<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->enum('node_type', ['resolver', 'geodns'])
                ->default('resolver')
                ->after('node_code');
            $table->string('domain')->nullable()
                ->after('name');
            $table->string('city')->nullable()
                ->after('country');
            $table->integer('weight')->default(100)
                ->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['node_type', 'domain', 'city', 'weight']);
        });
    }
};
