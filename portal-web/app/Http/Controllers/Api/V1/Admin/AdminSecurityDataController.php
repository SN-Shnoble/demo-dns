<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 安全数据管理（DDNS 提供商 / 停放域名 / TLD 黑名单 / 白名单 / 黑名单）
 * 数据存储在统一的 system_configs 表，config_key = 'security_data.<group>'
 */
final class AdminSecurityDataController
{
    private const GROUPS = [
        'dynamic-dns'    => 'DDNS 提供商域名列表',
        'parked-domains' => '停放域名 / Parking 服务商',
        'tld-blacklist'  => 'TLD 黑名单',
        'allow-lists'    => '白名单域名',
        'block-lists'    => '黑名单域名',
    ];

    public function index(string $group): JsonResponse
    {
        $this->validateGroup($group);
        $rows = DB::table('security_data_items')
            ->where('group_code', $group)
            ->orderBy('value')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return response()->json(['data' => $rows, 'meta' => ['group' => $group, 'total' => count($rows)]]);
    }

    public function store(Request $request, string $group): JsonResponse
    {
        $this->validateGroup($group);
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'value' => 'required|string|max:255',
            'note' => 'nullable|string|max:500',
            'enabled' => 'boolean',
        ]);

        $id = DB::table('security_data_items')->insertGetId([
            'group_code' => $group,
            'value' => $validated['value'],
            'note' => $validated['note'] ?? null,
            'enabled' => $validated['enabled'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AdminAuditLog::record('security_data.create', 'security_data', $id, ['group' => $group, 'value' => $validated['value']], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => array_merge($validated, ['id' => $id, 'group_code' => $group])], 201);
    }

    public function destroy(Request $request, string $group, string $id): JsonResponse
    {
        $this->validateGroup($group);
        $actorId = $request->user()?->admin_id;
        $count = DB::table('security_data_items')->where('id', $id)->where('group_code', $group)->delete();

        AdminAuditLog::record('security_data.delete', 'security_data', $id, ['group' => $group], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $id, 'deleted' => $count > 0]]);
    }

    public function batchImport(Request $request, string $group): JsonResponse
    {
        $this->validateGroup($group);
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'values' => 'required|array|min:1',
            'values.*' => 'string|max:255',
        ]);

        $now = now();
        $imported = 0;
        foreach ($validated['values'] as $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            DB::table('security_data_items')->updateOrInsert(
                ['group_code' => $group, 'value' => $value],
                ['enabled' => true, 'note' => null, 'created_at' => $now, 'updated_at' => $now]
            );
            $imported++;
        }

        AdminAuditLog::record('security_data.import', 'security_data', null, ['group' => $group, 'imported' => $imported], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['imported' => $imported]]);
    }

    public function summary(): JsonResponse
    {
        $summary = [];
        foreach (array_keys(self::GROUPS) as $group) {
            $summary[$group] = DB::table('security_data_items')->where('group_code', $group)->count();
        }

        return response()->json(['data' => $summary]);
    }

    private function validateGroup(string $group): void
    {
        if (! array_key_exists($group, self::GROUPS)) {
            abort(404, 'Unknown security data group: ' . $group);
        }
    }
}
