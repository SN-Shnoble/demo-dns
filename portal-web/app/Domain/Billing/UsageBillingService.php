<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Infrastructure\ClickHouse\ClickHouseClient;
use App\Models\JobExecution;
use App\Domain\Jobs\JobRunner;
use Illuminate\Support\Facades\DB;

/**
 * UI.md #67/#70 — Usage 聚合 + 账单生成。
 *
 * 1) Usage Aggregation: ClickHouse usage_events → PostgreSQL usage_records
 * 2) Billing Generation: usage_records (按 period) → invoices(billing_type=usage)
 *
 * 当前实现：聚合逻辑 + 账单生成，使用 JobRunner 包裹 + 失败告警。
 * ClickHouse 客户端通过 Infrastructure\ClickHouse\ClickHouseClient 注入。
 */
final class UsageBillingService
{
    public function __construct(
        private readonly ?ClickHouseClient $clickhouseClient = new ClickHouseClient(),
    ) {
        // 默认实例化 ClickHouseClient（与 ClickHouseStatsService 保持一致），
        // 避免 DI 未注入时 fetchUsageEvents 抛 "ClickHouse client not configured" 永久失败。
    }

    /**
     * 每 5 分钟调用：拉取 ClickHouse usage_events，按
     * (user_id, profile_id, device_id, billing_category, period) 聚合写入 usage_records。
     */
    public function aggregateOnce(?string $since = null): array
    {
        return JobRunner::run('usage_aggregation', function () use ($since) {
            // 实际 schema：dns_aggregation_offsets(id, topic, window_start, processed_at, record_count, status, error_message, ...)
            $offset = DB::table('aggregation_offsets')
                ->where('topic', 'usage_aggregation')
                ->orderByDesc('id')
                ->first();
            $sinceIso = $since ?? $offset?->processed_at;
            $events = $this->fetchUsageEvents($sinceIso);

            // 2026-06-22: 预加载有效 profile_id 集合，孤儿事件（如节点已删/uid 改写）直接 skip，
            // 避免 MySQL FK 约束 1452 导致整批回滚 → 聚合永远卡住。
            $profileIds = array_values(array_unique(array_filter(array_map(
                static fn (array $e) => (int) ($e['profile_id'] ?? 0),
                $events
            ))));
            $validProfileIds = $profileIds === []
                ? []
                : DB::table('profiles')->whereIn('id', $profileIds)->pluck('id')->all();
            $validProfileSet = array_flip(array_map('intval', $validProfileIds));
            $skippedOrphans = 0;

            $buckets = [];
            foreach ($events as $e) {
                $pid = (int) ($e['profile_id'] ?? 0);
                if ($pid <= 0 || ! isset($validProfileSet[$pid])) {
                    $skippedOrphans++;
                    continue;
                }
                $key = sprintf(
                    '%s|%s|%s|%s',
                    $e['user_id'],
                    $e['profile_id'],
                    $e['device_id'],
                    $e['billing_category'] ?? 'normal_query',
                );
                $buckets[$key] = ($buckets[$key] ?? 0) + 1;
            }
            $now = now();
            foreach ($buckets as $key => $count) {
                [$userId, $profileId, $deviceId, $category] = explode('|', $key);
                $period = $this->ensureOpenPeriod($userId);
                // 实际 schema：dns_usage_records 无 plan_code 列；user_id/profile_id/billing_period_id 必填
                // 同样不能 UPDATE SET 引用同表列，先 PHP 端按唯一键查找决定 created_at
                $existingUsage = DB::table('usage_records')
                    ->where('user_id', $userId)
                    ->where('profile_id', $profileId)
                    ->where('device_id', $deviceId !== '' ? $deviceId : null)
                    ->where('billing_category', $category)
                    ->where('billing_period_id', $period->id)
                    ->first();
                $usagePayload = [
                    'query_count' => (int) ($existingUsage->query_count ?? 0) + (int) $count,
                    'amount_minor' => (int) ($existingUsage->amount_minor ?? 0),
                    'last_aggregated_at' => $now,
                    'updated_at' => $now,
                ];
                if ($existingUsage === null) {
                    $usagePayload['user_id'] = $userId;
                    $usagePayload['profile_id'] = $profileId;
                    $usagePayload['device_id'] = $deviceId !== '' ? $deviceId : null;
                    $usagePayload['billing_category'] = $category;
                    $usagePayload['billing_period_id'] = $period->id;
                    $usagePayload['created_at'] = $now;
                    DB::table('usage_records')->insert($usagePayload);
                } else {
                    DB::table('usage_records')
                        ->where('id', $existingUsage->id)
                        ->update($usagePayload);
                }
            }

            // 写回 offset：以 topic + window_start(now) 唯一键写一条 done 记录
            // MySQL 限制：UPDATE 的 SET 不能引用同表列，因此不在 SQL 内做 COALESCE(created_at)
            $existingOffset = DB::table('aggregation_offsets')
                ->where('topic', 'usage_aggregation')
                ->where('window_start', $now)
                ->first();
            $offsetPayload = [
                'topic' => 'usage_aggregation',
                'window_start' => $now,
                'processed_at' => $now,
                'record_count' => count($events),
                'status' => 'done',
                'updated_at' => $now,
            ];
            if ($existingOffset === null) {
                $offsetPayload['created_at'] = $now;
                DB::table('aggregation_offsets')->insert($offsetPayload);
            } else {
                DB::table('aggregation_offsets')
                    ->where('id', $existingOffset->id)
                    ->update($offsetPayload);
            }
            return ['buckets' => count($buckets), 'events' => count($events), 'skipped_orphans' => $skippedOrphans];
        });
    }

