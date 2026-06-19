<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\Device;
use App\Models\Node;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceSeenController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile_id' => 'required|string|max:36',
            'device_id' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:100',
            'protocol' => 'nullable|string|max:50',
            'client_ip' => 'nullable|string|max:45',
        ]);

        /** @var Node $node */
        $node = $request->attributes->get('node');

        // Resolve user_id from the profile that the device belongs to
        $profile = Profile::findOrFail($validated['profile_id']);
        $userId = $profile->user_id;

        $deviceId = $validated['device_id'] ?? ('dev_' . md5($validated['profile_id'] . '_' . ($validated['client_ip'] ?? $request->ip())));

        $device = Device::firstOrCreate(
            ['id' => $deviceId],
            [
                'user_id' => $userId,
                'profile_id' => $validated['profile_id'],
                'name' => $validated['device_name'] ?? ('Unknown (' . substr($deviceId, 0, 12) . ')'),
                'device_type' => $validated['protocol'] ?? 'unknown',
                'device_id' => $validated['device_id'] ?? null,
                'public_ip' => $validated['client_ip'] ?? $request->ip(),
                'last_seen_at' => now(),
            ]
        );

        if ($device->wasRecentlyCreated === false) {
            $device->update([
                'last_seen_at' => now(),
                'public_ip' => $validated['client_ip'] ?? $request->ip(),
                'device_type' => $validated['protocol'] ?? $device->device_type,
            ]);
        }

        return response()->json([
            'data' => [
                'device_id' => $device->id,
                'created' => $device->wasRecentlyCreated,
            ],
        ]);
    }
}