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
        // Email notification settings
        Schema::table('email_notification_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('email_notification_settings', 'deployment_approval_required_email_notifications')) {
                $table->boolean('deployment_approval_required_email_notifications')->default(true)->after('deployment_failure_email_notifications');
            }
        });

        // Discord notification settings
        Schema::table('discord_notification_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('discord_notification_settings', 'deployment_approval_required_discord_notifications')) {
                $table->boolean('deployment_approval_required_discord_notifications')->default(true)->after('deployment_failure_discord_notifications');
            }
        });

        // Telegram notification settings
        Schema::table('telegram_notification_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_notification_settings', 'deployment_approval_required_telegram_notifications')) {
                $table->boolean('deployment_approval_required_telegram_notifications')->default(true)->after('deployment_failure_telegram_notifications');
            }
            if (! Schema::hasColumn('telegram_notification_settings', 'telegram_notifications_deployment_approval_required_thread_id')) {
                $table->text('telegram_notifications_deployment_approval_required_thread_id')->nullable()->after('telegram_notifications_deployment_failure_thread_id');
            }
        });

        // Slack notification settings
        Schema::table('slack_notification_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('slack_notification_settings', 'deployment_approval_required_slack_notifications')) {
                $table->boolean('deployment_approval_required_slack_notifications')->default(true)->after('deployment_failure_slack_notifications');
            }
        });

        // Pushover notification settings
        Schema::table('pushover_notification_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('pushover_notification_settings', 'deployment_approval_required_pushover_notifications')) {
                $table->boolean('deployment_approval_required_pushover_notifications')->default(true)->after('deployment_failure_pushover_notifications');
            }
        });

        // Webhook notification settings
        Schema::table('webhook_notification_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('webhook_notification_settings', 'deployment_approval_required_webhook_notifications')) {
                $table->boolean('deployment_approval_required_webhook_notifications')->default(true)->after('deployment_failure_webhook_notifications');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_notification_settings', function (Blueprint $table) {
            $table->dropColumnIfExists('deployment_approval_required_email_notifications');
        });

        Schema::table('discord_notification_settings', function (Blueprint $table) {
            $table->dropColumnIfExists('deployment_approval_required_discord_notifications');
        });

        Schema::table('telegram_notification_settings', function (Blueprint $table) {
            $table->dropColumnIfExists('deployment_approval_required_telegram_notifications');
            $table->dropColumnIfExists('telegram_notifications_deployment_approval_required_thread_id');
        });

        Schema::table('slack_notification_settings', function (Blueprint $table) {
            $table->dropColumnIfExists('deployment_approval_required_slack_notifications');
        });

        Schema::table('pushover_notification_settings', function (Blueprint $table) {
            $table->dropColumnIfExists('deployment_approval_required_pushover_notifications');
        });

        Schema::table('webhook_notification_settings', function (Blueprint $table) {
            $table->dropColumnIfExists('deployment_approval_required_webhook_notifications');
        });
    }
};
