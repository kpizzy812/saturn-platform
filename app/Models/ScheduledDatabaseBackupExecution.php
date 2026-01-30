<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledDatabaseBackupExecution extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            's3_uploaded' => 'boolean',
            'local_storage_deleted' => 'boolean',
            's3_storage_deleted' => 'boolean',
            'verified_at' => 'datetime',
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
