<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add unified docker_cleanup toggle columns to notification settings tables.
 *
 * The DB originally had split docker_cleanup_success/failure columns,
 * but models and frontend use a single docker_cleanup toggle.
 * This migration adds the missing single-toggle columns.
 */
return new class extends Migration
{
    private array $tables = [
        'telegram_notification_settings' => 'telegram',
        'discord_notification_settings' => 'discord',
        'slack_notification_settings' => 'slack',
        'pushover_notification_settings' => 'pushover',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $channel) {
            $col = "docker_cleanup_{$channel}_notifications";

            Schema::table($table, function (Blueprint $table) use ($col) {
                if (! Schema::hasColumn($table->getTable(), $col)) {
                    $table->boolean($col)->default(false);
                }
            });

            // Populate from existing success/failure columns
            $successCol = "docker_cleanup_success_{$channel}_notifications";
            $failureCol = "docker_cleanup_failure_{$channel}_notifications";

            if (Schema::hasColumn($table, $successCol)) {
                DB::table($table)->update([
                    $col => DB::raw("{$successCol} OR {$failureCol}"),
                ]);
            }
        }

        // Telegram also needs the thread_id column
        Schema::table('telegram_notification_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_notification_settings', 'telegram_notifications_docker_cleanup_thread_id')) {
                $table->text('telegram_notifications_docker_cleanup_thread_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $channel) {
            $col = "docker_cleanup_{$channel}_notifications";
            if (Schema::hasColumn($table, $col)) {
                Schema::table($table, function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }

        Schema::table('telegram_notification_settings', function (Blueprint $table) {
            if (Schema::hasColumn('telegram_notification_settings', 'telegram_notifications_docker_cleanup_thread_id')) {
                $table->dropColumn('telegram_notifications_docker_cleanup_thread_id');
            }
        });
    }
};
