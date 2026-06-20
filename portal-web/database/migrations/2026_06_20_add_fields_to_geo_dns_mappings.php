<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('geo_dns_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('geo_dns_mappings', 'node_name')) {
                $table->string('node_name', 100)->nullable()->after('region');
            }
            if (!Schema::hasColumn('geo_dns_mappings', 'public_ipv4')) {
                $table->string('public_ipv4', 45)->nullable()->after('node_name');
            }
            if (!Schema::hasColumn('geo_dns_mappings', 'node_alias')) {
                $table->string('node_alias', 100)->nullable()->after('public_ipv4');
            }
        });
    }

    public function down(): void
    {
        Schema::table('geo_dns_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('geo_dns_mappings', 'node_alias')) {
                $table->dropColumn('node_alias');
            }
            if (Schema::hasColumn('geo_dns_mappings', 'public_ipv4')) {
                $table->dropColumn('public_ipv4');
            }
            if (Schema::hasColumn('geo_dns_mappings', 'node_name')) {
                $table->dropColumn('node_name');
            }
        });
    }
};
