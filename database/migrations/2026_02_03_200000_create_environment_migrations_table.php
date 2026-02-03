<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates environment_migrations table for resource migration between environments (dev → uat → prod).
     */
    public function up(): void
    {
        Schema::create('environment_migrations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            // Polymorphic source resource (Application, Service, Database)
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->index(['source_type', 'source_id']);

            // Source and target environments
            $table->foreignId('source_environment_id')->constrained('environments')->cascadeOnDelete();
            $table->foreignId('target_environment_id')->constrained('environments')->cascadeOnDelete();

            // Target server for deployment
            $table->foreignId('target_server_id')->constrained('servers')->cascadeOnDelete();

            // Created/updated target resource (after migration is complete)
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->index(['target_type', 'target_id']);

            // Migration options
            // - copy_env_vars: Copy environment variables
            // - copy_volumes: Copy volume configurations (not data!)
            // - update_existing: Update existing resource instead of creating new
            // - config_only: Only update configuration, don't recreate container (for prod DBs)
            $table->json('options')->nullable();

            // Status tracking
            $table->string('status', 20)->default('pending');
            // pending: waiting for approval (if required) or ready to start
            // approved: approved by admin/owner, ready to execute
            // rejected: rejected by admin/owner
            // in_progress: migration is running
            // completed: successfully completed
            // failed: migration failed
            // rolled_back: migration was rolled back

            // Approval workflow
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Rollback snapshot for recovery
            // Stores: source config, existing target config (if updating), env vars, volumes
            $table->json('rollback_snapshot')->nullable();

            // Progress tracking
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->string('current_step')->nullable();
            $table->text('logs')->nullable();
            $table->text('error_message')->nullable();

            // Team ownership
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('status');
            $table->index(['team_id', 'status']);
            $table->index(['requires_approval', 'status']);
            $table->index(['requested_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('environment_migrations');
    }
};
