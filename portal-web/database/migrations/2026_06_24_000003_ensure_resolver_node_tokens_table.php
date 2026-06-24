<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 修复生产服务器 dns_resolver_node_tokens 表缺失问题。
 *
 * 根因：迁移状态损坏导致 dns_node_tokens → dns_resolver_node_tokens 的重命名未生效。
 * 策略：
 *   1. 若 dns_resolver_node_tokens 已存在 → 跳过
 *   2. 若 dns_node_tokens（旧名）仍存在 → 直接重命名
 *   3. 若都不存在 → 从零创建（带 FK → dns_resolver_nodes）
 */
return new class extends Migration
{
    public function up(): void
    {
        // 目标表已存在，无需处理
        if (Schema::hasTable('resolver_node_tokens')) {
            return;
        }

        // 旧表存在 → 重命名即可
        if (Schema::hasTable('node_tokens')) {
            Schema::rename('node_tokens', 'resolver_node_tokens');
            return;
        }

        // 都不存在 → 创建新表
        Schema::create('resolver_node_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('node_id');
            $table->string('token_prefix', 20);
            $table->char('token_hash', 64);
            $table->string('hmac_key_hash', 128)->nullable();
            $table->text('hmac_secret_encrypted')->nullable();
            $table->json('scopes')->nullable();
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 255)->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique('token_prefix', 'uniq_node_tokens_prefix');
            $table->unique('token_hash', 'uniq_node_tokens_hash');
            $table->index('node_id', 'idx_node_tokens_node');

            // FK → dns_resolver_nodes（已重命名后的表）
            $table->foreign('node_id', 'fk_node_tokens_node')
                ->references('id')->on('resolver_nodes')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        // 不执行任何操作：只做修复，不做回退
    }
};