    /**
     * 把已关闭的 billing_period 内 usage_records 生成 usage 类型账单。
     */
    public function generateBillingsForClosedPeriods(): array
    {
        return JobRunner::run('billing_generation', function () {
            $periods = DB::table('billing_periods')
                ->where('status', 'closed')
                ->whereNull('billing_id')
                ->get();
            $generated = 0;
            foreach ($periods as $period) {
                DB::transaction(function () use ($period, &$generated) {
                    $records = DB::table('usage_records')
                        ->where('billing_period_id', $period->id)
                        ->get();
                    $totalMinor = 0;
                    foreach ($records as $r) {
                        $totalMinor += $this->priceFor($r->billing_category, (int) $r->query_count);
                    }
                    $billingNo = 'BIL-' . now()->format('YmdHis') . '-' . str_pad((string) $period->id, 6, '0', STR_PAD_LEFT);
                    $billingId = DB::table('billings')->insertGetId([
                        'billing_no' => $billingNo,
                        'user_id' => $period->user_id,
                        'currency' => 'USD',
                        'subtotal_minor' => $totalMinor,
                        'discount_minor' => 0,
                        'tax_minor' => 0,
                        'total_minor' => $totalMinor,
                        'status' => 'pending',
                        'issued_at' => now(),
                        'billing_period_id' => $period->id,
                        'meta' => json_encode(['kind' => 'usage'], JSON_UNESCAPED_UNICODE),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    foreach ($records as $r) {
                        $amount = $this->priceFor($r->billing_category, (int) $r->query_count);
                        DB::table('billing_items')->insert([
                            'billing_id' => $billingId,
                            'item_type' => 'usage',
                            'source_type' => 'usage_record',
                            'source_id' => $r->id,
                            'description' => sprintf('DNS usage (%s) %d queries', $r->billing_category, $r->query_count),
                            'quantity' => $r->query_count,
                            'unit_price_minor' => $this->unitPrice($r->billing_category),
                            'amount_minor' => $amount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    DB::table('billing_periods')->where('id', $period->id)->update([
                        'status' => 'billed',
                        'billing_id' => $billingId,
                        'updated_at' => now(),
                    ]);
                    $generated++;
                });
            }
            return ['generated' => $generated];
        });
    }

    private function ensureOpenPeriod(string $userId): object
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $row = DB::table('billing_periods')
            ->where('user_id', $userId)
            ->where('period_start', $monthStart)
            ->where('status', 'open')
            ->first();
        if ($row) {
            return $row;
        }
        $id = DB::table('billing_periods')->insertGetId([
            'user_id' => $userId,
            'period_start' => $monthStart,
            'period_end' => $monthEnd,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return DB::table('billing_periods')->where('id', $id)->first();
    }

    /**
     * 简化定价：normal_query=0, encrypted_dns=1 分/千次, dnssec=2 分/千次。
     */
    private function priceFor(string $category, int $count): int
    {
        $unit = $this->unitPrice($category);
        return (int) round($count * $unit / 1000);
    }

    private function unitPrice(string $category): int
    {
        return match ($category) {
            'encrypted_dns' => 1,
            'dnssec' => 2,
            default => 0,
        };
    }

    /**
     * 抽象 ClickHouse 拉取：依赖未注入时直接失败（避免账单为 0）。
     */
    private function fetchUsageEvents(?string $sinceIso): array
    {
        if ($this->clickhouseClient === null) {
            // 关键路径：ClickHouse 未配置 → 必须显式失败，不能聚合为 0。
            throw new \RuntimeException(
                'ClickHouse client not configured. Refuse to aggregate usage with empty source.'
            );
        }
        $sql = 'SELECT user_id, profile_id, device_id, billing_category, timestamp FROM usage_events';
        if ($sinceIso) {
            $sql .= " WHERE timestamp > '" . addslashes($sinceIso) . "'";
        }
        $sql .= ' ORDER BY timestamp LIMIT 10000';
        $rows = $this->clickhouseClient->jsonSelect($sql, []);
        return is_array($rows) ? $rows : [];
    }
}
