<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Devices for all profiles ===" . PHP_EOL;
$devs = \App\Models\Device::orderBy("profile_id")->get();
foreach ($devs as $d) {
    $p = \App\Models\Profile::find($d->profile_id);
    echo "  id=" . $d->id . " profile_id=" . $d->profile_id . " (uid=" . ($p?->profile_uid ?? "?") . ") device_uid=" . $d->device_uid . " source_ip=" . ($d->source_ip ?? 'null') . " last_seen=" . $d->last_seen_at . PHP_EOL;
}

echo PHP_EOL . "=== Profile rules count ===" . PHP_EOL;
foreach (\App\Models\Profile::all() as $p) {
    $count = $p->rules()->count();
    echo "  profile_id=" . $p->id . " (uid=" . $p->profile_uid . ") rules=" . $count . PHP_EOL;
}
