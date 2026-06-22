<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\Node;
use Illuminate\Http\JsonResponse;

final class GeoDNSConfigController
{
    public function show(): JsonResponse
    {
        // 2026-06-22: 单一事实源 — nodes.status 列已 drop，"在线" 用 Node::online() scope 算。
        $resolvers = Node::query()
            ->where('node_type', 'resolver')
            ->online()
            ->select([
                'node_code',
                'region',
                'country',
                'city',
                'public_ipv4',
                'public_ipv6',
                'weight',
            ])
            ->get();

        $geodnsNodes = Node::query()
            ->where('node_type', 'geodns')
            ->online()
            ->whereNotNull('domain')
            ->select(['domain', 'public_ipv4', 'public_ipv6'])
            ->get();

        $domains = $geodnsNodes->pluck('domain')->filter()->unique()->values()->all();

        return response()->json([
            'data' => [
                'resolvers' => $resolvers,
                'domains' => $domains,
                'generated_at' => gmdate(DATE_ATOM),
                'ttl_seconds' => 30,
            ],
        ]);
    }
}
