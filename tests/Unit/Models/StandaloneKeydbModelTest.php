<?php

use App\Models\StandaloneKeydb;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

// type() Tests
test('type returns standalone-keydb', function () {
    $db = new StandaloneKeydb;
    expect($db->type())->toBe('standalone-keydb');
});

// databaseType() Tests
test('databaseType accessor returns type value', function () {
    $db = new StandaloneKeydb;
    expect($db->database_type)->toBe('standalone-keydb');
});

// Fillable Security Tests
test('fillable does not contain id', function () {
    $fillable = (new StandaloneKeydb)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable is not empty', function () {
    $fillable = (new StandaloneKeydb)->getFillable();

    expect($fillable)
        ->not->toBeEmpty()
        ->toBeArray();
});

test('fillable contains expected KeyDB fields', function () {
    $fillable = (new StandaloneKeydb)->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('keydb_password')
        ->toContain('keydb_conf')
        ->toContain('image')
        ->toContain('status')
        ->toContain('is_public')
        ->toContain('public_port')
        ->toContain('ports_mappings')
        ->toContain('limits_memory')
        ->toContain('environment_id')
        ->toContain('destination_id')
        ->toContain('destination_type')
        ->toContain('is_log_drain_enabled')
        ->toContain('restart_count')
        ->toContain('last_restart_at')
        ->toContain('last_restart_type');
});

// Casts Tests
test('keydb_password is cast to encrypted', function () {
    $casts = (new StandaloneKeydb)->getCasts();

    expect($casts['keydb_password'])->toBe('encrypted');
});

test('restart_count is cast to integer', function () {
    $casts = (new StandaloneKeydb)->getCasts();

    expect($casts['restart_count'])->toBe('integer');
});

test('last_restart_at is cast to datetime', function () {
    $casts = (new StandaloneKeydb)->getCasts();

    expect($casts['last_restart_at'])->toBe('datetime');
});

test('last_restart_type is cast to string', function () {
    $casts = (new StandaloneKeydb)->getCasts();

    expect($casts['last_restart_type'])->toBe('string');
});

// Appends Tests
test('appends contains internal_db_url', function () {
    $db = new StandaloneKeydb;
    expect($db->getAppends())->toContain('internal_db_url');
});

test('appends contains external_db_url', function () {
    $db = new StandaloneKeydb;
    expect($db->getAppends())->toContain('external_db_url');
});

test('appends contains server_status', function () {
    $db = new StandaloneKeydb;
    expect($db->getAppends())->toContain('server_status');
});

// Relationship Tests
test('environment relationship returns BelongsTo', function () {
    $db = new StandaloneKeydb;
    expect($db->environment())->toBeInstanceOf(BelongsTo::class);
});

test('persistentStorages relationship returns MorphMany', function () {
    $db = new StandaloneKeydb;
    expect($db->persistentStorages())->toBeInstanceOf(MorphMany::class);
});

test('fileStorages relationship returns MorphMany', function () {
    $db = new StandaloneKeydb;
    expect($db->fileStorages())->toBeInstanceOf(MorphMany::class);
});

test('destination relationship returns MorphTo', function () {
    $db = new StandaloneKeydb;
    expect($db->destination())->toBeInstanceOf(MorphTo::class);
});

test('scheduledBackups relationship returns MorphMany', function () {
    $db = new StandaloneKeydb;
    expect($db->scheduledBackups())->toBeInstanceOf(MorphMany::class);
});

test('environment_variables relationship returns MorphMany', function () {
    $db = new StandaloneKeydb;
    expect($db->environment_variables())->toBeInstanceOf(MorphMany::class);
});

test('tags relationship returns MorphToMany', function () {
    $db = new StandaloneKeydb;
    expect($db->tags())->toBeInstanceOf(MorphToMany::class);
});

test('runtime_environment_variables relationship returns MorphMany', function () {
    $db = new StandaloneKeydb;
    expect($db->runtime_environment_variables())->toBeInstanceOf(MorphMany::class);
});

test('sslCertificates relationship returns MorphMany', function () {
    $db = new StandaloneKeydb;
    expect($db->sslCertificates())->toBeInstanceOf(MorphMany::class);
});

// isRunning Tests
test('isRunning returns true when status contains running', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['status' => 'running:healthy'], true);
    expect($db->isRunning())->toBeTrue();
});

test('isRunning returns true when status is running:unhealthy', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['status' => 'running:unhealthy'], true);
    expect($db->isRunning())->toBeTrue();
});

test('isRunning returns false when status is exited', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['status' => 'exited:0'], true);
    expect($db->isRunning())->toBeFalse();
});

// isExited Tests
test('isExited returns true when status starts with exited', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['status' => 'exited:0'], true);
    expect($db->isExited())->toBeTrue();
});

test('isExited returns false when status is running', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['status' => 'running:healthy'], true);
    expect($db->isExited())->toBeFalse();
});

