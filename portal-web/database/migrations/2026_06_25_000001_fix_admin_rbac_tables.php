<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admin_user_roles')) {
            Schema::table('admin_user_roles', function (Blueprint $table): void {
                if (! Schema::hasColumn('admin_user_roles', 'assigned_by')) {
                    $table->unsignedBigInteger('assigned_by')->nullable()->after('admin_role_id');
                }
                if (! Schema::hasColumn('admin_user_roles', 'assigned_at')) {
                    $table->timestamp('assigned_at')->nullable()->after('assigned_by');
                }
            });
        }

        if (! Schema::hasTable('admin_role_nav_rules')) {
            Schema::create('admin_role_nav_rules', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('admin_role_id');
                $table->string('nav_key', 100);
                $table->boolean('visible')->default(true);
                $table->timestamps();

                $table->unique(['admin_role_id', 'nav_key'], 'uniq_admin_role_nav_rule');
                $table->index('nav_key', 'idx_admin_role_nav_rule_nav_key');
                $table->foreign('admin_role_id', 'fk_admin_role_nav_rules_role')
                    ->references('id')
                    ->on('admin_roles')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        if (Schema::hasTable('admin_menu_rule')) {
            DB::table('admin_menu_rule')
                ->where('path', '/admin/basic-config')
                ->orWhere('title_key', 'admin.basicConfig.title')
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_nav_rules');

        if (Schema::hasTable('admin_user_roles')) {
            Schema::table('admin_user_roles', function (Blueprint $table): void {
                if (Schema::hasColumn('admin_user_roles', 'assigned_at')) {
                    $table->dropColumn('assigned_at');
                }
                if (Schema::hasColumn('admin_user_roles', 'assigned_by')) {
                    $table->dropColumn('assigned_by');
                }
            });
        }
    }
};
