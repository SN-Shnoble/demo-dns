<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-22: UsageBillingService 增量偏移改为用 max_timestamp（末笔 event 时间）做游标。
 *
 * 改动：
 *   1. 新增 max_timestamp datetime nullable 列
 *   2. 初始化已有记录的 max_timestamp = processed_at（向后兼容旧 offset）
 *   3. 删除旧数据中重复的 window_start 记录（去重）
 */
return new class extends Migration {
    public function up(): void
    {
        // 1) 新增 max_timestamp 列
        if (Schema::hasTable('aggregation_offsets') && ! Schema::hasColumn('aggregation_offsets', 'max_timestamp')) {
            Schema::table('aggregation_offsets', function (Blueprint $table): void {
                $table->dateTime('max_timestamp')->nullable()->after('window_start')
                    ->comment('拉取到的末笔 event timestamp，用作增量游标');
            });

            // 2) 初始化已有记录：max_timestamp = processed_at（向后兼容）
            //    仅对 topic='usage_aggregation' 的记录赋值
            DB::table('aggregation_offsets')
                ->where('topic', 'usage_aggregation')
                ->whereNull('max_timestamp')
                ->update([
                    'max_timestamp' => DB::raw('processed_at'),
                ]);

            // 3) 删除同一 topic + window_start 的重复记录（旧逻辑每次 insert 一条新记录）
            //    保留 id 最大的一条
            $duplicates = DB::table('aggregation_offsets')
                ->select(DB::raw('MAX(id) as keep_id'))
                ->where('topic', 'usage_aggregation')
                ->groupBy(['topic', 'window_start'])
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $row) {
                DB::table('aggregation_offsets')
                    ->where('topic', 'usage_aggregation')
                    ->where('window_start', function ($q) use ($row) {
                        // 子查询取同一个 window_start 的 id 列表
                        $q->select('window_start')
                          ->from('aggregation_offsets', 'ao')
                          ->where('ao.id', $row->keep_id);
                    })
                    ->where('id', '<>', $row->keep_id)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('aggregation_offsets') && Schema::hasColumn('aggregation_offsets', 'max_timestamp')) {
            Schema::table('aggregation_offsets', function (Blueprint $table): void {
                $table->dropColumn('max_timestamp');
            });
        }
    }
};
