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
        Schema::table('scheduled_database_backup_executions', function (Blueprint $table) {
            $table->string('restore_status')->nullable()->after('status');
            $table->timestamp('restore_started_at')->nullable()->after('restore_status');
            $table->timestamp('restore_finished_at')->nullable()->after('restore_started_at');
            $table->text('restore_message')->nullable()->after('restore_finished_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_database_backup_executions', function (Blueprint $table) {
            $table->dropColumn(['restore_status', 'restore_started_at', 'restore_finished_at', 'restore_message']);
        });
    }
};
