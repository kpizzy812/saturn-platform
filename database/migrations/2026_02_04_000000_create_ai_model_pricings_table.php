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
        Schema::create('ai_model_pricings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->index(); // 'openai', 'anthropic', 'ollama'
            $table->string('model_id', 100)->index(); // e.g., 'gpt-4o', 'claude-sonnet-4-20250514'
            $table->string('model_name', 100); // Human readable name
            $table->decimal('input_price_per_1m', 10, 4); // Price per 1M input tokens in USD
            $table->decimal('output_price_per_1m', 10, 4); // Price per 1M output tokens in USD
            $table->unsignedInteger('context_window')->nullable(); // Max context window in tokens
            $table->boolean('is_active')->default(true); // Whether model is active/available
            $table->timestamps();

            $table->unique(['provider', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_model_pricings');
    }
};
