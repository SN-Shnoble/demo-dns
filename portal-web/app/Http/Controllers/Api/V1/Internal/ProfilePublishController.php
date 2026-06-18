<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Internal;

use App\Domain\Publish\PublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfilePublishController
{
    public function __construct(
        private readonly PublishService $publishService = new PublishService(),
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile_id' => 'required|string|max:40',
            'profile_version' => 'required|integer|min:1',
            'checksum' => 'required|string|max:100',
            'config_json' => 'required|array',
        ]);

        $result = $this->publishService->recordPublish(
            (string) $validated['profile_id'],
            (int) $validated['profile_version'],
            (string) $validated['checksum'],
            $validated['config_json'],
        );

        return response()->json([
            'data' => $result,
        ]);
    }
}
