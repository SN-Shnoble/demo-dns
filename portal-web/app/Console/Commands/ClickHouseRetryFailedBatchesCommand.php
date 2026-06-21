<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\ClickHouse\ClickHouseClient;
use App\Models\QueryLogEntry;
use App\Models\QueryLogIngestBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 2026-06-22 — ClickHouse 补传任务。
 *
 * 扫描 dns_query_log_ingest_batches 中 status='partial' 且 forwarded_to_clickhouse=0
 * 的批次，重新从 dns_query_log_entries 读取原始 items 并批量 insert 到 ClickHouse dns_logs。
 *
 * 设计要点（最小修改原则）：
 *   1. 不新增 retry_count 字段 — 复用 status 与 error_message 计数
 *   2. 批次从 raw_payload 读 fallback，dns_query_log_entries 才是主源
 *   3. 每批失败追加 [retry N] 前缀到 error_message，>5 次标 status='failed' 永不再试
 *   4. 默认每次处理 50 批（limit 1000 行），可用 --batch=N 调整
 *
 * 用法：
 *   php artisan clickhouse:retry-failed-batches
 *   php artisan clickhouse:retry-failed-batches --batch=200 --limit=2000
 */
final class ClickHouseRetryFailedBatchesCommand extends Command
{
    protected $signature = 'clickhouse:retry-failed-batches
        {--batch=50 : 每批处理的最大 batch 数量}
        {--limit=1000 : 单个 batch 最多回填的行数}';

    protected $description = '补传 forwarded_to_clickhouse=0 的 partial 批次到 ClickHouse';

    public function handle(): int
    {
        $batchLimit = (int) $this->option('batch');
        $rowLimit = (int) $this->option('limit');
        $client = new ClickHouseClient();

        // 2026-06-22: 单条 ping 失败直接退出，避免无意义循环
        if (! $client->ping()) {
            $this->error('ClickHouse ping failed — aborting');

            return self::FAILURE;
        }

        $batches = QueryLogIngestBatch::query()
            ->where('status', 'partial')
            ->where('forwarded_to_clickhouse', false)
            ->orderBy('id')
            ->limit($batchLimit)
            ->get();

        if ($batches->isEmpty()) {
            $this->info('No partial batches to retry');

            return self::SUCCESS;
        }

        $succeeded = 0;
        $failed = 0;
        $gaveUp = 0;

        foreach ($batches as $batch) {
            $retryCount = $this->extractRetryCount($batch->error_message);

            $entries = QueryLogEntry::query()
                ->where('ingest_batch_id', $batch->id)
                ->orderBy('id')
                ->limit($rowLimit)
                ->get();

            if ($entries->isEmpty()) {
                // entries 已被裁剪 / 清理 → 不再重试
                $batch->update([
                    'status' => 'failed',
                    'error_message' => 'no entries left to retry (original: ' . substr((string) $batch->error_message, 0, 200) . ')',
                    'forwarded_to_clickhouse' => false,
                    'updated_at' => now(),
                ]);
                $gaveUp++;
                continue;
            }

            $dnsLogs = [];
            foreach ($entries as $e) {
                $ts = $e->queried_at ?: $e->created_at ?: now();
                $dnsLogs[] = [
                    'event_time' => $ts->format('Y-m-d H:i:s'),
                    'timestamp' => $ts->format('Y-m-d H:i:s'),
                    'node_id' => (string) $e->node_id,
                    'user_id' => $e->user_id !== null ? (string) $e->user_id : '',
                    'profile_id' => $e->profile_id !== null ? (string) $e->profile_id : '',
                    'device_id' => $e->device_id !== null ? (string) $e->device_id : '',
                    'query_name' => (string) $e->query_name,
                    'domain' => (string) $e->query_name,
                    'query_type' => strtoupper((string) $e->query_type),
                    'action' => strtoupper((string) $e->action),
                    'reason' => (string) ($e->reason ?? ''),
                    'category' => (string) ($e->category ?? ''),
                    'client_ip' => (string) ($e->client_ip ?? ''),
                    'rcode' => (int) $e->rcode,
                    'latency_ms' => (int) $e->latency_ms,
                    // 2026-06-22: 旧 entries 没存 protocol，retry 时置空字符串（CH 列已 ALTER）
                    'protocol' => '',
                ];
            }

            try {
                $client->insertJsonEachRow('dns_logs', $dnsLogs);
                $batch->update([
                    'status' => 'succeeded',
                    'forwarded_to_clickhouse' => true,
                    'error_message' => null,
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);
                $succeeded++;
            } catch (\Throwable $e) {
                $newCount = $retryCount + 1;
                $newMsg = sprintf('[retry %d] %s', $newCount, $e->getMessage());
                $giveUp = $newCount >= 5;
                $batch->update([
                    'status' => $giveUp ? 'failed' : 'partial',
                    'error_message' => substr($newMsg, 0, 500),
                    'forwarded_to_clickhouse' => false,
                    'updated_at' => now(),
                ]);
                if ($giveUp) {
                    $gaveUp++;
                } else {
                    $failed++;
                }
            }
        }

        $this->line(sprintf(
            'batches=%d succeeded=%d still_partial=%d gave_up=%d',
            $batches->count(),
            $succeeded,
            $failed,
            $gaveUp
        ));

        return self::SUCCESS;
    }

    /**
     * 从 error_message 中解析 [retry N] 计数，没有就返回 0。
     */
    private function extractRetryCount(?string $errorMessage): int
    {
        if ($errorMessage === null) {
            return 0;
        }
        if (preg_match('/\[retry (\d+)\]/', $errorMessage, $m) === 1) {
            return (int) $m[1];
        }

        return 0;
    }
}
