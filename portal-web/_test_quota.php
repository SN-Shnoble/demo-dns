<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$col = DB::selectOne('SHOW COLUMNS FROM dns_config_versions WHERE Field = "config_json"');
echo "config_json type=" . $col->Type . PHP_EOL;

$arr = ["quota" => (object) [], "profile_id" => "test_q", "rules" => []];
$json = json_encode($arr);
echo "input_json=" . $json . PHP_EOL;

DB::table('config_versions')->insert([
    'version' => 9999,
    'target_scope' => 'profile',
    'target_profile_id' => null,
    'config_json' => $json,
    'checksum' => 'test',
    'published_at' => now(),
    'created_at' => now(),
]);
$row = DB::selectOne("SELECT config_json FROM dns_config_versions WHERE version = 9999");
echo "readback=" . $row->config_json . PHP_EOL;
DB::table('dns_config_versions')->where('version', 9999)->delete();
