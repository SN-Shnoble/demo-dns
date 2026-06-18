<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Domain\ConfigVersion\ConfigBuildService;
use App\Domain\ConfigVersion\ChecksumService;
use App\Models\ConfigVersion;
use App\Models\Node;
use App\Models\PublishTask;
use App\Models\TaskExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConfigPullController
{
    public function show(Request $request): JsonResponse|Response
    {
        /** @var Node $node */
        $node = $request->attributes->get('node');
        $service = new ConfigBuildService(new ChecksumService());
        $currentVersion = (int) $request->integer('current_version', $node->current_config_version);

        // Resolve the config version targeted for this node via publish tasks,
        // rather than picking the globally latest version. This ensures
        // multi-tenant isolation and supports gradual rollout.
        $configVersion = ConfigVersion::query()
            ->whereHas('publishTasks.taskExecutions', function ($q) use ($node): void {
                $q->where('node_id', $node->id);
            })
            ->orWhereHas('publishTasks', function ($q) use ($node): void {
                // Also match publish tasks that target this node's region
                // or have no specific target (global fallback).
                $q->where(function ($sq) use ($node): void {
                    $sq->where('target_node_id', $node->id)
                        ->orWhereNull('target_node_id');
                })->where('status', 'in_progress');
            })
            ->orderByDesc('version')
            ->first();

        // Fallback to latest version if no targeted task found.
        if ($configVersion === null) {
            $configVersion = ConfigVersion::query()->orderByDesc('version')->first();
        }

        if ($configVersion === null) {
            return response()->noContent();
        }

        if ($configVersion->version <= $currentVersion) {
            return response()->noContent();
        }

        $bundle = $service->buildBundle(
            [
                'profile_version' => $configVersion->version,
                'config_json' => $configVersion->config_json,
            ],
            [$this->defaultUpstream()],
        );

        $publishTask = PublishTask::where('config_version_id', $configVersion->id)
            ->latest('queued_at')
            ->first();

        if ($publishTask !== null) {
            TaskExecution::updateOrCreate(
                [
                    'publish_task_id' => $publishTask->id,
                    'node_id' => $node->id,
                ],
                [
                    'config_version' => $configVersion->version,
                    'status' => 'pulled',
                    'checksum' => $bundle['checksum'],
                    'pulled_at' => now(),
                    'last_seen_at' => now(),
                ],
            );
        }

        return response()->json([
            'data' => $bundle,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultUpstream(): array
    {
        return [
            'address' => config('dns.default_upstream', '1.1.1.1:53'),
            'protocol' => 'udp',
            'timeout' => '1500ms',
        ];
    }
}
