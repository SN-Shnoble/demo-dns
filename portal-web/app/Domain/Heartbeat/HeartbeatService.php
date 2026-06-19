<?php

namespace App\Domain\Heartbeat;

final class HeartbeatService
{
    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';

    /**
     * 评估心跳返回的 envelope 数据。
     *
     * 在线/离线只取决于 `last_heartbeat_at` 是否在超时窗口内：
     *   - `now - last_heartbeat_at <= threshold`  → online
     *   - 否则                                    → offline
     * 不再基于 qps/cpu/memory/error_count 等做"健康度"判断。
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
     * 仅根据心跳的到达事实返回 online/offline。
     * 当前心跳处理器只在收到请求时调用此方法，因此心跳成功接收即视为 online；
     * 超时由 HeartbeatTimeoutJob / 控制台定时任务把 nodes.status 标为 offline。
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

    /**
     * 控制台定时任务调用：节点最后心跳超过阈值时返回 offline，否则 online。
     */
    public function deriveOfflineStatusFromLastHeartbeat(?\DateTimeInterface $lastHeartbeatAt, int $thresholdSeconds = 90): string
    {
        if ($lastHeartbeatAt === null) {
            return self::STATUS_OFFLINE;
        }
        $age = max(0, time() - $lastHeartbeatAt->getTimestamp());

        return $age <= $thresholdSeconds ? self::STATUS_ONLINE : self::STATUS_OFFLINE;
    }
}
