<?php

namespace Tests\Feature;

use App\Domain\Auth\NodeTokenService;
use App\Models\Node;
use App\Models\NodeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Agent HMAC 鉴权中间件 (App\Http\Middleware\VerifyRequestSignature) 的端到端测试。
 *
 * 中间件契约（来自 VerifyRequestSignature.php）:
 *   - 必填 Header: Authorization (Bearer), X-Signature, X-Timestamp, X-Nonce
 *   - 签名 canonical:  ts \n METHOD \n path \n sha256(body)
 *   - 签名算法:      hmac_sha256(hmac_key, canonical)  -> hex
 *   - 时间戳容差:    ±300s
 *   - Nonce 长度:    16 ~ 128
 *   - 拒绝响应:      401, { "error": { "code": "unauthorized", "message": "...", "reason": "<具体原因>" } }
 *
 * 测试约定:
 *   - 只在「需要」时调用 provisionAgentCredentials()，header 缺失等可在 DB 查询前拒绝的场景不预先造数据
 *   - 失败测试只断言稳定的 error.code = "unauthorized" + 401，避免 reason 拆分时回归
 *   - 错误 reason 只在「该 reason 是被测核心行为」时断言（如 replay_detected、clock_skew_exceeded），并在断言点加注释
 *   - 不依赖 Admin / Sanctum 等其他鉴权通道；直接造 Node + NodeToken，与被测中间件解耦
 */
final class AgentHmacSignatureTest extends TestCase
{
    use RefreshDatabase;

    private NodeTokenService $tokens;

    /** heartbeat 端点的 canonical path 与 body，保持与签名计算一致。 */
    private const HEARTBEAT_PATH = '/api/v1/node/nodes/heartbeat';

