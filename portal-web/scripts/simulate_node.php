<?php
// scripts/simulate_node.php
// 模拟 dns-resolver 节点的完整工作流程：verify token → heartbeat → config pull
// 用于本地联调测试

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$NODE_TOKEN = $argv[1] ?? null;
$HMAC_KEY = $argv[2] ?? null;
$NODE_ID = 'nd_vydisdlb7k';
$BASE_URL = 'http://127.0.0.1:8081';
$API_KEY_FOR_VERIFY = $NODE_TOKEN;

if (!$NODE_TOKEN || !$HMAC_KEY) {
    echo "Usage: php simulate_node.php <token> <hmac_key>\n";
    exit(1);
}

echo "==== OcerDNS Node Simulator ====\n";
echo "Node ID:        $NODE_ID\n";
echo "API Base URL:   $BASE_URL\n";
echo "Token prefix:   " . substr($NODE_TOKEN, 0, 12) . "...\n\n";

/**
 * 构造带 HMAC 签名的请求
 */
function signedRequest(string $method, string $url, string $body, string $bearer, string $hmacKey): array
{
    $parsed = parse_url($url);
    $path = $parsed['path'];

    $ts = (string) time();
    $bodyHash = hash('sha256', $body);
    $canonical = $ts . "\n" . strtoupper($method) . "\n" . $path . "\n" . $bodyHash;
    $signature = hash_hmac('sha256', $canonical, $hmacKey);

    $nonce = bin2hex(random_bytes(16));

    return [
        'http' => [
            'method' => $method,
            'header' => [
                "Authorization: Bearer $bearer",
                "X-Signature: $signature",
                "X-Timestamp: $ts",
                "X-Nonce: $nonce",
                "Content-Type: application/json",
            ],
            'content' => $body,
            'ignore_errors' => true,
        ],
    ];
}

function doRequest(string $method, string $url, string $body, string $bearer, string $hmacKey): array
{
    $ctxOpts = signedRequest($method, $url, $body, $bearer, $hmacKey);
    $ctx = stream_context_create($ctxOpts);
    $body_stream = $body ?: null;
    $response = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/[\d\.]+ (\d+)#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    return ['status' => $status, 'body' => $response];
}

// 1. 验证 token（模拟 install 阶段）
echo "[1/3] Verifying token via /api/v1/node/tokens/verify ...\n";
$verifyBody = json_encode(['token' => $API_KEY_FOR_VERIFY]);
$verifyUrl = $BASE_URL . '/api/v1/node/tokens/verify';
$ctx = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json",
        'content' => $verifyBody,
        'ignore_errors' => true,
    ],
]);
$resp = @file_get_contents($verifyUrl, false, $ctx);
echo "  Response: $resp\n\n";

// 2. 发送心跳（模拟节点运行）
echo "[2/3] Sending heartbeat to /api/v1/node/nodes/heartbeat ...\n";
$heartbeatBody = json_encode([
    'status' => 'online',
    'uptime_seconds' => 3600,
    'version' => '1.0.0',
    'current_config_version' => 1,
    'profiles_loaded' => 5,
]);
$heartbeatUrl = $BASE_URL . '/api/v1/node/nodes/heartbeat';
$res = doRequest('POST', $heartbeatUrl, $heartbeatBody, $NODE_TOKEN, $HMAC_KEY);
echo "  Status:   {$res['status']}\n";
echo "  Response: " . substr($res['body'] ?? '', 0, 200) . "\n\n";

// 3. 拉取配置
echo "[3/3] Pulling config from /api/v1/node/resolver/config ...\n";
$configUrl = $BASE_URL . '/api/v1/node/resolver/config';
$res = doRequest('GET', $configUrl, '', $NODE_TOKEN, $HMAC_KEY);
echo "  Status:   {$res['status']}\n";
echo "  Response: " . substr($res['body'] ?? '', 0, 200) . "\n\n";

echo "==== Simulator complete ====\n";
