<?php

use App\Models\StandaloneMariadb;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

// type() Tests
test('type returns standalone-mariadb', function () {
    $db = new StandaloneMariadb;
    expect($db->type())->toBe('standalone-mariadb');
});

// databaseType() Tests
test('databaseType accessor returns type value', function () {
    $db = new StandaloneMariadb;
    expect($db->database_type)->toBe('standalone-mariadb');
});

// Fillable Security Tests
test('fillable does not contain id', function () {
    $fillable = (new StandaloneMariadb)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable is not empty', function () {
    $fillable = (new StandaloneMariadb)->getFillable();

    expect($fillable)
        ->not->toBeEmpty()
        ->toBeArray();
});

test('fillable contains expected MariaDB fields', function () {
    $fillable = (new StandaloneMariadb)->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('mariadb_user')
        ->toContain('mariadb_password')
        ->toContain('mariadb_root_password')
        ->toContain('mariadb_database')
        ->toContain('mariadb_conf')
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
test('mariadb_password is cast to encrypted', function () {
    $casts = (new StandaloneMariadb)->getCasts();

    expect($casts['mariadb_password'])->toBe('encrypted');
});

test('restart_count is cast to integer', function () {
    $casts = (new StandaloneMariadb)->getCasts();

    expect($casts['restart_count'])->toBe('integer');
});

test('last_restart_at is cast to datetime', function () {
    $casts = (new StandaloneMariadb)->getCasts();

    expect($casts['last_restart_at'])->toBe('datetime');
});

test('last_restart_type is cast to string', function () {
    $casts = (new StandaloneMariadb)->getCasts();

    expect($casts['last_restart_type'])->toBe('string');
});

// Appends Tests
test('appends contains internal_db_url', function () {
    $db = new StandaloneMariadb;
    expect($db->getAppends())->toContain('internal_db_url');
});

test('appends contains external_db_url', function () {
    $db = new StandaloneMariadb;
    expect($db->getAppends())->toContain('external_db_url');
});

test('appends contains database_type', function () {
    $db = new StandaloneMariadb;
    expect($db->getAppends())->toContain('database_type');
});

test('appends contains server_status', function () {
    $db = new StandaloneMariadb;
    expect($db->getAppends())->toContain('server_status');
});

// Relationship Tests
test('environment relationship returns BelongsTo', function () {
    $db = new StandaloneMariadb;
    expect($db->environment())->toBeInstanceOf(BelongsTo::class);
});

test('persistentStorages relationship returns MorphMany', function () {
    $db = new StandaloneMariadb;
    expect($db->persistentStorages())->toBeInstanceOf(MorphMany::class);
});

test('fileStorages relationship returns MorphMany', function () {
    $db = new StandaloneMariadb;
    expect($db->fileStorages())->toBeInstanceOf(MorphMany::class);
});

test('destination relationship returns MorphTo', function () {
    $db = new StandaloneMariadb;
    expect($db->destination())->toBeInstanceOf(MorphTo::class);
});

test('scheduledBackups relationship returns MorphMany', function () {
    $db = new StandaloneMariadb;
    expect($db->scheduledBackups())->toBeInstanceOf(MorphMany::class);
});

test('environment_variables relationship returns MorphMany', function () {
    $db = new StandaloneMariadb;
    expect($db->environment_variables())->toBeInstanceOf(MorphMany::class);
});

test('tags relationship returns MorphToMany', function () {
    $db = new StandaloneMariadb;
    expect($db->tags())->toBeInstanceOf(MorphToMany::class);
});

test('runtime_environment_variables relationship returns MorphMany', function () {
    $db = new StandaloneMariadb;
    expect($db->runtime_environment_variables())->toBeInstanceOf(MorphMany::class);
});

test('sslCertificates relationship returns MorphMany', function () {
    $db = new StandaloneMariadb;
    expect($db->sslCertificates())->toBeInstanceOf(MorphMany::class);
});

// isRunning Tests
test('isRunning returns true when status contains running', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['status' => 'running:healthy'], true);
    expect($db->isRunning())->toBeTrue();
});

test('isRunning returns true when status is running:unhealthy', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['status' => 'running:unhealthy'], true);
    expect($db->isRunning())->toBeTrue();
});

test('isRunning returns false when status is exited', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['status' => 'exited:0'], true);
    expect($db->isRunning())->toBeFalse();
});

// isExited Tests
test('isExited returns true when status starts with exited', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['status' => 'exited:0'], true);
    expect($db->isExited())->toBeTrue();
});

test('isExited returns false when status is running', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['status' => 'running:healthy'], true);
    expect($db->isExited())->toBeFalse();
});

// Status accessor Tests
test('status setter normalizes running(healthy) to running:healthy', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['status' => 'test'], true);
    $db->status = 'running(healthy)';
    expect($db->getAttributes()['status'])->toBe('running:healthy');
});

