<?php

namespace App\Http\Controllers\Api\V1\Node;

use App\Domain\ConfigVersion\ConfigBuildService;
use App\Domain\ConfigVersion\ChecksumService;
use App\Models\ConfigVersion;
use App\Models\Node;
use App\Models\Profile;
use App\Models\ProfileVersion;
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
            ->where(function ($query) use ($node): void {
                $query
                    ->whereHas('publishTasks.executions', function ($executionQuery) use ($node): void {
                        $executionQuery->where('node_id', $node->id);
                    })
                    ->orWhereHas('publishTasks', function ($taskQuery) use ($node): void {
                        $taskQuery
                            ->whereIn('status', ['queued', 'running', 'succeeded', 'partial'])
                            ->where(function ($targetQuery) use ($node): void {
                                $targetQuery
                                    ->where('target_scope', 'all_nodes')
                                    ->orWhere(function ($specificNodeQuery) use ($node): void {
                                        $specificNodeQuery
                                            ->where('target_scope', 'specific_nodes')
                                            ->whereJsonContains('target_filter->node_ids', $node->id);
                                    });
                            });
                    });
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

        // Read raw config_json bypassing Eloquent's `array` cast so the
        // nested `quota: {}` object is preserved as stdClass instead of
        // collapsing to []. The resolver expects quota as map[string]any.
        $rawConfigJson = $configVersion->getRawOriginal('config_json');
        $singleConfig = is_string($rawConfigJson)
            ? json_decode($rawConfigJson, true)
            : (array) $rawConfigJson;
        if (is_array($singleConfig) && array_key_exists('quota', $singleConfig)) {
            $singleConfig['quota'] = (object) $singleConfig['quota'];
        } else {
            $singleConfig['quota'] = (object) [];
        }
        // Backfill rule_id to string for legacy bundles (resolver expects string type).
        if (is_array($singleConfig) && isset($singleConfig['rules']) && is_array($singleConfig['rules'])) {
            foreach ($singleConfig['rules'] as $i => $r) {
                if (is_array($r) && array_key_exists('rule_id', $r)) {
                    $singleConfig['rules'][$i]['rule_id'] = (string) $r['rule_id'];
                }
            }
        }

        // 聚合所有活跃 Profile 的最新配置，确保 resolver 收到完整的多租户配置
        $allProfiles = [];
        $latestVersions = ProfileVersion::whereIn('profile_id', Profile::where('status', 'active')->pluck('id'))
            ->where('status', 'published')
            ->orderByDesc('version')
            ->get()
            ->groupBy('profile_id');

        foreach ($latestVersions as $profileId => $versions) {
            $pvConfig = $versions->first()->config_json;
            if (is_array($pvConfig)) {
                // 规范化 quota 对象
                if (array_key_exists('quota', $pvConfig)) {
                    $pvConfig['quota'] = (object) $pvConfig['quota'];
                } else {
                    $pvConfig['quota'] = (object) [];
                }
                $allProfiles[] = $pvConfig;
            }
        }

        // 如果已发布的配置不在 latestVersions 中，也追加进去
        $singleProfileId = $singleConfig['profile_id'] ?? null;
        $alreadyIncluded = false;
        foreach ($allProfiles as $p) {
            if (($p['profile_id'] ?? null) === $singleProfileId) {
                $alreadyIncluded = true;
                break;
            }
        }
        if (!$alreadyIncluded) {
            $allProfiles[] = $singleConfig;
        }

        $profileVersion = (int) ($singleConfig['version'] ?? $configVersion->version);
        $bundle = $service->buildBundle(
            [
                'profile_version' => $profileVersion,
                'config_json' => $singleConfig,
                'all_profiles' => $allProfiles,
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
