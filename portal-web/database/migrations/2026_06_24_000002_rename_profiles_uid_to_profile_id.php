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

        // Step 1: 删除旧的 CHECK 约束（如果存在）
        $this->dropCheckIfExists('dns_profiles', 'chk_profiles_uid');

        // Step 2: 重命名列
        Schema::table('profiles', function (Blueprint $table) {
            $table->renameColumn('profile_uid', 'profile_id');
        });

        // Step 3: 重建 CHECK 约束（引用新列名）
        $this->addCheckIfMissing('dns_profiles', 'chk_profiles_id', 'profile_id');
    }

    public function down(): void
    {
        if (! $this->columnExists('profiles', 'profile_id')) {
            return;
        }

        $this->dropCheckIfExists('dns_profiles', 'chk_profiles_id');

        Schema::table('profiles', function (Blueprint $table) {
            $table->renameColumn('profile_id', 'profile_uid');
        });

        $this->addCheckIfMissing('dns_profiles', 'chk_profiles_uid', 'profile_uid');
    }

    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    private function checkExists(string $constraintName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['dns_profiles', $constraintName]
        );
        return $row !== null;
    }

    private function dropCheckIfExists(string $table, string $constraintName): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if (! $this->checkExists($constraintName)) {
            return;
        }
        DB::statement("ALTER TABLE {$table} DROP CHECK {$constraintName}");
    }

    private function addCheckIfMissing(string $table, string $constraintName, string $column): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if ($this->checkExists($constraintName)) {
            return;
        }
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraintName}
            CHECK ({$column} REGEXP '^[0-9a-f]{6}$')");
    }
};
