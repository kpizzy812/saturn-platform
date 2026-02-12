<?php

use App\Models\StandaloneMongodb;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

// type() Tests
test('type returns standalone-mongodb', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->type())->toBe('standalone-mongodb');
});

// databaseType() Tests
test('databaseType accessor returns type value', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->database_type)->toBe('standalone-mongodb');
});

// isRunning Tests
test('isRunning returns true when status contains running', function () {
    $mongo = new StandaloneMongodb;
    $mongo->setRawAttributes(['status' => 'running:healthy'], true);
    expect($mongo->isRunning())->toBeTrue();
});

test('isRunning returns true when status is running', function () {
    $mongo = new StandaloneMongodb;
    $mongo->setRawAttributes(['status' => 'running'], true);
    expect($mongo->isRunning())->toBeTrue();
});

test('isRunning returns false when status is exited', function () {
    $mongo = new StandaloneMongodb;
    $mongo->setRawAttributes(['status' => 'exited'], true);
    expect($mongo->isRunning())->toBeFalse();
});

test('isRunning returns false when status is stopped', function () {
    $mongo = new StandaloneMongodb;
    $mongo->setRawAttributes(['status' => 'stopped'], true);
    expect($mongo->isRunning())->toBeFalse();
});

// isExited Tests
test('isExited returns true when status starts with exited', function () {
    $mongo = new StandaloneMongodb;
    $mongo->setRawAttributes(['status' => 'exited'], true);
    expect($mongo->isExited())->toBeTrue();
});

test('isExited returns true when status is exited with code', function () {
    $mongo = new StandaloneMongodb;
    $mongo->setRawAttributes(['status' => 'exited:0'], true);
    expect($mongo->isExited())->toBeTrue();
});

test('isExited returns false when status is running', function () {
    $mongo = new StandaloneMongodb;
    $mongo->setRawAttributes(['status' => 'running'], true);
    expect($mongo->isExited())->toBeFalse();
});

// status() Attribute Tests
test('status accessor normalizes running(healthy) to running:healthy', function () {
    $mongo = new StandaloneMongodb;
    $mongo->status = 'running(healthy)';
    expect($mongo->status)->toBe('running:healthy');
});

test('status accessor normalizes running to running:unhealthy', function () {
    $mongo = new StandaloneMongodb;
    $mongo->status = 'running';
    expect($mongo->status)->toBe('running:unhealthy');
});

test('status accessor preserves running:healthy format', function () {
    $mongo = new StandaloneMongodb;
    $mongo->status = 'running:healthy';
    expect($mongo->status)->toBe('running:healthy');
});

test('status accessor normalizes exited(0) to exited:0', function () {
    $mongo = new StandaloneMongodb;
    $mongo->status = 'exited(0)';
    expect($mongo->status)->toBe('exited:0');
});

test('status accessor normalizes exited to exited:unhealthy', function () {
    $mongo = new StandaloneMongodb;
    $mongo->status = 'exited';
    expect($mongo->status)->toBe('exited:unhealthy');
});

// workdir() Tests
test('workdir returns path ending with uuid', function () {
    $mongo = new StandaloneMongodb;
    $mongo->uuid = 'mongodb-uuid-123';
    expect($mongo->workdir())->toEndWith('mongodb-uuid-123');
});

test('workdir contains database configuration directory', function () {
    $mongo = new StandaloneMongodb;
    $mongo->uuid = 'mongodb-uuid-456';
    $workdir = $mongo->workdir();
    expect($workdir)->toContain('/');
    expect($workdir)->toEndWith('mongodb-uuid-456');
});

// isLogDrainEnabled Tests
test('isLogDrainEnabled returns true when enabled', function () {
    $mongo = new StandaloneMongodb;
    $mongo->is_log_drain_enabled = true;
    expect($mongo->isLogDrainEnabled())->toBeTrue();
});

test('isLogDrainEnabled returns false when disabled', function () {
    $mongo = new StandaloneMongodb;
    $mongo->is_log_drain_enabled = false;
    expect($mongo->isLogDrainEnabled())->toBeFalse();
});

test('isLogDrainEnabled returns false when null', function () {
    $mongo = new StandaloneMongodb;
    $mongo->is_log_drain_enabled = null;
    expect($mongo->isLogDrainEnabled())->toBeFalse();
});

// portsMappings() Attribute Tests
test('portsMappings setter converts empty string to null', function () {
    $mongo = new StandaloneMongodb;
    $mongo->ports_mappings = '';
    expect($mongo->ports_mappings)->toBeNull();
});

test('portsMappings setter preserves non-empty value', function () {
    $mongo = new StandaloneMongodb;
    $mongo->ports_mappings = '27017:27017';
    expect($mongo->ports_mappings)->toBe('27017:27017');
});

test('portsMappings setter preserves null', function () {
    $mongo = new StandaloneMongodb;
    $mongo->ports_mappings = null;
    expect($mongo->ports_mappings)->toBeNull();
});

// portsMappingsArray() Attribute Tests
test('portsMappingsArray returns empty array when null', function () {
    $mongo = new StandaloneMongodb;
    $mongo->ports_mappings = null;
    expect($mongo->ports_mappings_array)->toBe([]);
});

