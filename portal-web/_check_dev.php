<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Device;
use App\Models\Profile;

$rows = \Illuminate\Support\Facades\DB::select("SELECT id, device_uid, profile_id, user_id, name FROM `dns_devices`");
foreach ($rows as $r) {
    echo "id={$r->id} uid={$r->device_uid} profile_id={$r->profile_id} user_id={$r->user_id} name={$r->name}\n";
}
