<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminProtectionPolicyController
{
    /**
     * 防护策略分组在 system_configs 中的 key 前缀
     */
    private const GROUP_KEY = 'protection';

    public function show(): JsonResponse
    {
        $row = SystemConfig::where('config_key', self::GROUP_KEY)->first();
        $value = $row?->config_value ?? $this->defaultPolicies();

        return response()->json(['data' => $this->normalize($value)]);
    }

    public function update(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $payload = $request->validate([
            'dns_rebind' => 'array',
            'dns_rebind.enabled' => 'boolean',
            'dns_rebind.whitelist' => 'array',
            'dns_rebind.whitelist.*' => 'string|max:255',
            'idn' => 'array',
            'idn.enabled' => 'boolean',
            'typo' => 'array',
            'typo.enabled' => 'boolean',
            'typo.threshold' => 'integer|min:1|max:2',
            'dga' => 'array',
            'dga.enabled' => 'boolean',
            'dga.entropy_threshold' => 'numeric|min:3.0|max:5.5',
            'dga.digit_ratio' => 'numeric|min:0|max:1',
            'categories' => 'array',
            'categories.*' => 'array',
            'categories.*.enabled' => 'boolean',
        ]);

        $row = SystemConfig::updateOrCreate(
            ['config_key' => self::GROUP_KEY],
            [
                'config_value' => $payload,
                'description' => 'Protection policies (DNS rebinding / IDN / typo / DGA / categories)',
                'is_secret' => false,
            ]
        );

        AdminAuditLog::record('protection.update', 'system_config', $row->id, $payload, $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->normalize($payload)]);
    }

    public function export(): JsonResponse
    {
        $row = SystemConfig::where('config_key', self::GROUP_KEY)->first();

        return response()->json([
            'data' => [
                'exported_at' => now()->toIso8601String(),
                'config' => $row?->config_value ?? $this->defaultPolicies(),
            ],
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'config' => 'required|array',
        ]);

        $row = SystemConfig::updateOrCreate(
            ['config_key' => self::GROUP_KEY],
            [
                'config_value' => $validated['config'],
                'description' => 'Protection policies (imported)',
                'is_secret' => false,
            ]
        );

        AdminAuditLog::record('protection.import', 'system_config', $row->id, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['imported' => true]]);
    }

    private function normalize(array $value): array
    {
        $defaults = $this->defaultPolicies();
        return [
            'dns_rebind' => array_merge($defaults['dns_rebind'], $value['dns_rebind'] ?? []),
            'idn'        => array_merge($defaults['idn'],        $value['idn'] ?? []),
            'typo'       => array_merge($defaults['typo'],       $value['typo'] ?? []),
            'dga'        => array_merge($defaults['dga'],        $value['dga'] ?? []),
            'categories' => $value['categories'] ?? $defaults['categories'],
        ];
    }

    private function defaultPolicies(): array
    {
        return [
            'dns_rebind' => [
                'enabled' => true,
                'whitelist' => ['localhost', '*.local'],
            ],
            'idn' => [
                'enabled' => true,
            ],
            'typo' => [
                'enabled' => true,
                'threshold' => 1,
            ],
            'dga' => [
                'enabled' => true,
                'entropy_threshold' => 4.2,
                'digit_ratio' => 0.6,
            ],
            'categories' => [
                'malware'       => ['enabled' => true],
                'phishing'      => ['enabled' => true],
                'cryptojacking' => ['enabled' => true],
                'dynamic_dns'   => ['enabled' => false],
                'parked'        => ['enabled' => true],
                'typosquatting' => ['enabled' => true],
                'dga'           => ['enabled' => true],
                'new_domain'    => ['enabled' => false, 'threshold_days' => 30],
                'tracker'       => ['enabled' => true],
                'analytics'     => ['enabled' => true],
                'telemetry'     => ['enabled' => true],
                'ads'           => ['enabled' => false],
                'adult'         => ['enabled' => false],
                'gambling'      => ['enabled' => false],
            ],
        ];
    }
}
