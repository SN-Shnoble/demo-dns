<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Domain\Ingest\QueryLogReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QueryLogReadController
{
    public function __construct(
        private readonly QueryLogReadService $service = new QueryLogReadService(),
    ) {
    }

    public function logs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|string|max:40',
            'action' => 'nullable|string|max:20',
            'domain' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->service->logs($validated['user_id'], $validated));
    }

    public function analytics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|string|max:40',
        ]);

        return response()->json([
            'data' => $this->service->analytics($validated['user_id']),
        ]);
    }
}
