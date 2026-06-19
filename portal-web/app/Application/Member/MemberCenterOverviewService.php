<?php

declare(strict_types=1);

namespace App\Application\Member;

use App\Domain\Profile\UserDashboardService;

final class MemberCenterOverviewService
{
    public function __construct(
        private readonly UserDashboardService $memberCenterService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(string $userId): array
    {
        return $this->memberCenterService->getOverview($userId);
    }
}
