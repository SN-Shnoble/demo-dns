<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Domain\HealthView\NodeHealthViewService;
use App\Models\Node;
use Illuminate\Http\JsonResponse;

final class HealthViewController
{
    public function show(): JsonResponse
    {
        $service = new NodeHealthViewService();

        return response()->json([
            'data' => $service->build(Node::query()->get()->toArray()),
        ]);
    }
}
