<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\NodeToken;

$rows = NodeToken::where('node_id', 1)->get();
foreach ($rows as $t) {
    echo "id={$t->id} status={$t->status} token_hash=" . substr($t->token_hash ?? '', 0, 16) . " hmac_key_hash=" . substr($t->hmac_key_hash ?? '', 0, 16) . " has_secret=" . (!empty($t->hmac_secret_encrypted) ? 'yes' : 'no') . "\n";
    if (!empty($t->hmac_secret_encrypted)) {
        try {
            $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($t->hmac_secret_encrypted);
            echo "  decrypted_secret=" . $decrypted . "\n";
        } catch (Throwable $e) {
            echo "  decrypt error: " . $e->getMessage() . "\n";
        }
    }
}

// 验证 secret 跟 server.yaml 里的 hmk_mfbkds0n7pb4jbunr7k7jroz2izojvnd 一致
$expectedSecret = 'hmk_mfbkds0n7pb4jbunr7k7jroz2izojvnd';
$expectedTokenHash = hash('sha256', 'ocnd_aNLDX7QjKhKI2pVbPc0rFlroHGzD60lD9CtYglcx');
echo "expected_token_hash=" . $expectedTokenHash . "\n";
$token = NodeToken::where('token_hash', $expectedTokenHash)->first();
if ($token) {
    echo "FOUND token id={$token->id}\n";
    if (!empty($token->hmac_secret_encrypted)) {
        $dec = \Illuminate\Support\Facades\Crypt::decryptString($token->hmac_secret_encrypted);
        echo "  decrypted=" . $dec . "\n";
        echo "  matches_expected=" . ($dec === $expectedSecret ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "TOKEN NOT FOUND with expected token hash\n";
}
