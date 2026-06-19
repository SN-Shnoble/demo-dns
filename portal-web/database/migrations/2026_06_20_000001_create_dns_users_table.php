<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('username', 100);
            $table->string('email', 190);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('plan_code', 40)->nullable();
            $table->string('locale', 10)->default('zh-CN');
            $table->enum('status', ['active','suspended','closed'])->default('active');
            $table->unsignedBigInteger('current_team_id')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique('username', 'uniq_users_username');
            $table->unique('email', 'uniq_users_email');
            $table->index('plan_code', 'idx_users_plan');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_team_id', 'fk_users_team')
                ->references('id')->on('teams')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
