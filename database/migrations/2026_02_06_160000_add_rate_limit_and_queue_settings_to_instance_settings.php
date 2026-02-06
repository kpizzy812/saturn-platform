<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            // API Rate Limiting
            $table->integer('api_rate_limit')->default(200);

            // Horizon Worker
            $table->string('horizon_balance')->default('false');
            $table->integer('horizon_min_processes')->default(1);
            $table->integer('horizon_max_processes')->default(4);
            $table->integer('horizon_worker_memory')->default(128);
            $table->integer('horizon_worker_timeout')->default(3600);
            $table->integer('horizon_max_jobs')->default(400);

            // Horizon Retention
            $table->integer('horizon_trim_recent_minutes')->default(60);
            $table->integer('horizon_trim_failed_minutes')->default(10080);

            // Queue Monitoring
            $table->integer('horizon_queue_wait_threshold')->default(60);
        });
    }

    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'api_rate_limit',
                'horizon_balance',
                'horizon_min_processes',
                'horizon_max_processes',
                'horizon_worker_memory',
                'horizon_worker_timeout',
                'horizon_max_jobs',
                'horizon_trim_recent_minutes',
                'horizon_trim_failed_minutes',
                'horizon_queue_wait_threshold',
            ]);
        });
    }
};
