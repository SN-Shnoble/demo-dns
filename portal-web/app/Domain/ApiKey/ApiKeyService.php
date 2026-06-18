<?php

namespace App\Domain\ApiKey;

use App\Models\ApiKey;
use Illuminate\Support\Str;

final class ApiKeyService
{
    /**
     * List all API keys for a user.
     * @return ApiKey[]
     */
    public function list(string $userId): array
    {
        return ApiKey::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Create a new API key and return the plaintext key (shown only once).
     */
    public function create(string $userId, string $name, array $scopes): array
    {
        $plaintext = 'ocer_' . Str::random(40);
        $prefix = substr($plaintext, 0, 8);

        $key = new ApiKey();
        $key->user_id = $userId;
        $key->name = $name;
        $key->key_hash = hash('sha256', $plaintext);
        $key->key_prefix = $prefix;
        $key->scopes = $scopes;
        $key->save();

        return [
            'id' => $key->id,
            'name' => $key->name,
            'key_prefix' => $key->key_prefix,
            'plaintext_key' => $plaintext,
            'scopes' => $key->scopes,
            'status' => $key->status,
            'created_at' => $key->created_at,
        ];
    }

    /**
     * Revoke (delete) an API key.
     */
    public function revoke(string $userId, int $keyId): void
    {
        $key = ApiKey::query()
            ->where('id', $keyId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $key->delete();
    }
}
