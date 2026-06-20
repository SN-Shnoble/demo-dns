<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cv = \App\Models\ConfigVersion::orderByDesc('id')->first();
if (!$cv) { echo "no config version\n"; exit; }
echo "config_version id=".$cv->id." version=".$cv->version." checksum=".$cv->checksum."\n";
echo "config_json first 2000 chars:\n";
echo substr(json_encode($cv->config_json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), 0, 2000)."\n";
