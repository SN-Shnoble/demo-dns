<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 占位迁移：2026-06-12_073324_create_cache_table 已经建好 cache / cache_locks，
    // 当年 dns-console-web 合并进 portal-web 时附带的同名迁移会和它冲突。
    // 真正的 schema 由那条更早的迁移负责，这里只保留 rollback 占位。
    public function up(): void
    {
        // intentionally empty: cache / cache_locks already created by
        // 2026_06_12_073324_create_cache_table
        if (Schema::hasTable('cache')) {
            return;
        }
        // 极端情况：早先的迁移被手动删掉时，仍能兜底建表
        Schema::create('cache', function ($table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });
        Schema::create('cache_locks', function ($table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    public function down(): void
    {
        // 不在 down() 里 drop —— 这两张表是早先迁移建的，本迁移不持有所有权
    }
};
