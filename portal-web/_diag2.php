<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== bae475 logs in detail ===" . PHP_EOL;
$bae = \App\Models\Profile::where("profile_uid", "bae475")->first();
$logs = \App\Models\QueryLogEntry::where("profile_id", $bae->id)->orderByDesc("id")->limit(20)->get();
foreach ($logs as $e) {
    echo "  #" . $e->id . " device_id=" . $e->device_id . " domain=" . $e->query_name . " action=" . $e->action . " ts=" . $e->queried_at . PHP_EOL;
}

echo PHP_EOL . "=== Today (2026-06-20) bae475 logs ===" . PHP_EOL;
$today = \App\Models\QueryLogEntry::where("profile_id", $bae->id)
    ->where("queried_at", ">=", "2026-06-20 00:00:00")
    ->orderByDesc("id")
    ->get();
echo "Count: " . $today->count() . PHP_EOL;
foreach ($today as $e) {
    echo "  #" . $e->id . " device_id=" . $e->device_id . " domain=" . $e->query_name . " ts=" . $e->queried_at . PHP_EOL;
}

echo PHP_EOL . "=== Devices for bae475 ===" . PHP_EOL;
$devs = \App\Models\Device::where("profile_id", $bae->id)->get();
foreach ($devs as $d) {
    echo "  id=" . $d->id . " device_uid=" . $d->device_uid . " last_seen=" . $d->last_seen_at . PHP_EOL;
}

echo PHP_EOL . "=== Check active.json binding ===" . PHP_EOL;
$active = json_decode(file_get_contents(__DIR__ . "/../dns-resolver/configs/active.json"), true);
foreach ($active['profiles'] as $p) {
    echo "  profile_id=" . $p['profile_id'] . " devices=" . count($p['devices']) . PHP_EOL;
    foreach ($p['devices'] as $d) {
        echo "    device_id=" . $d['device_id'] . " source_ip=" . $d['source_ip'] . PHP_EOL;
    }
}
