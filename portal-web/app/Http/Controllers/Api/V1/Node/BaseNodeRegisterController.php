<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * 节点安装注册端点（统一实现）
 *
 * 2026-06-22 改造：统一处理 dns-resolver 和 geodns 两种节点的 register 报告。
 * 节点身份由 bearer token（AuthenticateNodeToken 中间件）解析得到，
 * 不再依赖 body 里的 node_id 字段。URL 路径（geodns/register 或
 * dns-resolver/register）只作语义分类，不再做严格类型校验。
 *
 * 用途：
 *   1) 在 console 节点列表中标记「已注册 / 已安装」
 *   2) **签发并返回 api_key**（明文，仅此一次），节点应缓存到 configs/api_key
 *      之后所有业务请求（heartbeat / config / ...）用 api_key 鉴权，
 *      不再依赖加密 token，也不受 APP_KEY 变更影响。
 */
abstract class BaseNodeRegisterController
{
    /**
     * 子类可覆盖：限定节点类型（'dns-resolver' / 'geodns' / null=任意）。
     * 返回 null 表示不限制，URL 路径不影响 type 校验。
     */
    protected function expectedNodeType(): ?string
    {
        return null;
    }

    /**
     * 子类可覆盖：自定义 type 校验逻辑（白名单 / 多类型支持）。
     * 默认放行（与 expectedNodeType() 保持一致的行为）。
     */
    protected function checkNodeType(Node $node): bool
    {
        return true;
    }

    public function register(Request $request): JsonResponse
    {
        $start = microtime(true);

        $validated = $request->validate([
            // node_id 仅作日志/审计用，节点身份由 bearer token
            // (AuthenticateNodeToken 中间件) 解析得到。
            'node_id' => 'nullable|string|max:80',
            'installed_at' => 'nullable|date',
            'listen_addr' => 'nullable|string|max:80',
        ]);

        $node = $request->attributes->get('node');
        if (! $node) {
            $this->logError($request, 'node token required', 401, $start);
            return response()->json(['error' => ['code' => 'UNAUTHORIZED', 'message' => 'node token required']], 401);
        }

        $expectedType = $this->expectedNodeType();
        if ($expectedType !== null && $node->node_type !== $expectedType) {
            $msg = "node_type mismatch: expected={$expectedType} actual={$node->node_type}";
            $this->logError($request, $msg, 400, $start, $node);
            return response()->json([
                'error' => [
                    'code' => 'TYPE_MISMATCH',
                    'message' => "this endpoint only accepts {$expectedType} nodes",
                ],
            ], 400);
        }

        if (!$this->checkNodeType($node)) {
            $msg = "node_type not allowed: actual={$node->node_type}";
            $this->logError($request, $msg, 400, $start, $node);
            return response()->json([
                'error' => [
                    'code' => 'TYPE_MISMATCH',
                    'message' => "node_type '{$node->node_type}' is not allowed for this endpoint",
                ],
            ], 400);
        }

        $updateData = [
            'last_installed_at' => $validated['installed_at'] ?? now(),
            'last_listen_addr' => $validated['listen_addr'] ?? null,
            'install_status' => 'installed',
        ];

        // 同时签发 api_key。节点拿到后会缓存到独立文件，
        // 之后所有请求用 api_key 鉴权。如果 api_key 列尚未迁移（防御性），
        // 则只更新 install 状态，不返回 api_key，节点继续用 token 鉴权。
        $apiKeyPlain = null;
        if (Schema::hasColumn('nodes', 'api_key')) {
            $apiKeyPlain = 'ak_' . Str::random(40);
            $updateData['api_key'] = hash('sha256', $apiKeyPlain);
            $updateData['api_key_issued_at'] = now();
        }

        $node->update($updateData);

        $response = [
            'data' => [
                'node_id' => $node->node_code,
                'node_type' => $node->node_type,
                'install_status' => $node->install_status,
                'last_installed_at' => $node->last_installed_at?->toIso8601String(),
            ],
        ];

        if ($apiKeyPlain !== null) {
            $response['data']['api_key'] = $apiKeyPlain;
            $response['data']['api_key_path'] = 'configs/api_key';
        }

        $latencyMs = (int) ((microtime(true) - $start) * 1000);
        $this->logInfo($request, $node, 200, $latencyMs, $apiKeyPlain !== null);

        return response()->json($response);
    }

    protected function logInfo(Request $request, Node $node, int $status, int $latencyMs, bool $apiKeyIssued): void
    {
        Log::channel('node_api')->info('register', [
            'method' => $request->method(),
            'path' => $request->path(),
            'node_id' => $node->node_code,
            'node_type' => $node->node_type,
            'token_prefix' => $this->tokenPrefix($request),
            'status' => $status,
            'latency_ms' => $latencyMs,
            'api_key_issued' => $apiKeyIssued,
            'remote_addr' => $request->ip(),
        ]);
    }

    protected function logError(Request $request, string $message, int $status, float $start, ?Node $node = null): void
    {
        $latencyMs = (int) ((microtime(true) - $start) * 1000);
        Log::channel('node_api')->error('register', [
            'method' => $request->method(),
            'path' => $request->path(),
            'node_id' => $node?->node_code,
            'node_type' => $node?->node_type,
            'token_prefix' => $this->tokenPrefix($request),
            'status' => $status,
            'latency_ms' => $latencyMs,
            'error' => $message,
            'remote_addr' => $request->ip(),
        ]);
    }

    private function tokenPrefix(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }
        return substr($bearer, 0, 8) . '***';
    }
}
