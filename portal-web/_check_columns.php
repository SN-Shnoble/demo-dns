<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "---dns_nodes full---\n";
$nodes = \Illuminate\Support\Facades\DB::select("SELECT id,node_code,name,status,public_ipv4,created_at,last_heartbeat_at,current_config_version,desired_config_version FROM `dns_nodes`");
foreach ($nodes as $n) {
    echo "id={$n->id} code={$n->node_code} name={$n->name} status={$n->status} cur_v={$n->current_config_version} des_v={$n->desired_config_version}\n";
}

echo "\n---dns_node_tokens columns---\n";
$rows = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM `dns_node_tokens`");
foreach ($rows as $r) {
    echo "  {$r->Field} ({$r->Type})\n";
}

$tokens = \Illuminate\Support\Facades\DB::select("SELECT * FROM `dns_node_tokens`");
foreach ($tokens as $t) {
    $secret = $t->hmac_secret_encrypted ?? null;
    echo "  id={$t->id} node_id={$t->node_id} status={$t->status} api_token=" . substr((string)($t->api_token ?? ''), 0, 20) . " has_secret=" . (!empty($secret) ? 'yes' : 'no') . "\n";
}

echo "\n---dns_api_keys columns---\n";
$rows = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM `dns_api_keys`");
foreach ($rows as $r) {
    echo "  {$r->Field} ({$r->Type})\n";
}
