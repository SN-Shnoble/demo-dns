<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rows = \Illuminate\Support\Facades\DB::table('query_log_entries')->orderByDesc('id')->limit(15)->get();
foreach ($rows as $r) {
  echo json_encode($r, JSON_UNESCAPED_UNICODE)."\n";
}
$cnt = \Illuminate\Support\Facades\DB::table('query_log_entries')->count();
echo "TOTAL=".$cnt."\n";
$byProfile = \Illuminate\Support\Facades\DB::table('query_log_entries')->select('profile_id', \Illuminate\Support\Facades\DB::raw('count(*) as c'))->groupBy('profile_id')->get();
echo "BY_PROFILE=".json_encode($byProfile)."\n";
