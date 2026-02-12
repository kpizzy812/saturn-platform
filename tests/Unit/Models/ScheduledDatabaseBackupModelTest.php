<?php

use App\Models\S3Storage;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\Team;

// Fillable Security Tests
test('fillable attributes are defined and not empty', function () {
    $backup = new ScheduledDatabaseBackup;
    expect($backup->getFillable())->not->toBeEmpty();
});

test('fillable does not include id or team_id', function () {
    $fillable = (new ScheduledDatabaseBackup)->getFillable();

    expect($fillable)
        ->not->toContain('id')
        ->not->toContain('team_id');
});

test('fillable does not include relationship fields', function () {
    $fillable = (new ScheduledDatabaseBackup)->getFillable();

    expect($fillable)
        ->not->toContain('database_type')
        ->not->toContain('database_id');
});

test('fillable does not include system-managed fields', function () {
    $fillable = (new ScheduledDatabaseBackup)->getFillable();

    expect($fillable)->not->toContain('last_restore_test_at');
});

test('fillable includes expected fields', function () {
    $fillable = (new ScheduledDatabaseBackup)->getFillable();

    expect($fillable)
        ->toContain('uuid')
        ->toContain('enabled')
        ->toContain('name')
        ->toContain('frequency')
        ->toContain('save_s3')
        ->toContain('s3_storage_id')
        ->toContain('backup_retention_period')
        ->toContain('databases_to_backup')
        ->toContain('dump_all')
        ->toContain('disable_local_backup')
        ->toContain('verify_after_backup')
        ->toContain('restore_test_enabled');
});

// Casts Tests
test('enabled is cast to boolean', function () {
    $casts = (new ScheduledDatabaseBackup)->getCasts();
    expect($casts['enabled'])->toBe('boolean');
});

test('save_s3 is cast to boolean', function () {
    $casts = (new ScheduledDatabaseBackup)->getCasts();
    expect($casts['save_s3'])->toBe('boolean');
});

test('dump_all is cast to boolean', function () {
    $casts = (new ScheduledDatabaseBackup)->getCasts();
    expect($casts['dump_all'])->toBe('boolean');
});

test('disable_local_backup is cast to boolean', function () {
    $casts = (new ScheduledDatabaseBackup)->getCasts();
    expect($casts['disable_local_backup'])->toBe('boolean');
});

test('verify_after_backup is cast to boolean', function () {
    $casts = (new ScheduledDatabaseBackup)->getCasts();
    expect($casts['verify_after_backup'])->toBe('boolean');
});

test('restore_test_enabled is cast to boolean', function () {
    $casts = (new ScheduledDatabaseBackup)->getCasts();
    expect($casts['restore_test_enabled'])->toBe('boolean');
});

test('last_restore_test_at is cast to datetime', function () {
    $casts = (new ScheduledDatabaseBackup)->getCasts();
    expect($casts['last_restore_test_at'])->toBe('datetime');
});

// Relationship Type Tests
test('team relationship returns belongsTo', function () {
    $relation = (new ScheduledDatabaseBackup)->team();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(Team::class);
});

test('database relationship returns morphTo', function () {
    $relation = (new ScheduledDatabaseBackup)->database();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test('latest_log relationship returns hasOne', function () {
    $relation = (new ScheduledDatabaseBackup)->latest_log();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class)
        ->and($relation->getRelated())->toBeInstanceOf(ScheduledDatabaseBackupExecution::class);
});

test('executions relationship returns hasMany', function () {
    $relation = (new ScheduledDatabaseBackup)->executions();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
        ->and($relation->getRelated())->toBeInstanceOf(ScheduledDatabaseBackupExecution::class);
});

test('s3 relationship returns belongsTo', function () {
    $relation = (new ScheduledDatabaseBackup)->s3();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and($relation->getRelated())->toBeInstanceOf(S3Storage::class);
});

// Method Existence Tests
test('server method exists', function () {
    expect(method_exists(new ScheduledDatabaseBackup, 'server'))->toBeTrue();
});

test('executionsPaginated method exists', function () {
    expect(method_exists(new ScheduledDatabaseBackup, 'executionsPaginated'))->toBeTrue();
});

test('get_last_days_backup_status method exists', function () {
    expect(method_exists(new ScheduledDatabaseBackup, 'get_last_days_backup_status'))->toBeTrue();
});

// Trait Tests
test('uses Auditable trait', function () {
    expect(class_uses_recursive(new ScheduledDatabaseBackup))->toContain(\App\Traits\Auditable::class);
});

test('uses LogsActivity trait', function () {
    expect(class_uses_recursive(new ScheduledDatabaseBackup))->toContain(\Spatie\Activitylog\Traits\LogsActivity::class);
});

// Activity Log Tests
test('getActivitylogOptions returns LogOptions', function () {
    $backup = new ScheduledDatabaseBackup;
    $options = $backup->getActivitylogOptions();

    expect($options)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
});

// Attribute Tests
test('name attribute works', function () {
    $backup = new ScheduledDatabaseBackup;
    $backup->name = 'Daily Postgres Backup';

    expect($backup->name)->toBe('Daily Postgres Backup');
});

test('frequency attribute works', function () {
    $backup = new ScheduledDatabaseBackup;
    $backup->frequency = '0 2 * * *';

    expect($backup->frequency)->toBe('0 2 * * *');
});
