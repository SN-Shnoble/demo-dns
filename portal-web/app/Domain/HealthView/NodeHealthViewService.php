<?php

namespace App\Domain\HealthView;

final class NodeHealthViewService
{
    /**
     * 健康视图：仅暴露 online/offline + 节点拓扑信息
     * 不再输出 qps_1m / capacity_qps / 健康度分等聚合字段
     *
     * @param array<int, array<string, mixed>> $nodes
     * @return array<string, mixed>
     */
    public function build(array $nodes): array
    {
        $items = array_values(array_filter(array_map(
            fn (array $node): ?array => $this->mapNode($node),
            $nodes,
        )));

        return [
            'generated_at' => gmdate(DATE_ATOM),
            'ttl_seconds' => 30,
            'nodes' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>|null
     */
    private function mapNode(array $node): ?array
    {
        // 仅 online 节点进入健康视图
        if (($node['status'] ?? 'offline') !== 'online') {
            return null;
        }

        return [
            'node_id' => $node['id'],
            'region' => $node['region'],
            'country' => $node['country'] ?? null,
            'city' => $node['city'] ?? null,
            'status' => $node['status'],
            'public_ipv4' => $node['public_ipv4'] ?? null,
            'public_ipv6' => $node['public_ipv6'] ?? null,
            'supported_protocols' => $node['supported_protocols'] ?? [],
            'weight' => (int) ($node['weight'] ?? 100),
            'last_heartbeat_at' => $node['last_heartbeat_at'] ?? null,
        ];
    }
}
