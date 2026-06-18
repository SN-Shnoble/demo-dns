<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\RuleSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminRuleController
{
    public function index(Request $request): JsonResponse
    {
        $query = RuleSource::query();

        if ($request->filled('type')) {
            $query->where('type', (string) $request->input('type'));
        }

        if ($request->filled('enabled')) {
            $query->where('enabled', filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN));
        }

        $sources = $query->orderBy('name')->get()->toArray();

        return response()->json([
            'data' => $sources,
            'meta' => [
                'total' => count($sources),
                'enabled' => count(array_filter($sources, fn (array $s): bool => (bool) $s['enabled'])),
                'last_sync_at' => collect($sources)->max('last_synced_at'),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => RuleSource::query()->findOrFail($id)->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->id;
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => ['required', Rule::in(['domain_list', 'adblock', 'hosts', 'rpz'])],
            'url' => 'required|string|url|max:500',
            'enabled' => 'boolean',
        ]);

        $source = RuleSource::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'url' => $validated['url'],
            'enabled' => $validated['enabled'] ?? true,
        ]);

        AdminAuditLog::record('rule.create', 'rule_source', $source->id, $source->toArray(), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $source->toArray()], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->id;
        $source = RuleSource::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:100',
            'type' => ['string', Rule::in(['domain_list', 'adblock', 'hosts', 'rpz'])],
            'url' => 'string|url|max:500',
            'enabled' => 'boolean',
        ]);

        $source->update($validated);

        AdminAuditLog::record('rule.update', 'rule_source', $id, $source->toArray(), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $source->fresh()->toArray()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->id;
        $source = RuleSource::findOrFail($id);
        $source->delete();

        AdminAuditLog::record('rule.delete', 'rule_source', $id, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    public function sync(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->id;
        $source = RuleSource::findOrFail($id);
        $source->update([
            'last_sync_status' => 'pending',
            'last_synced_at' => now(),
            'last_sync_message' => 'Sync requested by admin',
        ]);

        AdminAuditLog::record('rule.sync', 'rule_source', $id, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json([
            'data' => [
                'id' => $id,
                'status' => 'pending',
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $actorId = $request->user()?->id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $count = RuleSource::whereIn('id', $validated['ids'])->delete();

        AdminAuditLog::record('rule.batch_delete', 'rule_source', null, ['ids' => $validated['ids'], 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $count]]);
    }
}
