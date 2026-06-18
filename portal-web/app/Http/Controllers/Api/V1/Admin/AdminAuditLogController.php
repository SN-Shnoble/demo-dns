<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminAuditLogController
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'actor_id' => 'nullable|string',
            'action' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $logs = $this->auditService->search($filters);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
