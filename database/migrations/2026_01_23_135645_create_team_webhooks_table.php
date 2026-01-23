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
        Schema::create('team_webhooks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('url'); // Encrypted in model
            $table->text('secret'); // Encrypted in model
            $table->json('events')->default('[]');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'enabled']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->integer('status_code')->nullable();
            $table->json('payload');
            $table->text('response')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();

            $table->index(['team_webhook_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('team_webhooks');
    }
};
