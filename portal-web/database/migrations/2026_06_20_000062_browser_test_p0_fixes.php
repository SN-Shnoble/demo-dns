<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 兼容性修复 (2026-06-20)。
 * 仅允许补充缺失列，不允许覆盖正式生产表结构。
 */
return new class extends Migration {
    public function up(): void
    {
        // 2026-06-22: query_log_ingest_batches table has been removed (migration deleted).

        // 2) geo dns mappings: priority
        if (Schema::hasTable('geo_dns_mappings') && ! Schema::hasColumn('geo_dns_mappings', 'priority')) {
            Schema::table('geo_dns_mappings', function (Blueprint $t) {
                $t->unsignedInteger('priority')->default(0)->after('target_endpoint');
            });
        }

        // 3) admin roles: is_system / status
        if (Schema::hasTable('admin_roles')) {
            if (! Schema::hasColumn('admin_roles', 'is_system')) {
                Schema::table('admin_roles', function (Blueprint $t) {
                    $t->boolean('is_system')->default(false)->after('description');
                });
                DB::table('admin_roles')->update([
                    'is_system' => DB::raw('is_builtin'),
                ]);
            }
            if (! Schema::hasColumn('admin_roles', 'status')) {
                Schema::table('admin_roles', function (Blueprint $t) {
                    $t->string('status', 20)->default('active')->after('is_system');
                });
            }
        }

        // 4) team invitations: declined_at
        if (Schema::hasTable('team_invitations') && ! Schema::hasColumn('team_invitations', 'declined_at')) {
            Schema::table('team_invitations', function (Blueprint $t) {
                $t->timestamp('declined_at')->nullable()->after('accepted_at');
            });
        }

        // 5) subscriptions: plan_code
        if (Schema::hasTable('subscriptions')) {
            if (! Schema::hasColumn('subscriptions', 'plan_code')) {
                Schema::table('subscriptions', function (Blueprint $t) {
                    $t->string('plan_code', 50)->nullable()->after('plan_id');
                });
            }
        }

        // 5b) profiles: member center core fields
        if (Schema::hasTable('profiles')) {
            Schema::table('profiles', function (Blueprint $t) {
                if (! Schema::hasColumn('profiles', 'default_action')) {
                    $t->string('default_action', 20)->default('allow')->after('description');
                }
                if (! Schema::hasColumn('profiles', 'block_response')) {
                    $t->string('block_response', 20)->default('nxdomain')->after('default_action');
                }
                if (! Schema::hasColumn('profiles', 'log_mode')) {
                    $t->string('log_mode', 20)->default('full')->after('parental_settings');
                }
            });
        }

        // 6) policy snapshots: status
        if (Schema::hasTable('policy_snapshots') && ! Schema::hasColumn('policy_snapshots', 'status')) {
            Schema::table('policy_snapshots', function (Blueprint $t) {
                $t->string('status', 20)->default('draft')->after('checksum');
            });
        }

        // 不再创建 invoices / 不再把 publish_tasks、task_executions 改回测试时代字段。
    }

    public function down(): void
    {
        //
    }
};
