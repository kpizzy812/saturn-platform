<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PERFORMANCE FIX: Create separate table for deployment logs.
 *
 * Previously, logs were stored as JSON in application_deployment_queues.logs column.
 * Each addLogEntry() call required:
 * 1. Read entire JSON (O(N) where N = number of logs)
 * 2. Decode JSON
 * 3. Append new entry
 * 4. Encode entire JSON
 * 5. Write entire JSON back
 *
 * For a deployment with 1000 log entries, this meant reading/writing ~500KB per log.
 * Total complexity: O(N²) for N log entries.
 *
 * With this table, each addLogEntry() is just an INSERT: O(1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployment_log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')
                ->constrained('application_deployment_queues')
                ->cascadeOnDelete();
            $table->unsignedInteger('order')->default(1);
            $table->string('command')->nullable();
            $table->text('output');
            $table->string('type', 20)->default('stdout'); // stdout, stderr
            $table->boolean('hidden')->default(false);
            $table->unsignedTinyInteger('batch')->default(1);
            $table->string('stage', 50)->nullable(); // prepare, clone, build, push, deploy, healthcheck
            $table->timestamp('created_at')->useCurrent();

            // Index for fast retrieval by deployment
            $table->index(['deployment_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_log_entries');
    }
};
