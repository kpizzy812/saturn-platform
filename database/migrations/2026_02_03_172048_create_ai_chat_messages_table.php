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
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('session_id')->constrained('ai_chat_sessions')->cascadeOnDelete();

            // Message content
            $table->string('role'); // user, assistant, system
            $table->text('content');

            // Command/Intent detection (for actionable messages)
            $table->string('intent')->nullable(); // deploy, restart, stop, start, logs, status, help, etc.
            $table->json('intent_params')->nullable(); // {resource_type: 'application', resource_id: 123}

            // Command execution status (for messages that trigger actions)
            $table->string('command_status')->nullable(); // pending, executing, completed, failed, cancelled
            $table->text('command_result')->nullable();

            // User feedback
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5 stars

            $table->timestamps();

            $table->index('session_id');
            $table->index(['session_id', 'created_at']);
            $table->index('intent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
