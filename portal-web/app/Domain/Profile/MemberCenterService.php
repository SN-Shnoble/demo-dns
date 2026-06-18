<?php

namespace App\Domain\Profile;

use App\Models\Device;
use App\Models\Profile;
use App\Models\User;

final class MemberCenterService
{
    public function __construct(
        private readonly MemberWorkspaceService $workspace = new MemberWorkspaceService(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(string $userId): array
    {
        $this->workspace->primaryProfile($userId);
        $profiles = Profile::where('user_id', $userId)->get();
        $deviceCount = Device::where('user_id', $userId)->count();
        $analytics = $this->workspace->analytics($userId);

        $profileCount = $profiles->count();

        return [
            'user' => [
                'id' => $userId,
                'plan_code' => User::findOrFail($userId)->plan_code ?: 'free',
            ],
            'stats' => [
                'profile_count' => $profileCount,
                'device_count' => $deviceCount,
                'today_queries' => $analytics['today_queries'],
                'today_blocked' => $analytics['today_blocked'],
            ],
            'profiles' => $profiles->toArray(),
        ];
    }
}
