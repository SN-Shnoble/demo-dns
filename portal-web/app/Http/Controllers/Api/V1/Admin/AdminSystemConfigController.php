<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminSystemConfigController
{
    public function show(): JsonResponse
    {
        $rows = SystemConfig::query()->get();
        $payload = [];
        foreach ($rows as $row) {
            $payload[$row->key] = $this->isSensitiveKey($row->key)
                ? $this->maskConfigValue($row->value)
                : $row->value;
        }

        return response()->json([
            'data' => $payload,
            'meta' => [
                'keys' => array_keys($payload),
                'updated_at' => $rows->max('updated_at'),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'configs' => 'required|array',
        ]);

        $actorId = $request->user()?->id ?? 'system';
        $updated = [];
        foreach ($validated['configs'] as $key => $value) {
            $keyStr = (string) $key;
            SystemConfig::updateOrCreate(
                ['key' => $keyStr],
                ['value' => $value, 'updated_by' => $actorId],
            );
            $updated[] = $keyStr;
        }

        AdminAuditLog::record(
            action: 'system_config.update',
            targetType: 'system_config',
            targetId: null,
            payload: ['updated_keys' => $updated],
            actorId: $actorId,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['data' => ['updated' => $updated]]);
    }

    private function isSensitiveKey(string $key): bool
    {
        return str_contains($key, 'password')
            || str_contains($key, 'secret')
            || str_contains($key, 'merchant_key')
            || str_contains($key, 'token');
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function maskConfigValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value === '' ? '' : '********';
        }

        if (! is_array($value)) {
            return $value;
        }

        $masked = [];
        foreach ($value as $key => $item) {
            $masked[$key] = $this->isSensitiveKey((string) $key)
                ? (is_string($item) && $item !== '' ? '********' : $item)
                : $item;
        }

        return $masked;
    }
}
