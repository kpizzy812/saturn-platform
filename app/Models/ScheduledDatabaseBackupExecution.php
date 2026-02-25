<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledDatabaseBackupExecution extends BaseModel
{
    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, verified_at, restore_test_at, s3_integrity_checked_at (system-managed)
     */
    protected $fillable = [
        'scheduled_database_backup_id',
        'status',
        'message',
        'filename',
        'size',
        'database_name',
        'finished_at',
        's3_uploaded',
        's3_file_size',
        's3_object_key',
        'local_storage_deleted',
        's3_storage_deleted',
        'verification_status',
        'verification_error',
        'restore_status',
        'restore_started_at',
        'restore_finished_at',
        'restore_message',
        'restore_test_status',
        'restore_test_error',
        'restore_test_duration_seconds',
        's3_integrity_status',
        's3_integrity_error',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            's3_uploaded' => 'boolean',
            'local_storage_deleted' => 'boolean',
            's3_storage_deleted' => 'boolean',
            'is_encrypted' => 'boolean',
            'finished_at' => 'datetime',
            'verified_at' => 'datetime',
            'restore_started_at' => 'datetime',
            'restore_finished_at' => 'datetime',
            'restore_test_at' => 'datetime',
            's3_integrity_checked_at' => 'datetime',
            's3_file_size' => 'integer',
            'restore_test_duration_seconds' => 'integer',
        ];
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isRestoreTestPassed(): bool
    {
        return $this->restore_test_status === 'success';
    }

    public function hasValidS3Copy(): bool
    {
        return $this->s3_uploaded
            && ! $this->s3_storage_deleted
            && $this->s3_integrity_status === 'verified';
    }

    public function scheduledDatabaseBackup(): BelongsTo
    {
        return $this->belongsTo(ScheduledDatabaseBackup::class);
    }
}
