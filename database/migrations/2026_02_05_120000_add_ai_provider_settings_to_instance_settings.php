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
        Schema::table('instance_settings', function (Blueprint $table) {
            // AI Provider settings (moved from .env to DB for admin UI management)
            $table->string('ai_default_provider')->default('claude');
            $table->text('ai_anthropic_api_key')->nullable(); // encrypted
            $table->text('ai_openai_api_key')->nullable(); // encrypted
            $table->string('ai_claude_model')->default('claude-sonnet-4-20250514');
            $table->string('ai_openai_model')->default('gpt-4o-mini');
            $table->string('ai_ollama_base_url')->nullable();
            $table->string('ai_ollama_model')->default('llama3.1');
            $table->integer('ai_max_tokens')->default(2048);
            $table->boolean('ai_cache_enabled')->default(true);
            $table->integer('ai_cache_ttl')->default(86400);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ai_default_provider',
                'ai_anthropic_api_key',
                'ai_openai_api_key',
                'ai_claude_model',
                'ai_openai_model',
                'ai_ollama_base_url',
                'ai_ollama_model',
                'ai_max_tokens',
                'ai_cache_enabled',
                'ai_cache_ttl',
            ]);
        });
    }
};
