<?php

declare(strict_types=1);

namespace App\Domain\Publish;

use App\Models\ConfigVersion;
use App\Models\Node;
use App\Models\PublishTask;
use App\Models\TaskExecution;

/**
 * In-process equivalent of the former dns-console-web internal
 * `POST /api/v1/internal/profile-publishes` endpoint.
 *
 * Writes a (config_version, publish_task, task_executions) tuple to the
 * shared portal-web database. Member-side flows (the publish button on
 * a profile) call this directly. There is no HTTP layer and no fallback
 * path: if any write fails, the caller's transaction is rolled back and
 * a 5xx propagates to the user.
 */
final class PublishService
{
    /**
     * @param array<string, mixed> $configJson
     * @return array{publish_id: string, status: string, config_version: int, checksum: string}
     */
    public function recordPublish(
        string $profileId,
        int $profileVersion,
        string $checksum,
        array $configJson,
    ): array {
        $globalVersion = (int) (ConfigVersion::max('version') ?? 0) + 1;
        $encoded = json_encode($configJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException(
                'Failed to encode config JSON for publish: ' . json_last_error_msg(),
            );
        }

        $configVersion = ConfigVersion::create([
            'version' => $globalVersion,
            'profile_id' => $profileId,
            'profile_version' => $profileVersion,
            'user_id' => (string) ($configJson['user_id'] ?? 'unknown'),
            'team_id' => $configJson['team_id'] ?? null,
            'status' => 'ready',
            'checksum' => $checksum,
            'config_json' => $configJson,
            'config_size_bytes' => strlen($encoded),
            'generated_by' => 'portal-web',
            'generated_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $targetNodes = Node::whereNull('disabled_at')->get(['id']);

        $publishTask = PublishTask::create([
            'config_version_id' => $configVersion->id,
            'profile_id' => $profileId,
            'status' => 'queued',
            'target_scope' => 'all_nodes',
            'target_filter' => [],
            'target_node_count' => $targetNodes->count(),
            'applied_node_count' => 0,
            'failed_node_count' => 0,
            'retry_count' => 0,
            'message' => 'Queued for resolver pull',
            'queued_at' => now(),
        ]);

        // Pre-seed one task_execution row per eligible node so the admin
        // publishes view can show per-node progress from t=0. The row
        // is updated when the resolver calls /agent/resolver/config/ack.
        if ($targetNodes->isNotEmpty()) {
            $now = now();
            $rows = $targetNodes->map(fn (Node $node): array => [
                'id' => 'texec_' . substr(hash('sha256', $publishTask->id . $node->id . $globalVersion), 0, 16),
                'publish_task_id' => $publishTask->id,
                'node_id' => $node->id,
                'config_version' => $globalVersion,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            TaskExecution::insert($rows);
        }

        // Bump desired_config_version on every eligible node so the next
        // heartbeat response tells the resolver to pull the new bundle.
        Node::whereNull('disabled_at')->update([
            'desired_config_version' => $globalVersion,
        ]);

        return [
            'publish_id' => $publishTask->id,
            'status' => 'queued',
            'config_version' => (int) $configVersion->version,
            'checksum' => (string) $configVersion->checksum,
        ];
    }
}
