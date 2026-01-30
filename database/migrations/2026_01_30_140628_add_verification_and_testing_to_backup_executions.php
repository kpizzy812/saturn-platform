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
            // Verification fields
            $table->string('verification_status')->nullable()->after('s3_storage_deleted'); // pending, verified, failed, skipped
            $table->timestamp('verified_at')->nullable()->after('verification_status');
            $table->text('verification_message')->nullable()->after('verified_at');
            $table->string('checksum')->nullable()->after('verification_message'); // MD5/SHA256 hash
            $table->string('checksum_algorithm')->nullable()->after('checksum'); // md5, sha256

            // Restore test fields
            $table->string('restore_test_status')->nullable()->after('checksum_algorithm'); // pending, success, failed, skipped
            $table->timestamp('restore_test_at')->nullable()->after('restore_test_status');
            $table->text('restore_test_message')->nullable()->after('restore_test_at');
            $table->integer('restore_test_duration_seconds')->nullable()->after('restore_test_message');

            // S3 integrity fields
            $table->string('s3_integrity_status')->nullable()->after('restore_test_duration_seconds'); // verified, failed, pending
            $table->timestamp('s3_integrity_checked_at')->nullable()->after('s3_integrity_status');
            $table->bigInteger('s3_file_size')->nullable()->after('s3_integrity_checked_at');
            $table->string('s3_etag')->nullable()->after('s3_file_size');
        });

        // Add settings for automated testing
        Schema::table('scheduled_database_backups', function (Blueprint $table) {
            $table->boolean('verify_after_backup')->default(true)->after('disable_local_backup');
            $table->boolean('restore_test_enabled')->default(false)->after('verify_after_backup');
            $table->string('restore_test_frequency')->default('weekly')->after('restore_test_enabled'); // daily, weekly, monthly
            $table->timestamp('last_restore_test_at')->nullable()->after('restore_test_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_database_backup_executions', function (Blueprint $table) {
            $table->dropColumn([
                'verification_status',
                'verified_at',
                'verification_message',
                'checksum',
                'checksum_algorithm',
                'restore_test_status',
                'restore_test_at',
                'restore_test_message',
                'restore_test_duration_seconds',
                's3_integrity_status',
                's3_integrity_checked_at',
                's3_file_size',
                's3_etag',
            ]);
        });

        Schema::table('scheduled_database_backups', function (Blueprint $table) {
            $table->dropColumn([
                'verify_after_backup',
                'restore_test_enabled',
                'restore_test_frequency',
                'last_restore_test_at',
            ]);
        });
    }
};
