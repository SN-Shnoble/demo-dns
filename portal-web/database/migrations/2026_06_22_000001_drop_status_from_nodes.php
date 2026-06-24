<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-22: 删除 nodes.status 列。
 *
 * 旧设计里 nodes.status 是「节点是否在线」的状态位（pending/online/offline/degraded/...），
 * 由 HeartbeatController 在收到心跳时写 online，cron / MarkOfflineCommand 写 offline。
 *
 * 新设计里整个系统只有「一个事实源：last_heartbeat_at」：
 *   - 在线/离线在 Node::isOnline() / runtimeStatus() 里按 last_heartbeat_at + 阈值即时算出
 *   - 不再写 nodes.status，不再有 cron / MarkOfflineCommand
 *
 * 这条迁移同时删 idx_nodes_status 索引，否则 dropColumn 在 PG 上会因为索引依赖失败。
 *
 * 注意：dns_node_heartbeats.status 是历史表，每条心跳自报的状态，**保留**（不影响 runtimeStatus）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            // PG 上必须先删索引再删列，否则报 "index ... depends on column status"
            $table->dropIndex('idx_nodes_status');
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        // 回滚：恢复列与索引。install_status / last_installed_at / last_listen_addr 不动。
        Schema::table('nodes', function (Blueprint $table): void {
            $table->enum('status', ['pending', 'online', 'offline', 'degraded', 'maintenance', 'disabled', 'retired'])
                ->default('pending');
            $table->index('status', 'idx_nodes_status');
        });
    }
};
