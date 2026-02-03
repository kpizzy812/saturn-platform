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
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('ai_chat_messages')->cascadeOnDelete();

            // Provider info
            $table->string('provider'); // claude, openai
            $table->string('model'); // claude-sonnet-4-20250514, gpt-4o, etc.
            $table->string('operation'); // chat, command_parse, stream

            // Token usage
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);

            // Cost tracking (USD)
            $table->decimal('cost_usd', 10, 6)->default(0);

            // Performance
            $table->unsignedInteger('response_time_ms')->nullable();

            // Status
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
            $table->index(['provider', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
