<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Application\Agent\ConfigAcknowledgementService;
use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConfigAckController
{
    public function __construct(
        private readonly ConfigAcknowledgementService $service,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Node $node */
        $node = $request->attributes->get('node');
        $validated = $request->validate([
            'config_version' => 'required|integer|min:0',
            'status' => 'required|string|max:30',
            'checksum' => 'nullable|string|max:100',
        ]);

        $ack = $this->service->acknowledge($node, $validated);

        return response()->json([
            'data' => $ack,
        ]);
    }
}
