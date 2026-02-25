<?php

use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $execution = new ScheduledDatabaseBackupExecution;
    expect($execution->getFillable())->not->toBeEmpty();
});

test('fillable does not include id', function () {
    $fillable = (new ScheduledDatabaseBackupExecution)->getFillable();
    expect($fillable)->not->toContain('id');
});

test('fillable includes restore status fields', function () {
    $fillable = (new ScheduledDatabaseBackupExecution)->getFillable();

    expect($fillable)
        ->toContain('restore_status')
        ->toContain('restore_started_at')
        ->toContain('restore_finished_at')
        ->toContain('restore_message');
});

test('fillable includes core backup execution fields', function () {
    $fillable = (new ScheduledDatabaseBackupExecution)->getFillable();

    expect($fillable)
        ->toContain('scheduled_database_backup_id')
        ->toContain('status')
        ->toContain('message')
        ->toContain('filename')
        ->toContain('size')
        ->toContain('database_name')
        ->toContain('finished_at');
});

test('fillable includes s3 fields', function () {
    $fillable = (new ScheduledDatabaseBackupExecution)->getFillable();

    expect($fillable)
        ->toContain('s3_uploaded')
        ->toContain('s3_file_size')
        ->toContain('s3_object_key')
        ->toContain('s3_storage_deleted');
});

test('fillable includes verification fields', function () {
    $fillable = (new ScheduledDatabaseBackupExecution)->getFillable();

    expect($fillable)
        ->toContain('verification_status')
        ->toContain('verification_error')
        ->toContain('restore_test_status')
        ->toContain('restore_test_error')
        ->toContain('s3_integrity_status')
        ->toContain('s3_integrity_error');
});

// Casts Tests
test('restore_started_at is cast to datetime', function () {
    $casts = (new ScheduledDatabaseBackupExecution)->getCasts();
    expect($casts['restore_started_at'])->toBe('datetime');
});

test('restore_finished_at is cast to datetime', function () {
    $casts = (new ScheduledDatabaseBackupExecution)->getCasts();
    expect($casts['restore_finished_at'])->toBe('datetime');
});

test('s3_uploaded is cast to boolean', function () {
    $casts = (new ScheduledDatabaseBackupExecution)->getCasts();
    expect($casts['s3_uploaded'])->toBe('boolean');
});

test('is_encrypted is cast to boolean', function () {
    $casts = (new ScheduledDatabaseBackupExecution)->getCasts();
    expect($casts['is_encrypted'])->toBe('boolean');
});

test('finished_at is cast to datetime', function () {
    $casts = (new ScheduledDatabaseBackupExecution)->getCasts();
    expect($casts['finished_at'])->toBe('datetime');
});

// Relationship Tests
test('scheduledDatabaseBackup relationship returns belongsTo', function () {
    $relation = (new ScheduledDatabaseBackupExecution)->scheduledDatabaseBackup();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(ScheduledDatabaseBackup::class);
});

// Method Tests
test('isVerified returns true when verification_status is verified', function () {
    $execution = new ScheduledDatabaseBackupExecution;
    $execution->verification_status = 'verified';

    expect($execution->isVerified())->toBeTrue();
});

test('isVerified returns false for other statuses', function () {
    $execution = new ScheduledDatabaseBackupExecution;
    $execution->verification_status = 'pending';

    expect($execution->isVerified())->toBeFalse();
});

test('isRestoreTestPassed returns true when restore_test_status is success', function () {
    $execution = new ScheduledDatabaseBackupExecution;
    $execution->restore_test_status = 'success';

    expect($execution->isRestoreTestPassed())->toBeTrue();
});

test('isRestoreTestPassed returns false for other statuses', function () {
    $execution = new ScheduledDatabaseBackupExecution;
    $execution->restore_test_status = 'failed';

    expect($execution->isRestoreTestPassed())->toBeFalse();
});
