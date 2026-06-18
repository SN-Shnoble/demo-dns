<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AuditService
{
    /**
     * Log an action to the audit trail.
     */
    public function log(
        string $action,
        ?string $actorId = null,
        string $actorType = 'user',
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $ipHash = null,
        ?string $userAgent = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before_json' => $before,
            'after_json' => $after,
            'ip_hash' => $ipHash,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Search audit logs with filters.
     *
     * @param  array{actor_id?: string, action?: string, from?: string, to?: string, page?: int, per_page?: int}  $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = AuditLog::query();

        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 20), 100);

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
