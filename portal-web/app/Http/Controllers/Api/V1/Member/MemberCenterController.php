<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Application\Member\MemberCenterOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MemberCenterController
{
    public function __construct(
        private readonly MemberCenterOverviewService $service,
    ) {
    }

    public function overview(Request $request): JsonResponse
    {
        $overview = $this->service->getOverview($request->user()->id);

        return response()->json(['data' => $overview]);
    }
}
