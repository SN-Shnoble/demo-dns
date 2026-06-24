<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-21: 节点 API Key 字段
 *
 * 用于 register 端点签发的 api_key 存储（hash(sha256) 形式）。
 * 之后所有业务接口用 api_key 鉴权，不再依赖加密 token。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->string('api_key', 80)->nullable();
            $table->timestamp('api_key_issued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            $table->dropColumn(['api_key', 'api_key_issued_at']);
        });
    }
};
