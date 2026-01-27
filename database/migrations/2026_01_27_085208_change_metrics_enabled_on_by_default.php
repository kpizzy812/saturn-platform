<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Enable metrics collection by default for all servers.
     * This ensures Sentinel collects CPU/memory/disk metrics
     * on all servers including auto-provisioned VPS instances.
     */
    public function up(): void
    {
        Schema::table('server_settings', function (Blueprint $table) {
            $table->boolean('is_metrics_enabled')->default(true)->change();
        });

        // Enable metrics on all existing servers
        DB::table('server_settings')->update(['is_metrics_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_settings', function (Blueprint $table) {
            $table->boolean('is_metrics_enabled')->default(false)->change();
        });
    }
};
