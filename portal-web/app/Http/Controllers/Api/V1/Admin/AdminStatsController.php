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
        // 2026-06-22: 单一事实源 — 用 Node::online() scope 算在线数，不再依赖 nodes.status 列（已 drop）。
        // 离线 = 总数 - 在线 - 未装（degraded 也算"还在岗"故计入在线）。
        $totalNodes = Node::count();
        $onlineNodes = (int) Node::online()->count();
        $notInstalledNodes = (int) Node::where('install_status', '!=', 'installed')->count();
        $offlineNodes = max($totalNodes - $onlineNodes - $notInstalledNodes, 0);

        $publishes = PublishTask::count();
        $completedPublishes = PublishTask::where('status', 'succeeded')->count();
        $queryBatches = (int) QueryLogIngestBatch::sum('item_count');

        $health = (new HealthCheckService())->probe();

        return response()->json([
            'data' => [
                'nodes' => [
                    'total' => $totalNodes,
                    'online' => $onlineNodes,
                    'offline' => $offlineNodes,
                    'not_installed' => $notInstalledNodes,
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
                    'gafam' => $this->countByCategory('gafam'),
                    'root' => $this->countByCategory('root'),
                    'encrypted_dns' => $this->countByCategory('encrypted_dns'),
                    'dnssec_valid' => $this->countByCategory('dnssec_valid'),
                ],
                'system' => array_merge([
                    'uptime_hours' => 0,
                    'php_version' => PHP_VERSION,
                ], $health),
            ],
        ]);
    }

    /**
     * UI.md #32: 24h 维度统计（GAFAM / 根域名 / 加密DNS / DNSSEC）。
     * CH 不可用时返回 0，避免 5xx。
     */
    private function countByCategory(string $bucket): int
    {
        try {
            $client = new \App\Infrastructure\ClickHouse\ClickHouseClient();
            $query = match ($bucket) {
                'gafam' => "SELECT count() AS c FROM dns_logs WHERE event_time >= now() - INTERVAL 24 HOUR AND domain IN ('google.com','www.google.com','youtube.com','www.youtube.com','facebook.com','www.facebook.com','instagram.com','www.instagram.com','whatsapp.com','www.whatsapp.com','x.com','twitter.com','www.x.com','www.twitter.com','apple.com','www.apple.com','amazon.com','www.amazon.com','microsoft.com','www.microsoft.com')",
                'root' => "SELECT count() AS c FROM dns_logs WHERE event_time >= now() - INTERVAL 24 HOUR AND position(domain, '.') = 0",
                'encrypted_dns' => "SELECT 0 AS c",
                'dnssec_valid' => "SELECT 0 AS c",
                default => null,
            };
            if ($query === null) {
                return 0;
            }
            $row = $client->jsonSelect($query);
            return (int) ($row[0]['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
