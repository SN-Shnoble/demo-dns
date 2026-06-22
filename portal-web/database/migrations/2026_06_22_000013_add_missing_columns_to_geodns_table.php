<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为 dns_geodns 表补充 GeoDnsMapping 模型所需的缺失列
 *
 * 背景：GeoDnsMapping 模型原指向 geo_dns_mappings 表（已删除），
 * 现改为指向 dns_geodns 表。该表缺少 country / target_node_id / enabled 等列。
 *
 * 变更说明：
 * - country：原 geo_dns_mappings 地域国家字段
 * - target_node_id：目标 DNS 节点外键
 * - node_name：节点名称
 * - public_ipv4：节点公网 IPv4
 * - node_alias：节点别名
 * - target_endpoint：目标端点 URL
 * - priority：优先级
 * - enabled：是否启用
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geodns', function (Blueprint $table): void {
            if (! Schema::hasColumn('geodns', 'country')) {
                $table->string('country', 8)->nullable()->after('domain');
            }
            if (! Schema::hasColumn('geodns', 'target_node_id')) {
                $table->unsignedBigInteger('target_node_id')->nullable()->after('country');
            }
            if (! Schema::hasColumn('geodns', 'node_name')) {
                $table->string('node_name', 100)->nullable()->after('target_node_id');
            }
            if (! Schema::hasColumn('geodns', 'public_ipv4')) {
                $table->string('public_ipv4', 45)->nullable()->after('node_name');
            }
            if (! Schema::hasColumn('geodns', 'node_alias')) {
                $table->string('node_alias', 100)->nullable()->after('public_ipv4');
            }
            if (! Schema::hasColumn('geodns', 'target_endpoint')) {
                $table->string('target_endpoint', 255)->nullable()->after('node_alias');
            }
            if (! Schema::hasColumn('geodns', 'priority')) {
                $table->integer('priority')->default(0)->after('target_endpoint');
            }
            if (! Schema::hasColumn('geodns', 'enabled')) {
                $table->boolean('enabled')->default(true)->after('priority');
            }
        });
    }

    public function down(): void
    {
        Schema::table('geodns', function (Blueprint $table): void {
            $columns = ['enabled', 'priority', 'target_endpoint', 'node_alias', 'public_ipv4', 'node_name', 'target_node_id', 'country'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('geodns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};