<?php

declare(strict_types=1);

namespace App\Application\Member;

use App\Domain\Profile\DomainNormalizer;
use App\Domain\Profile\MemberWorkspaceService;
use App\Models\ProfileRule;

final class WorkspaceRuleService
{
    public function __construct(
        private readonly MemberWorkspaceService $workspaceService,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function updateRule(string $userId, string $listType, string $ruleId, array $attributes): array
    {
        $profile = $this->workspaceService->primaryProfile($userId);

        $rule = ProfileRule::where('profile_id', $profile->id)
            ->where('list_type', $listType)
            ->where('id', $ruleId)
            ->firstOrFail();

        if (isset($attributes['domain'])) {
            $rule->domain = (string) $attributes['domain'];
            $rule->normalized_domain = DomainNormalizer::normalize((string) $attributes['domain']);
        }

        if (isset($attributes['match_type'])) {
            $rule->match_type = (string) $attributes['match_type'];
        }

        if (array_key_exists('enabled', $attributes)) {
            $rule->enabled = (bool) $attributes['enabled'];
        }

        $rule->save();

        return $rule->fresh()->toArray();
    }
}
