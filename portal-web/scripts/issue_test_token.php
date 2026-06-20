<?php
// scripts/issue_test_token.php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$node = \App\Models\Node::where('node_code', 'nd_vydisdlb7k')->first();
if (!$node) {
    echo "Node not found\n";
    exit(1);
}

$token = 'ntk_test' . bin2hex(random_bytes(16));
$hmacKey = 'hmk_test' . bin2hex(random_bytes(16));
$tokenHash = hash('sha256', $token);
$hmacKeyHash = hash('sha256', $hmacKey);
$encrypted = \Illuminate\Support\Facades\Crypt::encryptString($hmacKey);
$prefix = substr($token, 0, 12);

\App\Models\NodeToken::create([
    'node_id' => $node->id,
    'token_prefix' => $prefix,
    'token_hash' => $tokenHash,
    'hmac_key_hash' => $hmacKeyHash,
    'hmac_secret_encrypted' => $encrypted,
    'scopes' => json_encode(['heartbeat', 'config_pull']),
    'status' => 'active',
]);

echo "NODE_ID=" . $node->id . "\n";
echo "TOKEN=" . $token . "\n";
echo "HMAC_KEY=" . $hmacKey . "\n";
