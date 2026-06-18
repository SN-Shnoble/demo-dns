<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\System\HealthCheckService;
use App\Models\ConfigVersion;
use App\Models\Node;
use App\Models\PublishTask;
use App\Models\QueryLogIngestBatch;
use Illuminate\Http\JsonResponse;

final class AdminStatsController
{
    public function overview(): JsonResponse
    {
        // 节点只关心 online / offline（pending 视作离线，disabled 不计入）
        $totalNodes = Node::count();
        $onlineNodes = Node::where('status', 'online')->count();
        $offlineNodes = max($totalNodes - $onlineNodes, 0);

        $publishes = PublishTask::count();
        $completedPublishes = PublishTask::where('status', 'completed')->count();
        $queryBatches = (int) QueryLogIngestBatch::sum('item_count');

        $health = (new HealthCheckService())->probe();

        return response()->json([
            'data' => [
                'nodes' => [
                    'total' => $totalNodes,
                    'online' => $onlineNodes,
                    'offline' => $offlineNodes,
                ],
                'publishes' => [
                    'total' => $publishes,
                    'success_rate' => $publishes > 0 ? round(($completedPublishes / $publishes) * 100, 2) : 0.0,
                    'last_24h' => PublishTask::where('queued_at', '>=', now()->subDay())->count(),
                ],
                'configs' => [
                    'total_versions' => ConfigVersion::count(),
                    'active_nodes' => $onlineNodes,
                    'latest_version' => (int) (ConfigVersion::max('version') ?? 0),
                ],
                'queries' => [
                    'last_24h' => $queryBatches,
                ],
                'system' => array_merge([
                    'uptime_hours' => 0,
                    'php_version' => PHP_VERSION,
                ], $health),
            ],
        ]);
    }
}