    /**
     * 真实发出去的 POST body。
     *
     * 注意：postJson(path, [], headers) 实际发送的字节是 `[]`（Laravel 会用 json_encode
     * 把空数组编码成 `[]`），所以签名 canonical 必须用 `[]` 的 sha256，而不是 `{}`。
     * 历史上写的是 `'{}'`，会触发中间件 `signature_mismatch` 401。
     */
    private const HEARTBEAT_BODY_JSON = '[]';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokens = new NodeTokenService();
        // RefreshDatabase 不清缓存；防 nonce 跨用例污染
        Cache::flush();
    }

    // ------------------------------------------------------------------
    // 辅助方法
    // ------------------------------------------------------------------

    /**
     * 创建已审批节点 + 签发凭据。
     *
     * @return array{node: Node, api_key: string, secret: string, token: NodeToken}
     */
    private function provisionAgentCredentials(): array
    {
        $node = new Node();
        $node->forceFill([
            'id' => 'node_'.bin2hex(random_bytes(6)),
            'node_name' => 'test-node-'.bin2hex(random_bytes(3)),
            'status' => 'approved',
            // nodes.region 是 NOT NULL（迁移 2026_06_16_090000），测试 fixture 必须给出
            'region' => 'ap-northeast-1',
            'approved_at' => now(),
            'version' => 'v1.0.0',
        ])->save();

        $issued = $this->tokens->issueToken($node, 'test');

        return [
            'node' => $node,
            'api_key' => $issued['plain'],
            'secret' => $issued['hmac_key'],
            'token' => $node->tokens()->latest('created_at')->first(),
        ];
    }

    /**
     * 用给定的 api_key + secret 构造一套合规的 HMAC 请求头。
     *
     * @param  array<string, string>  $overrides  个别 header 可覆盖，用于构造"缺/错"场景
     * @return array<string, string>
     */
    private function withAgentSignature(
        string $apiKey,
        string $secret,
        array $overrides = [],
    ): array {
        $ts = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = $ts."\nPOST\n".self::HEARTBEAT_PATH."\n".hash('sha256', self::HEARTBEAT_BODY_JSON);
        $signature = hash_hmac('sha256', $canonical, $secret);

        return array_merge([
            'Authorization' => 'Bearer '.$apiKey,
            'X-Signature' => $signature,
            'X-Timestamp' => $ts,
            'X-Nonce' => $nonce,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $overrides);
    }

    /**
     * 用过期时间戳重算签名（其它 header 保持与 withAgentSignature 一致）。
     *
     * @param  array<string, string>  $base
     * @return array<string, string>
     */
    private function withExpiredTimestamp(array $base, string $apiKey, string $secret, int $secondsAgo): array
    {
        $ts = (string) (time() - $secondsAgo);
        $canonical = $ts."\nPOST\n".self::HEARTBEAT_PATH."\n".hash('sha256', self::HEARTBEAT_BODY_JSON);
        $base['X-Timestamp'] = $ts;
        $base['X-Signature'] = hash_hmac('sha256', $canonical, $secret);
        $base['X-Nonce'] = bin2hex(random_bytes(16));
        $base['Authorization'] = 'Bearer '.$apiKey;

        return $base;
    }

    // ------------------------------------------------------------------
    // 成功路径
    // ------------------------------------------------------------------

    public function test_accepts_request_with_valid_hmac_signature(): void
    {
        $cred = $this->provisionAgentCredentials();
        $headers = $this->withAgentSignature($cred['api_key'], $cred['secret']);

        $response = $this->postJson(self::HEARTBEAT_PATH, json_decode(self::HEARTBEAT_BODY_JSON, true), $headers);
        if ($response->getStatusCode() !== 200) {
            fwrite(STDERR, "DEBUG RESPONSE: " . $response->getContent() . "\n");
        }
        $response->assertOk();
    }

    // ------------------------------------------------------------------
    // 失败路径
    // ------------------------------------------------------------------

    /**
     * 完全不带任何 HMAC header。
     * 中间件在查 DB 前就 reject，provision 是浪费，这里故意不 provision。
     */
    public function test_rejects_request_when_auth_headers_are_missing(): void
    {
        $this->postJson(self::HEARTBEAT_PATH, [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.reason', 'missing_auth_headers');
    }

    public function test_accepts_request_without_hmac_key_header(): void
    {
        $cred = $this->provisionAgentCredentials();
        $headers = $this->withAgentSignature($cred['api_key'], $cred['secret']);

        $this->postJson(self::HEARTBEAT_PATH, [], $headers)
            ->assertOk();
    }

    public function test_rejects_request_with_invalid_signature(): void
    {
        $cred = $this->provisionAgentCredentials();
        $headers = $this->withAgentSignature($cred['api_key'], $cred['secret']);
        // 签名长度固定 64 hex；用 64 个 0 模拟"签名错误"
        $headers['X-Signature'] = str_repeat('0', 64);

        $this->postJson(self::HEARTBEAT_PATH, [], $headers)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.reason', 'signature_mismatch');
    }

    public function test_rejects_request_with_invalid_hmac_key(): void
    {
        $cred = $this->provisionAgentCredentials();
        // 全部用错误 secret 构造：签名用错 secret 算 + X-Hmac-Key 也填错
        $wrongSecret = 'hmk_'.str_repeat('z', 32);
        $headers = $this->withAgentSignature($cred['api_key'], $wrongSecret);

        $this->postJson(self::HEARTBEAT_PATH, [], $headers)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.reason', 'signature_mismatch');
    }

    public function test_rejects_unknown_api_key(): void
    {
        $cred = $this->provisionAgentCredentials();
        // 凭空捏造的 bearer；其它 header 用真凭据签名（保证走到 DB 查询这一步）
        $headers = $this->withAgentSignature('ntk_'.str_repeat('a', 32), $cred['secret']);

        $this->postJson(self::HEARTBEAT_PATH, [], $headers)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.reason', 'invalid_credentials');
    }

    public function test_rejects_request_with_expired_timestamp(): void
    {
        $cred = $this->provisionAgentCredentials();
        $headers = $this->withAgentSignature($cred['api_key'], $cred['secret']);
        // 1 小时前，超出 ±300s 窗口；用过期时间戳重算签名
        $headers = $this->withExpiredTimestamp($headers, $cred['api_key'], $cred['secret'], 3600);

        $this->postJson(self::HEARTBEAT_PATH, [], $headers)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.reason', 'clock_skew_exceeded');
    }

    public function test_rejects_request_with_too_short_nonce(): void
    {
        $cred = $this->provisionAgentCredentials();
        $headers = $this->withAgentSignature($cred['api_key'], $cred['secret']);
        // 8 字符，< 16
        $headers['X-Nonce'] = 'short1234';

        $this->postJson(self::HEARTBEAT_PATH, [], $headers)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.reason', 'invalid_nonce');
    }

    public function test_rejects_replayed_nonce(): void
    {
        $cred = $this->provisionAgentCredentials();

        // 第一次：合法请求，应当 OK
        $firstHeaders = $this->withAgentSignature($cred['api_key'], $cred['secret']);
        $this->postJson(self::HEARTBEAT_PATH, [], $firstHeaders)
            ->assertOk();

        // 第二次：复用同一 nonce + 重新签名（中间件在签名通过后才检查 nonce 重放，所以必须重算）
        $secondHeaders = $this->withAgentSignature($cred['api_key'], $cred['secret']);
        $secondHeaders['X-Nonce'] = $firstHeaders['X-Nonce'];
        $canonical = $secondHeaders['X-Timestamp']."\nPOST\n".self::HEARTBEAT_PATH."\n".hash('sha256', self::HEARTBEAT_BODY_JSON);
        $secondHeaders['X-Signature'] = hash_hmac('sha256', $canonical, $cred['secret']);

        $this->postJson(self::HEARTBEAT_PATH, [], $secondHeaders)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.reason', 'replay_detected');
    }

    public function test_rejects_revoked_token(): void
    {
        $cred = $this->provisionAgentCredentials();
        // 直接撤销该节点的 token
        $cred['token']->update(['revoked_at' => now()]);

        $this->postJson(
            self::HEARTBEAT_PATH,
            [],
            $this->withAgentSignature($cred['api_key'], $cred['secret']),
        )
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.reason', 'invalid_credentials');
    }
}
