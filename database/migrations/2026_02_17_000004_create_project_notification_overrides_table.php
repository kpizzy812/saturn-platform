<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_notification_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->onDelete('cascade');
            // null = inherit from team, true = enabled, false = disabled
            $table->boolean('deployment_success')->nullable();
            $table->boolean('deployment_failure')->nullable();
            $table->boolean('backup_success')->nullable();
            $table->boolean('backup_failure')->nullable();
            $table->boolean('status_change')->nullable();
            // Custom webhooks (encrypted)
            $table->text('custom_discord_webhook')->nullable();
            $table->text('custom_slack_webhook')->nullable();
            $table->text('custom_webhook_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_notification_overrides');
    }
};
