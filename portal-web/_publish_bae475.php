<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Device;
use App\Models\Profile;

$profileUid = 'bae475';
$userId = '1';

// 1) bae475 profile
$profile = Profile::where('profile_uid', $profileUid)->first();
if (! $profile) {
    echo "FATAL: profile $profileUid not found\n";
    exit(1);
}
echo "profile.pk={$profile->id} profile.uid={$profile->profile_uid} user_id={$profile->user_id}\n";

// 2) 确保 bae475 有独立设备带 source_ip=127.0.0.1
//    b669c1 已经有 dev-localhost id=1，不能复用 device_uid
$device = Device::query()
    ->where('profile_id', $profile->id)
    ->where('device_uid', 'dev-localhost-bae')
    ->first();

if (! $device) {
    $device = Device::create([
        'user_id' => $profile->user_id,
        'profile_id' => $profile->id,
        'device_uid' => 'dev-localhost-bae',
        'name' => 'Localhost',
        'source' => 'auto',
        'protocol' => 'doh',
        'ip_hash' => hash('sha256', '127.0.0.1'),
        'source_ip' => '127.0.0.1',
        'fingerprint' => hash('sha256', 'localhost-bae-' . $profile->id),
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'last_query_at' => now(),
        'query_count' => 0,
        'status' => 'active',
    ]);
    echo "device created pk={$device->id} source_ip={$device->source_ip}\n";
} else {
    $device->update(['source_ip' => '127.0.0.1']);
    echo "device updated pk={$device->id} source_ip={$device->source_ip}\n";
}

// 3) 同时为 b669c1 (profile_id=1) 的 dev-localhost 设备补 source_ip，让它发布时也带 IP
$b669Device = Device::find(1);
if ($b669Device && empty($b669Device->source_ip)) {
    $b669Device->update(['source_ip' => '127.0.0.1']);
    echo "b669c1 device id=1 source_ip updated to 127.0.0.1\n";
}

// 4) publish bae475
$configBuilder = app(\App\Domain\Profile\ProfileConfigBuilder::class);
$publishService = app(\App\Domain\Publish\PublishService::class);
$app2 = new \App\Application\Member\ProfilePublishApplicationService($configBuilder, $publishService);

try {
    $result = $app2->publishForUser((string) $profile->user_id, $profileUid);
    echo "publish ok:\n";
    echo "  profile_id=" . $result['profile_id'] . "\n";
    echo "  config_version=" . $result['config_version'] . "\n";
    echo "  publish_id=" . $result['publish_id'] . "\n";
    echo "  publish_status=" . $result['publish_status'] . "\n";
    echo "  checksum=" . substr($result['checksum'], 0, 16) . "...\n";
} catch (Throwable $e) {
    echo "publish FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(2);
}

// 5) 验证
$cv = \Illuminate\Support\Facades\DB::selectOne("SELECT id, version, target_scope, target_profile_id FROM `dns_config_versions` ORDER BY id DESC LIMIT 1");
echo "latest config_version: id={$cv->id} v={$cv->version} scope={$cv->target_scope} target_pid={$cv->target_profile_id}\n";

$pt = \Illuminate\Support\Facades\DB::selectOne("SELECT id, config_version_id, profile_id, status, target_scope, target_node_count FROM `dns_publish_tasks` ORDER BY id DESC LIMIT 1");
echo "latest publish_task: id={$pt->id} cv_id={$pt->config_version_id} profile={$pt->profile_id} status={$pt->status} target={$pt->target_scope} node_count={$pt->target_node_count}\n";

$pv = \Illuminate\Support\Facades\DB::selectOne("SELECT id, profile_id, version FROM `dns_profile_versions` ORDER BY id DESC LIMIT 1");
echo "latest profile_version: id={$pv->id} profile_id={$pv->profile_id} version={$pv->version}\n";

$node = \Illuminate\Support\Facades\DB::selectOne("SELECT id, current_config_version, desired_config_version FROM `dns_nodes` WHERE id=1");
echo "node1: cur_v={$node->current_config_version} des_v={$node->desired_config_version}\n";

// 输出 bae475 bundle 的 profiles[0].devices 看是否带 source_ip
$cvRow = \Illuminate\Support\Facades\DB::selectOne("SELECT config_json FROM `dns_config_versions` ORDER BY id DESC LIMIT 1");
$json = is_string($cvRow->config_json) ? $cvRow->config_json : json_encode($cvRow->config_json);
echo "--- bae475 config_json devices ---\n";
$arr = json_decode($json, true);
$devices = $arr['devices'] ?? [];
foreach ($devices as $d) {
    echo "  device_id=" . ($d['device_id'] ?? 'null') . " source_ip=" . ($d['source_ip'] ?? 'null') . " name=" . ($d['name'] ?? 'null') . "\n";
}