test('status setter preserves running:healthy format', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['status' => 'test'], true);
    $db->status = 'running:healthy';
    expect($db->getAttributes()['status'])->toBe('running:healthy');
});

test('status setter converts running to running:unhealthy', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['status' => 'test'], true);
    $db->status = 'running';
    expect($db->getAttributes()['status'])->toBe('running:unhealthy');
});

test('status getter normalizes running(healthy) to running:healthy', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['status' => 'running(healthy)'], true);
    expect($db->status)->toBe('running:healthy');
});

test('status getter normalizes exited to exited:unhealthy', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['status' => 'exited'], true);
    expect($db->status)->toBe('exited:unhealthy');
});

// isLogDrainEnabled Tests
test('isLogDrainEnabled returns false when not set', function () {
    $db = new StandaloneMariadb;
    expect($db->isLogDrainEnabled())->toBeFalse();
});

test('isLogDrainEnabled returns value of is_log_drain_enabled', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['is_log_drain_enabled' => true], true);
    expect($db->isLogDrainEnabled())->toBeTrue();
});

// portsMappings accessor Tests
test('portsMappings setter converts empty string to null', function () {
    $db = new StandaloneMariadb;
    $db->ports_mappings = '';
    expect($db->getAttributes()['ports_mappings'])->toBeNull();
});

test('portsMappings setter preserves non-empty string', function () {
    $db = new StandaloneMariadb;
    $db->ports_mappings = '3306:3306';
    expect($db->getAttributes()['ports_mappings'])->toBe('3306:3306');
});

// portsMappingsArray accessor Tests
test('portsMappingsArray returns empty array when ports_mappings is null', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['ports_mappings' => null], true);
    expect($db->portsMappingsArray)->toBeArray()->toBeEmpty();
});

test('portsMappingsArray returns array when ports_mappings is comma string', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['ports_mappings' => '3306:3306,3307:3307'], true);
    expect($db->portsMappingsArray)
        ->toBeArray()
        ->toHaveCount(2)
        ->toContain('3306:3306')
        ->toContain('3307:3307');
});

// SoftDeletes Tests
test('trashed method exists', function () {
    $db = new StandaloneMariadb;
    expect(method_exists($db, 'trashed'))->toBeTrue();
});

test('restore method exists', function () {
    $db = new StandaloneMariadb;
    expect(method_exists($db, 'restore'))->toBeTrue();
});

// isBackupSolutionAvailable Tests
test('isBackupSolutionAvailable returns true', function () {
    $db = new StandaloneMariadb;
    expect($db->isBackupSolutionAvailable())->toBeTrue();
});

// Traits Tests
test('model uses LogsActivity trait', function () {
    $db = new StandaloneMariadb;
    expect(class_uses_recursive($db))
        ->toHaveKey('Spatie\Activitylog\Traits\LogsActivity');
});

test('model uses Auditable trait', function () {
    $db = new StandaloneMariadb;
    expect(class_uses_recursive($db))
        ->toHaveKey('App\Traits\Auditable');
});

test('model uses HasSafeStringAttribute trait', function () {
    $db = new StandaloneMariadb;
    expect(class_uses_recursive($db))
        ->toHaveKey('App\Traits\HasSafeStringAttribute');
});

test('model uses SoftDeletes trait', function () {
    $db = new StandaloneMariadb;
    expect(class_uses_recursive($db))
        ->toHaveKey('Illuminate\Database\Eloquent\SoftDeletes');
});

test('model uses ValidatesPublicPort trait', function () {
    $db = new StandaloneMariadb;
    expect(class_uses_recursive($db))
        ->toHaveKey('App\Traits\ValidatesPublicPort');
});

// workdir Tests
test('workdir returns path ending with uuid', function () {
    $db = new StandaloneMariadb;
    $db->uuid = 'test-uuid-123';
    expect($db->workdir())->toEndWith('/test-uuid-123');
});

// realStatus Tests
test('realStatus returns raw status value', function () {
    $db = new StandaloneMariadb;
    $db->setRawAttributes(['status' => 'running(healthy)'], true);
    expect($db->realStatus())->toBe('running(healthy)');
});

// Additional Method Tests
test('project returns environment project', function () {
    $db = new StandaloneMariadb;
    $project = (object) ['id' => 1, 'name' => 'Test Project'];
    $db->environment = (object) ['project' => $project];
    expect($db->project())->toBe($project);
});

test('team returns environment project team', function () {
    $db = new StandaloneMariadb;
    $team = (object) ['id' => 1, 'name' => 'Test Team'];
    $db->environment = (object) ['project' => (object) ['team' => $team]];
    expect($db->team())->toBe($team);
});
