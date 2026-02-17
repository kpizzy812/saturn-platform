<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_database_backups', function (Blueprint $table) {
            $table->boolean('encrypt_backup')->default(false)->after('disable_local_backup');
            $table->text('encryption_key')->nullable()->after('encrypt_backup');
        });

        Schema::table('scheduled_database_backup_executions', function (Blueprint $table) {
            $table->boolean('is_encrypted')->default(false)->after('local_storage_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_database_backups', function (Blueprint $table) {
            $table->dropColumn(['encrypt_backup', 'encryption_key']);
        });

        Schema::table('scheduled_database_backup_executions', function (Blueprint $table) {
            $table->dropColumn('is_encrypted');
        });
    }
};
