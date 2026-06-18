<?php

declare(strict_types=1);

namespace App\Application\Member;

use App\Domain\Profile\ProfileConfigBuilder;
use App\Domain\Profile\ProfilePublishService;
use App\Domain\Publish\PublishService;
use App\Models\Profile;
use App\Models\ProfileVersion;
use Illuminate\Support\Facades\DB;

final class ProfilePublishApplicationService
{
    public function __construct(
        private readonly ProfileConfigBuilder $configBuilder,
        private readonly PublishService $publishService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function publishForUser(string $userId, string $profileId): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where('id', $profileId)
            ->firstOrFail()
            ->load(['rules', 'devices']);

        $featureSettings = [
            'security' => array_merge(
                ['enabled' => (bool) $profile->security_enabled],
                $profile->security_settings ?? [],
            ),
            'privacy' => array_merge(
                ['enabled' => (bool) $profile->privacy_enabled, 'log_mode' => $profile->log_mode],
                $profile->privacy_settings ?? [],
            ),
            'parental' => array_merge(
                ['enabled' => (bool) $profile->parental_enabled, 'safe_search' => (bool) $profile->safe_search_enabled],
                $profile->parental_settings ?? [],
            ),
        ];

        $profilePublishService = new ProfilePublishService($this->configBuilder, $this->publishService);

        return DB::transaction(function () use ($profile, $profilePublishService, $featureSettings): array {
            $publishResult = $profilePublishService->publish(
                $profile->toArray(),
                $profile->rules->toArray(),
                $featureSettings,
                [],
            );

            ProfileVersion::create([
                'profile_id' => $profile->id,
                'version' => $publishResult['profile_version'],
                'status' => 'published',
                'checksum' => $publishResult['checksum'],
                'config_json' => $publishResult['config_json'],
                'rule_count' => $profile->rules->count(),
                'published_by' => $profile->user_id,
                'external_publish_id' => $publishResult['publish_id'] ?? null,
                'published_at' => now(),
            ]);

            $profile->update([
                'draft_version' => $publishResult['profile_version'],
                'current_version' => $publishResult['profile_version'],
                'last_published_at' => now(),
            ]);

            return $publishResult;
        });
    }
}
