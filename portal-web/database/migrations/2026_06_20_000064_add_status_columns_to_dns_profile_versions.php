<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * ProfileVersion 模型声明了 status/rule_count/message 字段，
     * 但 dns_profile_versions 的原始迁移只包含 core 字段。
     * 这里补齐对齐 ProfilePublishApplicationService 的写入。
     */
    public function up(): void
    {
        Schema::table('profile_versions', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->after('version')->default('published');
            $table->unsignedInteger('rule_count')->nullable()->after('status')->default(0);
            $table->string('message', 500)->nullable()->after('rule_count');
        });
    }

    public function down(): void
    {
        Schema::table('profile_versions', function (Blueprint $table) {
            $table->dropColumn(['status', 'rule_count', 'message']);
        });
    }
};
