<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Profile bae475 status ===" . PHP_EOL;
$p = \App\Models\Profile::where("profile_uid", "bae475")->first();
if ($p) {
    echo "  id=" . $p->id . " uid=" . $p->profile_uid . " name=" . $p->name . PHP_EOL;
    echo "  version=" . $p->version . " published_at=" . ($p->published_at ?? 'null') . PHP_EOL;
}

echo PHP_EOL . "=== Profile b669c1 status ===" . PHP_EOL;
$p = \App\Models\Profile::where("profile_uid", "b669c1")->first();
if ($p) {
    echo "  id=" . $p->id . " uid=" . $p->profile_uid . " name=" . $p->name . PHP_EOL;
    echo "  version=" . $p->version . " published_at=" . ($p->published_at ?? 'null') . PHP_EOL;
}

echo PHP_EOL . "=== ProfileVersions ===" . PHP_EOL;
$pv = \App\Models\ProfileVersion::orderByDesc("id")->limit(10)->get();
foreach ($pv as $v) {
    $p = \App\Models\Profile::find($v->profile_id);
    echo "  #" . $v->id . " profile_id=" . $v->profile_id . " (uid=" . ($p?->profile_uid ?? "?") . ") version=" . $v->version . " status=" . $v->status . " published_at=" . $v->published_at . PHP_EOL;
}

echo PHP_EOL . "=== ConfigVersions ===" . PHP_EOL;
$cv = \App\Models\ConfigVersion::orderByDesc("id")->get();
foreach ($cv as $c) {
    echo "  #" . $c->id . " version=" . $c->version . " target_profile_id=" . $c->target_profile_id . " checksum=" . $c->checksum . PHP_EOL;
}

echo PHP_EOL . "=== PublishTasks ===" . PHP_EOL;
$pt = \App\Models\PublishTask::orderByDesc("id")->limit(10)->get();
foreach ($pt as $t) {
    echo "  #" . $t->id . " config_version_id=" . $t->config_version_id . " profile_id=" . $t->profile_id . " status=" . $t->status . " target_scope=" . $t->target_scope . PHP_EOL;
}
