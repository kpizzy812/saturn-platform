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
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // Context - what page/resource the chat was started from
            $table->string('context_type')->nullable(); // application, server, database, service, project
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('context_name')->nullable(); // Resource name for display

            $table->string('title')->nullable(); // Auto-generated or user-defined
            $table->string('status')->default('active'); // active, archived

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['team_id', 'status']);
            $table->index(['context_type', 'context_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_chat_sessions');
    }
};
