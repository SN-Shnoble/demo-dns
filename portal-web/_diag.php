<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== QueryLogEntry count by profile ===" . PHP_EOL;
$rows = \App\Models\QueryLogEntry::query()
    ->select("profile_id", DB::raw("count(*) as cnt"))
    ->groupBy("profile_id")
    ->get();
foreach ($rows as $r) {
    $profile = \App\Models\Profile::find($r->profile_id);
    echo "  profile_id=" . $r->profile_id . " (uid=" . ($profile?->profile_uid ?? "?") . ") -> " . $r->cnt . " rows" . PHP_EOL;
}

echo PHP_EOL . "=== Latest 5 entries ===" . PHP_EOL;
$latest = \App\Models\QueryLogEntry::orderByDesc("id")->limit(5)->get();
foreach ($latest as $e) {
    echo "  #" . $e->id . " profile_id=" . $e->profile_id . " device_id=" . $e->device_id . " domain=" . $e->query_name . " ts=" . $e->queried_at . PHP_EOL;
}

echo PHP_EOL . "=== Profile bae475 lookup ===" . PHP_EOL;
$bae = \App\Models\Profile::where("profile_uid", "bae475")->first();
if ($bae) {
    echo "  bae475 -> id=" . $bae->id . " user_id=" . $bae->user_id . " name=" . $bae->name . PHP_EOL;
    $baeLogs = \App\Models\QueryLogEntry::where("profile_id", $bae->id)->count();
    echo "  bae475 log count: " . $baeLogs . PHP_EOL;
} else {
    echo "  bae475 NOT FOUND!" . PHP_EOL;
}

echo PHP_EOL . "=== All profiles ===" . PHP_EOL;
foreach (\App\Models\Profile::all(["id", "profile_uid", "user_id", "name"]) as $p) {
    echo "  id=" . $p->id . " uid=" . $p->profile_uid . " name=" . $p->name . PHP_EOL;
}