// Status accessor Tests
test('status setter normalizes running(healthy) to running:healthy', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['status' => 'test'], true);
    $db->status = 'running(healthy)';
    expect($db->getAttributes()['status'])->toBe('running:healthy');
});

test('status setter preserves running:healthy format', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['status' => 'test'], true);
    $db->status = 'running:healthy';
    expect($db->getAttributes()['status'])->toBe('running:healthy');
});

test('status setter converts running to running:unhealthy', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['status' => 'test'], true);
    $db->status = 'running';
    expect($db->getAttributes()['status'])->toBe('running:unhealthy');
});

test('status getter normalizes running(healthy) to running:healthy', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['status' => 'running(healthy)'], true);
    expect($db->status)->toBe('running:healthy');
});

test('status getter normalizes exited to exited:unhealthy', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['status' => 'exited'], true);
    expect($db->status)->toBe('exited:unhealthy');
});

// isLogDrainEnabled Tests
test('isLogDrainEnabled returns false when not set', function () {
    $db = new StandaloneKeydb;
    expect($db->isLogDrainEnabled())->toBeFalse();
});

test('isLogDrainEnabled returns value of is_log_drain_enabled', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['is_log_drain_enabled' => true], true);
    expect($db->isLogDrainEnabled())->toBeTrue();
});

// portsMappings accessor Tests
test('portsMappings setter converts empty string to null', function () {
    $db = new StandaloneKeydb;
    $db->ports_mappings = '';
    expect($db->getAttributes()['ports_mappings'])->toBeNull();
});

test('portsMappings setter preserves non-empty string', function () {
    $db = new StandaloneKeydb;
    $db->ports_mappings = '6379:6379';
    expect($db->getAttributes()['ports_mappings'])->toBe('6379:6379');
});

// portsMappingsArray accessor Tests
test('portsMappingsArray returns empty array when ports_mappings is null', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['ports_mappings' => null], true);
    expect($db->portsMappingsArray)->toBeArray()->toBeEmpty();
});

test('portsMappingsArray returns array when ports_mappings is comma string', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['ports_mappings' => '6379:6379,6380:6380'], true);
    expect($db->portsMappingsArray)
        ->toBeArray()
        ->toHaveCount(2)
        ->toContain('6379:6379')
        ->toContain('6380:6380');
});

// SoftDeletes Tests
test('trashed method exists', function () {
    $db = new StandaloneKeydb;
    expect(method_exists($db, 'trashed'))->toBeTrue();
});

test('restore method exists', function () {
    $db = new StandaloneKeydb;
    expect(method_exists($db, 'restore'))->toBeTrue();
});

// isBackupSolutionAvailable Tests
test('isBackupSolutionAvailable returns false', function () {
    $db = new StandaloneKeydb;
    expect($db->isBackupSolutionAvailable())->toBeFalse();
});

// Traits Tests
test('model uses LogsActivity trait', function () {
    $db = new StandaloneKeydb;
    expect(class_uses_recursive($db))
        ->toHaveKey('Spatie\Activitylog\Traits\LogsActivity');
});

test('model uses Auditable trait', function () {
    $db = new StandaloneKeydb;
    expect(class_uses_recursive($db))
        ->toHaveKey('App\Traits\Auditable');
});

test('model uses HasSafeStringAttribute trait', function () {
    $db = new StandaloneKeydb;
    expect(class_uses_recursive($db))
        ->toHaveKey('App\Traits\HasSafeStringAttribute');
});

test('model uses SoftDeletes trait', function () {
    $db = new StandaloneKeydb;
    expect(class_uses_recursive($db))
        ->toHaveKey('Illuminate\Database\Eloquent\SoftDeletes');
});

test('model uses ValidatesPublicPort trait', function () {
    $db = new StandaloneKeydb;
    expect(class_uses_recursive($db))
        ->toHaveKey('App\Traits\ValidatesPublicPort');
});

// workdir Tests
test('workdir returns path ending with uuid', function () {
    $db = new StandaloneKeydb;
    $db->uuid = 'test-uuid-123';
    expect($db->workdir())->toEndWith('/test-uuid-123');
});

// realStatus Tests
test('realStatus returns raw status value', function () {
    $db = new StandaloneKeydb;
    $db->setRawAttributes(['status' => 'running(healthy)'], true);
    expect($db->realStatus())->toBe('running(healthy)');
});

// Additional Method Tests
test('project returns environment project', function () {
    $db = new StandaloneKeydb;
    $project = (object) ['id' => 1, 'name' => 'Test Project'];
    $db->environment = (object) ['project' => $project];
    expect($db->project())->toBe($project);
});

test('team returns environment project team', function () {
    $db = new StandaloneKeydb;
    $team = (object) ['id' => 1, 'name' => 'Test Team'];
    $db->environment = (object) ['project' => (object) ['team' => $team]];
    expect($db->team())->toBe($team);
});
