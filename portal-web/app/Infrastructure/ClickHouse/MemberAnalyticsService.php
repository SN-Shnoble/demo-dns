<?php

declare(strict_types=1);

namespace App\Infrastructure\ClickHouse;

/**
 * Read-side service that turns the raw dns_logs ClickHouse rows into the
 * shapes the portal-web frontend expects. All public methods are
 * pure: any transport failure returns an empty payload so the calling
 * controller can decide whether to fall back to the local
 * (fallback) sample. We never throw past the service boundary — that
 * would block a member's dashboard because the analytics store is
 * down.
 */
final class MemberAnalyticsService
{
    public function __construct(
        private readonly ClickHouseClient $client = new ClickHouseClient(),
    ) {
    }

    public function ping(): bool
    {
        return $this->client->ping();
    }

    /**
     * @return array{
     *   today_queries: int,
     *   today_blocked: int,
     *   period_queries: int,
     *   top_domains: array<int, array{domain: string, count: int}>,
     *   top_blocked: array<int, array{domain: string, count: int}>
     * }
     */
    public function summaryForUser(string $userId): array
    {
        if (! $this->client->ping()) {
            return [
                'today_queries' => 0,
                'today_blocked' => 0,
                'period_queries' => 0,
                'top_domains' => [],
                'top_blocked' => [],
            ];
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT count() AS total, countIf(action = \'BLOCK\') AS blocked '.
                'FROM dns_logs WHERE user_id = {uid:String} AND timestamp >= now() - INTERVAL 24 HOUR',
                ['uid' => $userId]
            );
        } catch (\RuntimeException) {
            return $this->emptySummary();
        }

        $row = $rows[0] ?? [];
        $todayQueries = (int) ($row['total'] ?? 0);
        $todayBlocked = (int) ($row['blocked'] ?? 0);

        return [
            'today_queries'  => $todayQueries,
            'today_blocked'  => $todayBlocked,
            'period_queries' => $todayQueries,
            'top_domains'    => $this->topDomains($userId, 'all'),
            'top_blocked'    => $this->topDomains($userId, 'BLOCK'),
        ];
    }

    /**
     * @return array<int, array{domain: string, count: int}>
     */
    public function topDomains(string $userId, string $actionFilter = 'all'): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        $where = 'user_id = {uid:String} AND timestamp >= now() - INTERVAL 7 DAY';
        if (strtoupper($actionFilter) === 'BLOCK') {
            $where .= ' AND action = {act:String}';
            $params = ['uid' => $userId, 'act' => 'BLOCK'];
        } else {
            $params = ['uid' => $userId];
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT domain, count() AS hits FROM dns_logs '.
                'WHERE '.$where.' '.
                'GROUP BY domain ORDER BY hits DESC LIMIT 10',
                $params
            );
        } catch (\RuntimeException) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! isset($row['domain'])) {
                continue;
            }
            $out[] = [
                'domain' => (string) $row['domain'],
                'count'  => (int) ($row['hits'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return array{
     *   today_queries: int,
     *   today_blocked: int,
     *   period_queries: int,
     *   top_domains: array<int, array{domain: string, count: int}>,
     *   top_blocked: array<int, array{domain: string, count: int}>
     * }
     */
    private function emptySummary(): array
    {
        return [
            'today_queries'  => 0,
            'today_blocked'  => 0,
            'period_queries' => 0,
            'top_domains'    => [],
            'top_blocked'    => [],
        ];
    }
}
