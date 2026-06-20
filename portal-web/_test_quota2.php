<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ConfigVersion;

// Simulate the exact call pattern
$arr = [
  "quota" => (object) [],
  "profile_id" => "test_eloquent",
  "rules" => [],
];
echo "BEFORE Eloquent setAttribute: json=" . json_encode($arr) . PHP_EOL;

$cv = new ConfigVersion();
$cv->config_json = $arr;
echo "AFTER Eloquent setAttribute: attributes.config_json=" . $cv->getAttributes()['config_json'] . PHP_EOL;

// Now use ::create
$cv2 = ConfigVersion::create([
    'version' => 8888,
    'target_scope' => 'profile',
    'target_profile_id' => null,
    'config_json' => $arr,
    'checksum' => 'test_eloquent',
    'published_at' => now(),
    'created_at' => now(),
]);

$row = \Illuminate\Support\Facades\DB::selectOne("SELECT config_json FROM dns_config_versions WHERE id = " . $cv2->id);
echo "FROM DB RAW: " . $row->config_json . PHP_EOL;

$cv3 = ConfigVersion::find($cv2->id);
echo "FROM ELOQUENT GET: " . json_encode($cv3->config_json) . PHP_EOL;

ConfigVersion::where('id', $cv2->id)->delete();
