<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds Smart Rollback features for automatic rollback on errors.
     */
    public function up(): void
    {
        // Add auto-rollback settings to application_settings
        Schema::table('application_settings', function (Blueprint $table) {
            $table->boolean('auto_rollback_enabled')->default(false);
            $table->integer('rollback_validation_seconds')->default(300);
            $table->integer('rollback_max_restarts')->default(3);
            $table->boolean('rollback_on_health_check_fail')->default(true);
            $table->boolean('rollback_on_crash_loop')->default(true);
        });

        // Create rollback events table for audit trail
        Schema::create('application_rollback_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('failed_deployment_id')
                ->nullable()
                ->references('id')
                ->on('application_deployment_queues')
                ->nullOnDelete();
            $table->foreignId('rollback_deployment_id')
                ->nullable()
                ->references('id')
                ->on('application_deployment_queues')
                ->nullOnDelete();
            $table->foreignId('triggered_by_user_id')
                ->nullable()
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->string('trigger_reason');
            $table->enum('trigger_type', ['automatic', 'manual'])->default('automatic');
            $table->json('metrics_snapshot')->nullable();
            $table->string('status')->default('triggered');
            $table->text('error_message')->nullable();

            $table->string('from_commit')->nullable();
            $table->string('to_commit')->nullable();

            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'created_at']);
            $table->index('status');
        });

        // Add last successful deployment tracking
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('last_successful_deployment_id')
                ->nullable()
                ->references('id')
                ->on('application_deployment_queues')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_settings', function (Blueprint $table) {
            $table->dropColumn([
                'auto_rollback_enabled',
                'rollback_validation_seconds',
                'rollback_max_restarts',
                'rollback_on_health_check_fail',
                'rollback_on_crash_loop',
            ]);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['last_successful_deployment_id']);
            $table->dropColumn('last_successful_deployment_id');
        });

        Schema::dropIfExists('application_rollback_events');
    }
};
