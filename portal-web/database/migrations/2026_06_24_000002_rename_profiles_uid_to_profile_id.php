<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 重命名 dns_profiles.profile_uid → profile_id
 *
 * 迁移文件 `000015_create_dns_profiles_table` 中列名写的是 profile_id，
 * 但实际数据库中被创建为 profile_uid（文件编辑后未重新 migrate）。
 * 此迁移将数据库列与代码保持一致。
 *
 * 变更：
 *   1. 删除 CHECK 约束 chk_profiles_uid（引用旧列名）
 *   2. 重命名列 profile_uid → profile_id
 *   3. 重建 CHECK 约束 chk_profiles_id（引用新列名）
 */
return new class extends Migration
{
    public function up(): void
    {
        // 只有列名实际为 profile_uid 时才执行
        if (! $this->columnExists('profiles', 'profile_uid')) {
            return;
        }

        // Step 1: 删除旧的 CHECK 约束（MySQL 8.0 用 DROP CHECK）
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE dns_profiles DROP CHECK chk_profiles_uid');
        }

        // Step 2: 重命名列
        Schema::table('profiles', function (Blueprint $table) {
            $table->renameColumn('profile_uid', 'profile_id');
        });

        // Step 3: 重建 CHECK 约束（引用新列名）
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE dns_profiles ADD CONSTRAINT chk_profiles_id
                CHECK (profile_id REGEXP '^[0-9a-f]{6}$')");
        }
    }

    public function down(): void
    {
        if (! $this->columnExists('profiles', 'profile_id')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE dns_profiles DROP CHECK chk_profiles_id');
        }

        Schema::table('profiles', function (Blueprint $table) {
            $table->renameColumn('profile_id', 'profile_uid');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE dns_profiles ADD CONSTRAINT chk_profiles_uid
                CHECK (profile_uid REGEXP '^[0-9a-f]{6}$')");
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }
};
