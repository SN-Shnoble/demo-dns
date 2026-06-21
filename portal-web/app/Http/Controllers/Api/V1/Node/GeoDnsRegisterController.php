<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * GeoDNS 节点安装注册端点（geodns 专属，与 dns-resolver 接口隔离）
 *
 * geodns 执行 `install` 子命令后，会向本端点发起 POST 报告：
 *   - node_id        节点 ID（由 console 预签发）
 *   - installed_at   安装时间（ISO8601）
 *   - listen_addr    HTTP 监听地址
 *
 * 用途：
 *   1) 在 console GeoDNS 节点列表中标记「已注册 / 已安装」
 *   2) **签发并返回 api_key**（明文，仅此一次），节点应缓存到 configs/api_key
 *      之后所有业务请求（heartbeat / config / ...）用 api_key 鉴权。
 */
final class GeoDnsRegisterController
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'node_id' => 'required|string|max:80',
            'installed_at' => 'nullable|date',
            'listen_addr' => 'nullable|string|max:80',
        ]);

        $nodeToken = $request->attributes->get('node_token');
        if (! $nodeToken) {
            return response()->json(['error' => ['code' => 'UNAUTHORIZED', 'message' => 'node token required']], 401);
        }

        $node = Node::query()
            ->where('node_code', $validated['node_id'])
            ->where('node_type', 'geodns')
            ->first();
        if (! $node) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'geodns node not found']], 404);
        }

        $updateData = [
            'last_installed_at' => $validated['installed_at'] ?? now(),
            'last_listen_addr' => $validated['listen_addr'] ?? null,
            'install_status' => 'installed',
        ];

        // 2026-06-21: 同时签发 api_key（防御性：仅当数据库已迁移 api_key 列时）
        $apiKeyPlain = null;
        if (Schema::hasColumn('nodes', 'api_key')) {
            $apiKeyPlain = 'ak_' . Str::random(40);
            $updateData['api_key'] = hash('sha256', $apiKeyPlain);
            $updateData['api_key_issued_at'] = now();
        }

        $node->update($updateData);

        $response = [
            'data' => [
                'node_id' => $node->node_code,
                'install_status' => $node->install_status,
                'last_installed_at' => $node->last_installed_at?->toIso8601String(),
            ],
        ];

        if ($apiKeyPlain !== null) {
            $response['data']['api_key'] = $apiKeyPlain;
            $response['data']['api_key_path'] = 'configs/api_key';
        }

        return response()->json($response);
    }
}
