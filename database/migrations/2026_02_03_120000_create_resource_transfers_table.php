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
        Schema::create('resource_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            // Polymorphic source (Application, Service, Database)
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->index(['source_type', 'source_id']);

            // Target destination
            $table->foreignId('target_environment_id')->constrained('environments')->cascadeOnDelete();
            $table->foreignId('target_server_id')->constrained('servers')->cascadeOnDelete();

            // Created target resource (after cloning is complete)
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->index(['target_type', 'target_id']);

            // Transfer mode: clone (full resource), data_only (to existing DB), partial (specific tables/collections)
            $table->string('transfer_mode')->default('clone'); // clone, data_only, partial

            // Transfer options for partial mode
            // For PostgreSQL/MySQL/MariaDB: {"tables": ["users", "orders"]}
            // For MongoDB: {"collections": ["users", "orders"]}
            // For Redis: {"key_patterns": ["user:*", "session:*"]}
            // For ClickHouse: {"tables": ["events", "metrics"]}
            $table->json('transfer_options')->nullable();

            // For data_only mode - existing target database UUID
            $table->string('existing_target_uuid')->nullable();

            // Status tracking
            $table->string('status')->default('pending'); // pending, preparing, transferring, restoring, completed, failed, cancelled
            $table->unsignedTinyInteger('progress')->default(0); // 0-100

            // Current step description for UI
            $table->string('current_step')->nullable();

            // Transfer statistics
            $table->unsignedBigInteger('transferred_bytes')->default(0);
            $table->unsignedBigInteger('total_bytes')->nullable();

            // Error handling
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();

            // Logs for debugging
            $table->text('logs')->nullable();

            // User who initiated the transfer
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Team ownership
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['team_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_transfers');
    }
};
