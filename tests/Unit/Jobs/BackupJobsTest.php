<?php

/**
 * Unit tests for Backup/Restore Jobs.
 *
 * Tests cover:
 * - BackupVerificationJob: ShouldBeEncrypted, ShouldQueue, tries, timeout, queue, backoff, checksum
 * - DatabaseRestoreJob: ShouldBeEncrypted, ShouldQueue, tries, maxExceptions, timeout, queue, backoff
 */

use App\Jobs\BackupVerificationJob;
use App\Jobs\DatabaseRestoreJob;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery;

afterEach(fn () => Mockery::close());

// ─── BackupVerificationJob ────────────────────────────────────────────────────

test('BackupVerificationJob class exists', function () {
    expect(class_exists(BackupVerificationJob::class))->toBeTrue();
});

test('BackupVerificationJob implements ShouldQueue', function () {
    expect(is_a(BackupVerificationJob::class, ShouldQueue::class, allow_string: true))->toBeTrue();
});

test('BackupVerificationJob implements ShouldBeEncrypted', function () {
    expect(is_a(BackupVerificationJob::class, ShouldBeEncrypted::class, allow_string: true))->toBeTrue();
});

test('BackupVerificationJob has handle method', function () {
    expect(method_exists(BackupVerificationJob::class, 'handle'))->toBeTrue();
});

test('BackupVerificationJob tries is 2', function () {
    $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();
    $job = new BackupVerificationJob($execution);
    expect($job->tries)->toBe(2);
});

test('BackupVerificationJob timeout is 600 seconds', function () {
    $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();
    $job = new BackupVerificationJob($execution);
    expect($job->timeout)->toBe(600);
});

test('BackupVerificationJob runs on high queue', function () {
    $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();
    $job = new BackupVerificationJob($execution);
    expect($job->queue)->toBe('high');
});

test('BackupVerificationJob backoff method returns 30 and 60 seconds', function () {
    $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();
    $job = new BackupVerificationJob($execution);
    expect($job->backoff())->toBe([30, 60]);
});

test('BackupVerificationJob default checksum algorithm is md5', function () {
    $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();
    $job = new BackupVerificationJob($execution);
    expect($job->checksumAlgorithm)->toBe('md5');
});

test('BackupVerificationJob accepts custom checksum algorithm', function () {
    $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();
    $job = new BackupVerificationJob($execution, 'sha256');
    expect($job->checksumAlgorithm)->toBe('sha256');
});

// ─── DatabaseRestoreJob ───────────────────────────────────────────────────────

test('DatabaseRestoreJob class exists', function () {
    expect(class_exists(DatabaseRestoreJob::class))->toBeTrue();
});

test('DatabaseRestoreJob implements ShouldQueue', function () {
    expect(is_a(DatabaseRestoreJob::class, ShouldQueue::class, allow_string: true))->toBeTrue();
});

test('DatabaseRestoreJob implements ShouldBeEncrypted', function () {
    expect(is_a(DatabaseRestoreJob::class, ShouldBeEncrypted::class, allow_string: true))->toBeTrue();
});

test('DatabaseRestoreJob has handle method', function () {
    expect(method_exists(DatabaseRestoreJob::class, 'handle'))->toBeTrue();
});

test('DatabaseRestoreJob tries is 3', function () {
    $instance = (new ReflectionClass(DatabaseRestoreJob::class))->newInstanceWithoutConstructor();
    expect($instance->tries)->toBe(3);
});

test('DatabaseRestoreJob maxExceptions is 2', function () {
    $instance = (new ReflectionClass(DatabaseRestoreJob::class))->newInstanceWithoutConstructor();
    expect($instance->maxExceptions)->toBe(2);
});

test('DatabaseRestoreJob default timeout is 3600 seconds', function () {
    $instance = (new ReflectionClass(DatabaseRestoreJob::class))->newInstanceWithoutConstructor();
    expect($instance->timeout)->toBe(3600);
});

test('DatabaseRestoreJob runs on high queue when constructed', function () {
    $backup = Mockery::mock(ScheduledDatabaseBackup::class)->makePartial();
    $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();
    $job = new DatabaseRestoreJob($backup, $execution);
    expect($job->queue)->toBe('high');
});

test('DatabaseRestoreJob backoff method returns 60 and 120 seconds', function () {
    $instance = (new ReflectionClass(DatabaseRestoreJob::class))->newInstanceWithoutConstructor();
    expect($instance->backoff())->toBe([60, 120]);
});

test('DatabaseRestoreJob timeout uses backup timeout when set', function () {
    $backup = Mockery::mock(ScheduledDatabaseBackup::class)->makePartial();
    $backup->timeout = 300;
    $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();
    $job = new DatabaseRestoreJob($backup, $execution);
    expect($job->timeout)->toBe(300);
});

test('DatabaseRestoreJob timeout defaults to 3600 when backup timeout is null', function () {
    $backup = Mockery::mock(ScheduledDatabaseBackup::class)->makePartial();
    // $backup->timeout is null by default (not in attributes)
    $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();
    $job = new DatabaseRestoreJob($backup, $execution);
    expect($job->timeout)->toBe(3600);
});

test('DatabaseRestoreJob minimum timeout is 60 seconds', function () {
    $backup = Mockery::mock(ScheduledDatabaseBackup::class)->makePartial();
    $backup->timeout = 30; // below minimum of 60
    $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();
    $job = new DatabaseRestoreJob($backup, $execution);
    expect($job->timeout)->toBe(60);
});
