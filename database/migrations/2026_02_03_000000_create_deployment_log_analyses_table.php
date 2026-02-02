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
        Schema::create('deployment_log_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained('application_deployment_queues')->cascadeOnDelete();

            // Analysis results
            $table->string('root_cause')->nullable();
            $table->text('root_cause_details')->nullable();
            $table->json('solution')->nullable();
            $table->json('prevention')->nullable();

            // Metadata
            $table->string('error_category')->default('unknown');
            $table->string('severity')->default('medium');
            $table->float('confidence')->default(0.0);

            // Provider info
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->integer('tokens_used')->nullable();

            // Cache key for deduplication
            $table->string('error_hash', 64)->index();

            // Status
            $table->string('status')->default('pending'); // pending, analyzing, completed, failed
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->unique('deployment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployment_log_analyses');
    }
};
