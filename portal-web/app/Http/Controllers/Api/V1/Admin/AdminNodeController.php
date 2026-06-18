<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\Node;
use App\Models\NodeToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class AdminNodeController
{
    public function index(): JsonResponse
    {
        $nodes = Node::query()->orderBy('node_name')->get()->toArray();
        $totalNodes = count($nodes);
        // 节点只关心 online / offline：pending/disabled 视作离线
        $onlineNodes = count(array_filter($nodes, fn (array $node): bool => $node['status'] === 'online'));

        return response()->json([
            'data' => $nodes,
            'meta' => [
                'total' => $totalNodes,
                'online' => $onlineNodes,
                'offline' => max($totalNodes - $onlineNodes, 0),
            ],
        ]);
    }

    public function show(string $nodeId): JsonResponse
    {
        $node = Node::query()->findOrFail($nodeId);
        $tokens = NodeToken::where('node_id', $nodeId)->orderByDesc('created_at')->get()->map(fn (NodeToken $token): array => [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => optional($token->last_used_at)?->toIso8601String(),
            'expires_at' => optional($token->expires_at)?->toIso8601String(),
            'revoked_at' => optional($token->revoked_at)?->toIso8601String(),
            'created_at' => optional($token->created_at)?->toIso8601String(),
        ])->all();

        $row = $node->toArray();
        $row['tokens'] = $tokens;

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->id;
        $validated = $request->validate([
            'node_name' => 'required|string|max:100|unique:nodes,node_name',
            'region' => 'required|string|max:80',
            'country' => 'nullable|string|size:2',
            'city' => 'nullable|string|max:100',
            'provider' => 'nullable|string|max:80',
            'public_ipv4' => 'nullable|string|max:45',
            'public_ipv6' => 'nullable|string|max:45',
            'hostname' => 'nullable|string|max:255',
            'supported_protocols' => 'array',
            'supported_protocols.*' => 'string',
            'weight' => 'integer|min:0|max:10000',
            'capacity_qps' => 'integer|min:0',
            'labels' => 'array',
        ]);

        $node = Node::create(array_merge($validated, [
            'id' => 'node_' . Str::lower(Str::random(16)),
            'status' => 'pending',
            'current_config_version' => 0,
            'desired_config_version' => 0,
        ]));

        AdminAuditLog::record('node.create', 'node', $node->id, $node->toArray(), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $node->toArray()], 201);
    }

    public function update(Request $request, string $nodeId): JsonResponse
    {
        $actorId = $request->user()?->id;
        $node = Node::query()->findOrFail($nodeId);

        $validated = $request->validate([
            'node_name' => 'string|max:100|unique:nodes,node_name,' . $nodeId,
            'region' => 'string|max:80',
            'country' => 'nullable|string|size:2',
            'city' => 'nullable|string|max:100',
            'provider' => 'nullable|string|max:80',
            'public_ipv4' => 'nullable|string|max:45',
            'public_ipv6' => 'nullable|string|max:45',
            'hostname' => 'nullable|string|max:255',
            'supported_protocols' => 'array',
            'supported_protocols.*' => 'string',
            'weight' => 'integer|min:0|max:10000',
            'capacity_qps' => 'integer|min:0',
            'labels' => 'array',
        ]);

        $node->update($validated);

        AdminAuditLog::record('node.update', 'node', $nodeId, $validated, $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $node->fresh()->toArray()]);
    }

    public function destroy(Request $request, string $nodeId): JsonResponse
    {
        $actorId = $request->user()?->id;
        $node = Node::query()->findOrFail($nodeId);
        $node->delete();

        AdminAuditLog::record('node.delete', 'node', $nodeId, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $nodeId, 'deleted' => true]]);
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $actorId = $request->user()?->id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $count = Node::whereIn('id', $validated['ids'])->delete();

        AdminAuditLog::record('node.batch_delete', 'node', null, ['ids' => $validated['ids'], 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $count]]);
    }

    public function enable(Request $request, string $nodeId): JsonResponse
    {
        $actorId = $request->user()?->id;
        $node = Node::query()->findOrFail($nodeId);
        $node->update(['disabled_at' => null, 'status' => 'online']);

        AdminAuditLog::record('node.enable', 'node', $nodeId, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $node->fresh()->toArray()]);
    }

    public function disable(Request $request, string $nodeId): JsonResponse
    {
        $actorId = $request->user()?->id;
        $node = Node::query()->findOrFail($nodeId);
        $node->update(['disabled_at' => now(), 'status' => 'disabled']);

        AdminAuditLog::record('node.disable', 'node', $nodeId, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $node->fresh()->toArray()]);
    }

    public function issueToken(Request $request, string $nodeId): JsonResponse
    {
        $actorId = $request->user()?->id;
        $node = Node::query()->findOrFail($nodeId);

        $validated = $request->validate([
            'name' => 'string|max:100',
            'expires_in_days' => 'integer|min:1|max:3650',
        ]);

        // 预签发 (api_key, secret) 三元组：明文仅返回一次，服务端只存 sha256
        // 同节点同名 token 已存在时，自动重新生成（覆盖旧凭据）
        $plainToken = Str::random(32);
        $plainSecret = 'sk_' . bin2hex(random_bytes(32));

        $token = NodeToken::updateOrCreate(
            [
                'node_id' => $node->id,
                'name' => $validated['name'] ?? 'default',
            ],
            [
                'id' => 'ntk_' . bin2hex(random_bytes(8)),
                'token_hash' => hash('sha256', $plainToken),
                'hmac_key_hash' => hash('sha256', $plainSecret),
                'hmac_secret_encrypted' => Crypt::encryptString($plainSecret),
                'expires_at' => isset($validated['expires_in_days']) ? now()->addDays((int) $validated['expires_in_days']) : null,
                'created_at' => now(),
            ]
        );

        AdminAuditLog::record('node.token_issue', 'node_token', $token->id, ['node_id' => $nodeId], $actorId, null, $request->ip(), $request->userAgent());

        // 明文仅返回一次，运维需用 `resolver install` 写入目标机 configs/server.yaml
        // Cache-Control: no-store 确保浏览器/中间代理不缓存明文凭据。
        return response()->json([
            'data' => [
                'id' => $token->id,
                'name' => $token->name,
                'node_id' => $node->id,
                'api_key' => $plainToken,
                'secret' => $plainSecret,
                'expires_at' => optional($token->expires_at)?->toIso8601String(),
            ],
        ], 201)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }

    public function revokeToken(Request $request, string $nodeId, string $tokenId): JsonResponse
    {
        $actorId = $request->user()?->id;
        $token = NodeToken::where('node_id', $nodeId)->where('id', $tokenId)->firstOrFail();
        $token->update(['revoked_at' => now()]);

        AdminAuditLog::record('node.token_revoke', 'node_token', $tokenId, ['node_id' => $nodeId], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $tokenId, 'revoked' => true]]);
    }
}
