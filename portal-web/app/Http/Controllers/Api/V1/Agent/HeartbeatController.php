<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Domain\Heartbeat\HeartbeatService;
use App\Models\Node;
use App\Models\NodeHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HeartbeatController
{
    public function store(Request $request): JsonResponse
    {
        $service = new HeartbeatService();
        /** @var Node $node */
        $node = $request->attributes->get('node');

        // 心跳只携带"是否在岗 + 持有配置版本"，不再带 qps/cpu/mem/disk/error
        $heartbeat = $request->validate([
            'status' => 'nullable|string|max:30',
            'uptime_seconds' => 'nullable|integer|min:0',
            'version' => 'nullable|string|max:50',
            'current_config_version' => 'nullable|integer|min:0',
            'profiles_loaded' => 'nullable|integer|min:0',
            'last_config_pull_at' => 'nullable|date',
            'last_log_flush_at' => 'nullable|date',
        ]);
        $heartbeat['status'] = $heartbeat['status'] ?? HeartbeatService::STATUS_ONLINE;
        $heartbeat['current_config_version'] = (int) ($heartbeat['current_config_version'] ?? $node->current_config_version);
        $heartbeat['node_id'] = $node->id;

        NodeHeartbeat::create([
            'node_id' => $node->id,
            'status' => $heartbeat['status'],
            'uptime_seconds' => (int) ($heartbeat['uptime_seconds'] ?? 0),
            'version' => $heartbeat['version'] ?? $node->version,
            'current_config_version' => $heartbeat['current_config_version'],
            'profiles_loaded' => (int) ($heartbeat['profiles_loaded'] ?? 0),
            'last_config_pull_at' => $heartbeat['last_config_pull_at'] ?? null,
            'last_log_flush_at' => $heartbeat['last_log_flush_at'] ?? null,
            'reported_at' => now(),
            'created_at' => now(),
        ]);

        $node->update([
            'status' => $service->computeStatus($heartbeat),
            'version' => $heartbeat['version'] ?? $node->version,
            'current_config_version' => $heartbeat['current_config_version'],
            'last_heartbeat_at' => now(),
        ]);

        return response()->json([
            'data' => $service->evaluate($heartbeat, [
                'desired_config_version' => $node->desired_config_version,
            ]),
        ]);
    }
}
