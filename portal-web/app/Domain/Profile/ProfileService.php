<?php

namespace App\Domain\Profile;

use App\Models\Profile;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ProfileService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForCurrentUser(string $userId): array
    {
        return Profile::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $userId, array $payload): array
    {
        $profile = Profile::create([
            'user_id' => $userId,
            'name' => $payload['name'] ?? 'My Profile',
            'description' => $payload['description'] ?? null,
            'default_action' => $payload['default_action'] ?? 'allow',
            'block_response' => $payload['block_response'] ?? 'nxdomain',
            'security_enabled' => $payload['security_enabled'] ?? true,
            'privacy_enabled' => $payload['privacy_enabled'] ?? true,
            'adblock_enabled' => $payload['adblock_enabled'] ?? false,
            'parental_enabled' => $payload['parental_enabled'] ?? false,
        ]);

        return $profile->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $userId, string $profileId): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where('id', $profileId)
            ->firstOrFail();

        return $profile->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(string $userId, string $profileId, array $payload): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where('id', $profileId)
            ->firstOrFail();

        $updatable = array_intersect_key($payload, array_flip([
            'name', 'description', 'default_action', 'block_response',
            'security_enabled', 'adblock_enabled', 'parental_enabled',
            'privacy_enabled', 'safe_search_enabled', 'log_mode',
        ]));

        $profile->update($updatable);

        return $profile->fresh()->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $userId, string $profileId): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where('id', $profileId)
            ->firstOrFail();

        $profile->delete();

        return [
            'id' => $profileId,
            'deleted' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function copy(string $userId, string $profileId): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where('id', $profileId)
            ->firstOrFail();

        $clone = $profile->replicate();
        $clone->name = $profile->name . ' (Copy)';
        $clone->save();

        return $clone->toArray();
    }

    /**
     * @param array<int, string> $profileIds
     * @return array<string, mixed>
     */
    public function batchDelete(string $userId, array $profileIds): array
    {
        $existingIds = Profile::where('user_id', $userId)
            ->whereIn('id', $profileIds)
            ->pluck('id')
            ->all();

        if ($existingIds === []) {
            return [
                'requested' => count($profileIds),
                'deleted' => 0,
                'not_found' => array_values($profileIds),
            ];
        }

        $notFound = array_values(array_diff($profileIds, $existingIds));
        $deletedCount = Profile::where('user_id', $userId)
            ->whereIn('id', $existingIds)
            ->delete();

        return [
            'requested' => count($profileIds),
            'deleted' => $deletedCount,
            'not_found' => $notFound,
        ];
    }
}