test('portsMappingsArray splits comma-separated values', function () {
    $mongo = new StandaloneMongodb;
    $mongo->ports_mappings = '27017:27017,27018:27018';
    expect($mongo->ports_mappings_array)->toBe(['27017:27017', '27018:27018']);
});

test('portsMappingsArray returns single value array for single mapping', function () {
    $mongo = new StandaloneMongodb;
    $mongo->ports_mappings = '27017:27017';
    expect($mongo->ports_mappings_array)->toBe(['27017:27017']);
});

// Relationship Tests
test('environment relationship returns BelongsTo', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->environment())->toBeInstanceOf(BelongsTo::class);
});

test('persistentStorages relationship returns MorphMany', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->persistentStorages())->toBeInstanceOf(MorphMany::class);
});

test('fileStorages relationship returns MorphMany', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->fileStorages())->toBeInstanceOf(MorphMany::class);
});

test('destination relationship returns MorphTo', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->destination())->toBeInstanceOf(MorphTo::class);
});

test('scheduledBackups relationship returns MorphMany', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->scheduledBackups())->toBeInstanceOf(MorphMany::class);
});

test('environment_variables relationship returns MorphMany', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->environment_variables())->toBeInstanceOf(MorphMany::class);
});

test('tags relationship returns MorphToMany', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->tags())->toBeInstanceOf(MorphToMany::class);
});

test('runtime_environment_variables relationship returns MorphMany', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->runtime_environment_variables())->toBeInstanceOf(MorphMany::class);
});

test('sslCertificates relationship returns MorphMany', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->sslCertificates())->toBeInstanceOf(MorphMany::class);
});

// SoftDeletes Tests
test('trashed method exists from SoftDeletes trait', function () {
    $mongo = new StandaloneMongodb;
    expect(method_exists($mongo, 'trashed'))->toBeTrue();
});

test('restore method exists from SoftDeletes trait', function () {
    $mongo = new StandaloneMongodb;
    expect(method_exists($mongo, 'restore'))->toBeTrue();
});

test('forceDelete method exists from SoftDeletes trait', function () {
    $mongo = new StandaloneMongodb;
    expect(method_exists($mongo, 'forceDelete'))->toBeTrue();
});

// Fillable Tests
test('fillable array includes uuid', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getFillable())->toContain('uuid');
});

test('fillable array includes name', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getFillable())->toContain('name');
});

test('fillable array includes description', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getFillable())->toContain('description');
});

test('fillable array includes mongo_initdb_root_username', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getFillable())->toContain('mongo_initdb_root_username');
});

test('fillable array includes mongo_initdb_root_password', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getFillable())->toContain('mongo_initdb_root_password');
});

test('fillable array includes mongo_db', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getFillable())->toContain('mongo_db');
});

test('fillable array includes status', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getFillable())->toContain('status');
});

test('fillable array includes restart_count', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getFillable())->toContain('restart_count');
});

test('fillable array includes last_restart_at', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getFillable())->toContain('last_restart_at');
});

test('fillable array includes last_restart_type', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getFillable())->toContain('last_restart_type');
});

// Casts Tests
test('restart_count is cast to integer', function () {
    $mongo = new StandaloneMongodb;
    $casts = $mongo->getCasts();
    expect($casts)->toHaveKey('restart_count', 'integer');
});

test('last_restart_at is cast to datetime', function () {
    $mongo = new StandaloneMongodb;
    $casts = $mongo->getCasts();
    expect($casts)->toHaveKey('last_restart_at', 'datetime');
});

test('last_restart_type is cast to string', function () {
    $mongo = new StandaloneMongodb;
    $casts = $mongo->getCasts();
    expect($casts)->toHaveKey('last_restart_type', 'string');
});

// Appends Tests
test('appends includes internal_db_url', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getAppends())->toContain('internal_db_url');
});

test('appends includes external_db_url', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getAppends())->toContain('external_db_url');
});

test('appends includes database_type', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getAppends())->toContain('database_type');
});

test('appends includes server_status', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->getAppends())->toContain('server_status');
});

// Additional Method Tests
test('isBackupSolutionAvailable returns true', function () {
    $mongo = new StandaloneMongodb;
    expect($mongo->isBackupSolutionAvailable())->toBeTrue();
});

test('project returns environment project', function () {
    $mongo = new StandaloneMongodb;
    $project = (object) ['id' => 1, 'name' => 'Test Project'];
    $mongo->environment = (object) ['project' => $project];
    expect($mongo->project())->toBe($project);
});

test('project returns null when no environment', function () {
    $mongo = new StandaloneMongodb;
    $mongo->environment = null;
    expect($mongo->project())->toBeNull();
});

test('team returns environment project team', function () {
    $mongo = new StandaloneMongodb;
    $team = (object) ['id' => 1, 'name' => 'Test Team'];
    $mongo->environment = (object) ['project' => (object) ['team' => $team]];
    expect($mongo->team())->toBe($team);
});

test('team returns null when no environment', function () {
    $mongo = new StandaloneMongodb;
    $mongo->environment = null;
    expect($mongo->team())->toBeNull();
});

test('realStatus returns raw status value', function () {
    $mongo = new StandaloneMongodb;
    $mongo->setRawAttributes(['status' => 'running(healthy)'], true);
    expect($mongo->realStatus())->toBe('running(healthy)');
});
