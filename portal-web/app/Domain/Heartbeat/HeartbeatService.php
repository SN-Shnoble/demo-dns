<?php

namespace App\Domain\Heartbeat;

final class HeartbeatService
{
    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';

    /**
     * 评估心跳返回的 envelope 数据。
     *
     * envelope 里的 `node_status` 字段是「agent 自己报告的状态」，
     * 控制平面的"是否在线"以 last_heartbeat_at + 阈值算（见 Node::isOnline()）。
     *
     * @param array<string, mixed> $heartbeat
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    public function evaluate(array $heartbeat, array $node): array
    {
        $latestConfigVersion = (int) ($node['desired_config_version'] ?? 0);
        $currentConfigVersion = (int) ($heartbeat['current_config_version'] ?? 0);

        return [
            'ok' => true,
            'server_time' => gmdate(DATE_ATOM),
            'node_status' => $this->computeStatus($heartbeat),
            'latest_config_version' => $latestConfigVersion,
            'should_pull_config' => $latestConfigVersion > $currentConfigVersion,
            'config_endpoint' => '/api/v1/node/resolver/config',
            'next_heartbeat_after_seconds' => 30,
        ];
    }

    /**
     * 仅根据本帧心跳 payload 返回 agent 自报的 online/offline。
     * 控制平面的"是否在线"统一由 Node::isOnline() 现算。
     *
     * @param array<string, mixed> $heartbeat
     */
    public function computeStatus(array $heartbeat): string
    {
        $status = (string) ($heartbeat['status'] ?? '');

        return $status === self::STATUS_ONLINE || $status === ''
            ? self::STATUS_ONLINE
            : self::STATUS_OFFLINE;
    }
}
