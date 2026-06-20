<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\QueryLogEntry;
use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminQueryLogController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'nullable|string|max:255',
            'action' => 'nullable|string|max:20',
            'profile_id' => 'nullable|string|max:40',
            'user_id' => 'nullable|integer|min:1',
            'username' => 'nullable|string|max:120',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'export' => 'nullable|boolean',
        ]);

        $query = QueryLogEntry::query();

        if (! empty($validated['domain'])) {
            $query->where('query_name', 'like', '%' . $validated['domain'] . '%');
        }

        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (! empty($validated['profile_id'])) {
            $query->where('profile_id', $validated['profile_id']);
        }

        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        } elseif (! empty($validated['username'])) {
            $uids = \App\Models\User::query()
                ->where('username', 'like', '%' . $validated['username'] . '%')
                ->orWhere('email', 'like', '%' . $validated['username'] . '%')
                ->pluck('uid')
                ->all();
            if (! empty($uids)) {
                $query->whereIn('user_id', $uids);
            } else {
                $query->whereRaw('1=0');
            }
        }

        if (! empty($validated['start_time'])) {
            $query->where('queried_at', '>=', $validated['start_time']);
        }

        if (! empty($validated['end_time'])) {
            $query->where('queried_at', '<=', $validated['end_time']);
        }

        // 导出模式返回全部匹配数据
        if (! empty($validated['export'])) {
            $all = $query->orderByDesc('queried_at')->limit(10000)->get();
            $rows = $this->enrich($all);

            return response()->json(['data' => $rows]);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query->orderByDesc('queried_at')->paginate(min($perPage, 100));
        $rows = $this->enrich(collect($paginator->items()));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }

    /**
     * 批量为日志记录附加 user_name 和 profile_name 字段，避免前端 N+1。
     */
    private function enrich(iterable $entries): array
    {
        $userIds = [];
        $profileIds = [];
        foreach ($entries as $entry) {
            if (! empty($entry->user_id)) $userIds[$entry->user_id] = true;
            if (! empty($entry->profile_id)) $profileIds[$entry->profile_id] = true;
        }
        $userMap = \App\Models\User::query()
            ->whereIn('uid', array_keys($userIds))
            ->get(['uid', 'username', 'email'])
            ->keyBy('uid');
        $profileMap = \App\Models\Profile::query()
            ->whereIn('id', array_keys($profileIds))
            ->get(['id', 'profile_uid', 'name'])
            ->keyBy('id');

        $out = [];
        foreach ($entries as $entry) {
            $row = $entry->toArray();
            $user = ! empty($entry->user_id) ? $userMap->get($entry->user_id) : null;
            $profile = ! empty($entry->profile_id) ? $profileMap->get($entry->profile_id) : null;
            $row['user_name'] = $user?->username;
            $row['user_email'] = $user?->email;
            $row['profile_uid'] = $profile?->profile_uid;
            $row['profile_name'] = $profile?->name;
            $out[] = $row;
        }

        return $out;
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $deleted = QueryLogEntry::whereIn('id', $validated['ids'])->delete();

        AdminAuditLog::record('query_logs.batch_destroy', 'query_log_entry', null, ['count' => $deleted], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $deleted]]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $deleted = QueryLogEntry::query()->delete();

        AdminAuditLog::record('query_logs.clear_all', 'query_log_entry', null, ['count' => $deleted], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $deleted]]);
    }

    /**
     * 后台筛选下拉使用：返回所有 profile 简要信息
     */
    public function profiles(): JsonResponse
    {
        $rows = \App\Models\Profile::query()
            ->orderBy('id')
            ->get(['id', 'profile_uid', 'name', 'user_id'])
            ->map(fn ($p): array => [
                'id' => $p->id,
                'profile_uid' => $p->profile_uid,
                'name' => $p->name,
                'user_id' => $p->user_id,
            ])
            ->all();

        return response()->json(['data' => $rows]);
    }
}
