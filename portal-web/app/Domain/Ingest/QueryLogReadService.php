<?php

namespace App\Domain\Ingest;

use App\Models\QueryLogEntry;
use Illuminate\Database\QueryException;

final class QueryLogReadService
{
    /**
     * @param array<string, mixed> $filters
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function logs(string $userId, array $filters): array
    {
        try {
            $query = QueryLogEntry::query()
                ->where('user_id', $userId)
                ->orderByDesc('queried_at')
                ->orderByDesc('created_at');
        } catch (QueryException) {
            return ['data' => [], 'meta' => ['page' => 1, 'per_page' => 20, 'total' => 0]];
        }

        if (! empty($filters['action'])) {
            $query->where('action', (string) $filters['action']);
        }

        if (! empty($filters['domain'])) {
            $query->where('query_name', 'like', '%' . strtolower((string) $filters['domain']) . '%');
        }

        if (isset($filters['profile_pk']) && (int) $filters['profile_pk'] > 0) {
            $query->where('profile_id', (int) $filters['profile_pk']);
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $total = (clone $query)->count();
        $items = $query
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (QueryLogEntry $entry): array => [
                'id' => $entry->id,
                'profile_id' => $entry->profile_id,
                'device_id' => $entry->device_id,
                'domain' => $entry->query_name,
                'action' => $entry->action,
                'reason' => $entry->reason,
                'category' => $entry->category,
                'query_type' => $entry->query_type,
                'rcode' => $entry->rcode,
                'latency_ms' => $entry->latency_ms,
                'timestamp' => optional($entry->queried_at)->toIso8601String() ?? optional($entry->created_at)->toIso8601String(),
            ])
            ->all();

        return [
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analytics(string $userId, ?string $profileId = null): array
    {
        try {
            $query = QueryLogEntry::query()
                ->where('user_id', $userId)
                ->orderByDesc('queried_at');
            if ($profileId !== null && $profileId !== '') {
                $query->where('profile_id', $profileId);
            }
            $entries = $query->get(['query_name', 'action']);
        } catch (QueryException) {
            return [
                'today_queries' => 0,
                'today_blocked' => 0,
                'period_queries' => 0,
                'top_domains' => [],
                'top_blocked' => [],
            ];
        }

        $topDomains = $entries
            ->countBy('query_name')
            ->map(fn (int $count, string $domain): array => ['domain' => $domain, 'count' => $count])
            ->sortByDesc('count')
            ->values()
            ->take(10)
            ->all();

        $topBlocked = $entries
            ->where('action', 'block')
            ->countBy('query_name')
            ->map(fn (int $count, string $domain): array => ['domain' => $domain, 'count' => $count])
            ->sortByDesc('count')
            ->values()
            ->take(10)
            ->all();

        return [
            'today_queries' => $entries->count(),
            'today_blocked' => $entries->where('action', 'block')->count(),
            'period_queries' => $entries->count(),
            'top_domains' => $topDomains,
            'top_blocked' => $topBlocked,
        ];
    }
}
