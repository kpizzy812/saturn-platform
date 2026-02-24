<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_health_checks', function (Blueprint $table) {
            $table->unsignedBigInteger('memory_total_bytes')->nullable()->after('memory_usage_percent');
        });
    }

    public function down(): void
    {
        Schema::table('server_health_checks', function (Blueprint $table) {
            $table->dropColumn('memory_total_bytes');
        });
    }
};
