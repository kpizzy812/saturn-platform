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
        Schema::create('code_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->nullable()->constrained('application_deployment_queues')->nullOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();

            // Git info
            $table->string('commit_sha', 40);
            $table->string('base_commit_sha', 40)->nullable();

            // Status
            $table->string('status', 20)->default('pending'); // pending, analyzing, completed, failed

            // Results (no raw diff stored for security)
            $table->json('files_analyzed')->nullable();
            $table->unsignedInteger('violations_count')->default(0);
            $table->unsignedInteger('critical_count')->default(0);

            // LLM enrichment info (optional)
            $table->string('llm_provider', 50)->nullable();
            $table->string('llm_model', 100)->nullable();
            $table->unsignedInteger('llm_tokens_used')->nullable();
            $table->boolean('llm_failed')->default(false);

            // Cache key for idempotency (hash of diff + detectors version)
            $table->string('cache_key', 64)->index();

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            // Error tracking
            $table->text('error_message')->nullable();

            $table->timestamps();

            // Unique constraint: one review per commit per application
            $table->unique(['application_id', 'commit_sha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_reviews');
    }
};
