<?php

namespace App\Domain\Rule;

use App\Domain\Profile\DomainNormalizer;

final class RuleService
{
    /**
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, array<string, mixed>>
     */
    public function normalizeRules(array $rules): array
    {
        $normalized = [];
        $seen = [];

        foreach ($rules as $rule) {
            $normalizedDomain = DomainNormalizer::normalize((string) ($rule['domain'] ?? ''));
            $key = implode(':', [
                $rule['profile_id'] ?? '',
                $rule['list_type'] ?? '',
                $rule['match_type'] ?? '',
                $normalizedDomain,
            ]);

            if (isset($seen[$key])) {
                throw new \InvalidArgumentException(sprintf('Duplicate rule detected for %s.', $normalizedDomain));
            }

            $seen[$key] = true;
            $rule['normalized_domain'] = $normalizedDomain;
            $normalized[] = $rule;
        }

        return $normalized;
    }
}
