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
        Schema::create('code_review_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('code_review_id')->constrained('code_reviews')->cascadeOnDelete();

            // Rule identification
            $table->string('rule_id', 20);        // SEC001, SEC002, etc.
            $table->string('source', 20);          // 'regex', 'ast', 'llm'

            // Severity and confidence
            $table->string('severity', 20);        // critical, high, medium, low
            $table->decimal('confidence', 3, 2)->default(1.00); // 1.00 for deterministic

            // Location
            $table->string('file_path', 500);
            $table->unsignedInteger('line_number')->nullable();

            // Details (snippet is masked - no secrets stored)
            $table->text('message');
            $table->text('snippet')->nullable();        // Masked snippet
            $table->text('suggestion')->nullable();      // LLM enrichment

            // Security flags
            $table->boolean('contains_secret')->default(false);

            // Fingerprint for deduplication across reviews
            $table->string('fingerprint', 64)->nullable()->index();

            $table->timestamp('created_at')->useCurrent();

            // Indexes for common queries
            $table->index('code_review_id');
            $table->index('severity');
            $table->index('rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_review_violations');
    }
};
