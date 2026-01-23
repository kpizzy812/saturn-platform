<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('database_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('database_uuid')->index();
            $table->string('database_type'); // postgresql, mysql, redis, mongodb, clickhouse, etc.

            // Common metrics
            $table->float('cpu_percent')->nullable();
            $table->bigInteger('memory_bytes')->nullable();
            $table->bigInteger('memory_limit_bytes')->nullable();
            $table->bigInteger('network_rx_bytes')->nullable();
            $table->bigInteger('network_tx_bytes')->nullable();

            // Database-specific metrics stored as JSON
            $table->json('metrics')->nullable();

            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            // Composite index for efficient time-range queries
            $table->index(['database_uuid', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_metrics');
    }
};
