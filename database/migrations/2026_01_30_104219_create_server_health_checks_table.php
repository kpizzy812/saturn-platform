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
        Schema::create('server_health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->onDelete('cascade');
            $table->string('status', 20); // 'healthy', 'degraded', 'down', 'unreachable'
            $table->boolean('is_reachable')->default(false);
            $table->boolean('is_usable')->default(false);
            $table->integer('response_time_ms')->nullable(); // SSH connection time in ms
            $table->float('disk_usage_percent')->nullable(); // Disk usage percentage
            $table->float('cpu_usage_percent')->nullable(); // CPU usage if available
            $table->float('memory_usage_percent')->nullable(); // Memory usage if available
            $table->text('error_message')->nullable(); // Error details if check failed
            $table->integer('uptime_seconds')->nullable(); // Server uptime in seconds
            $table->string('docker_version')->nullable(); // Docker version detected
            $table->json('container_counts')->nullable(); // {running: X, stopped: Y, total: Z}
            $table->timestamp('checked_at'); // When this check was performed
            $table->timestamps();

            // Indexes for performance
            $table->index('server_id');
            $table->index('status');
            $table->index('checked_at');
            $table->index(['server_id', 'checked_at']); // For querying server health history
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_health_checks');
    }
};
