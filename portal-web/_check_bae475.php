<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$p = \App\Models\Profile::where('profile_uid', 'bae475')->first();
if (!$p) { echo "bae475 profile not found\n"; exit; }
echo "PK=".$p->id."\n";
echo "profile_uid=".$p->profile_uid."\n";
echo "user_id=".$p->user_id."\n";
echo "name=".$p->name."\n";
$pv = \App\Models\ProfileVersion::where('profile_id', $p->id)->orderByDesc('id')->first();
if ($pv) {
  echo "version=".$pv->version."\n";
  echo "status=".$pv->status."\n";
  echo "created_at=".$pv->created_at."\n";
} else {
  echo "no profile_version\n";
}
$rows = \App\Models\Device::where('profile_id', $p->id)->get(['id','device_uid','name','source_ip','ip_hash']);
echo "devices=".$rows->toJson(JSON_UNESCAPED_UNICODE)."\n";

$last = \App\Models\ProfileVersion::orderByDesc('id')->first();
if ($last) {
  echo "latest_version_pk=".$last->id." profile_id=".$last->profile_id." version=".$last->version." status=".$last->status."\n";
}
$cnt = \Illuminate\Support\Facades\DB::table('query_log_entries')->count();
echo "query_log_entries_total=".$cnt."\n";
$last2 = \Illuminate\Support\Facades\DB::table('query_log_entries')->orderByDesc('id')->first();
if ($last2) {
  echo "last_log_id=".$last2->id." profile_id=".$last2->profile_id." domain=".$last2->domain."\n";
}

$pub = \App\Models\PublishTask::orderByDesc('id')->first();
if ($pub) {
  echo "publish_task_id=".$pub->id." profile_id=".$pub->profile_id." status=".$pub->status." version=".$pub->config_version."\n";
}

$te = \App\Models\TaskExecution::orderByDesc('id')->first();
if ($te) {
  echo "task_execution_id=".$te->id." node_id=".$te->node_id." status=".$te->status."\n";
}
