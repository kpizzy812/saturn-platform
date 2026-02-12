<?php

use App\Models\StandaloneRedis;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

// type() Tests
test('type returns standalone-redis', function () {
    $redis = new StandaloneRedis;
    expect($redis->type())->toBe('standalone-redis');
});

// databaseType() Tests
test('databaseType accessor returns type value', function () {
    $redis = new StandaloneRedis;
    expect($redis->database_type)->toBe('standalone-redis');
});

// isRunning Tests
test('isRunning returns true when status contains running', function () {
    $redis = new StandaloneRedis;
    $redis->setRawAttributes(['status' => 'running:healthy'], true);
    expect($redis->isRunning())->toBeTrue();
});

test('isRunning returns true when status is running', function () {
    $redis = new StandaloneRedis;
    $redis->setRawAttributes(['status' => 'running'], true);
    expect($redis->isRunning())->toBeTrue();
});

test('isRunning returns false when status is exited', function () {
    $redis = new StandaloneRedis;
    $redis->setRawAttributes(['status' => 'exited'], true);
    expect($redis->isRunning())->toBeFalse();
});

test('isRunning returns false when status is stopped', function () {
    $redis = new StandaloneRedis;
    $redis->setRawAttributes(['status' => 'stopped'], true);
    expect($redis->isRunning())->toBeFalse();
});

// isExited Tests
test('isExited returns true when status starts with exited', function () {
    $redis = new StandaloneRedis;
    $redis->setRawAttributes(['status' => 'exited'], true);
    expect($redis->isExited())->toBeTrue();
});

test('isExited returns true when status is exited with code', function () {
    $redis = new StandaloneRedis;
    $redis->setRawAttributes(['status' => 'exited:0'], true);
    expect($redis->isExited())->toBeTrue();
});

test('isExited returns false when status is running', function () {
    $redis = new StandaloneRedis;
    $redis->setRawAttributes(['status' => 'running'], true);
    expect($redis->isExited())->toBeFalse();
});

// status() Attribute Tests
test('status accessor normalizes running(healthy) to running:healthy', function () {
    $redis = new StandaloneRedis;
    $redis->status = 'running(healthy)';
    expect($redis->status)->toBe('running:healthy');
});

test('status accessor normalizes running to running:unhealthy', function () {
    $redis = new StandaloneRedis;
    $redis->status = 'running';
    expect($redis->status)->toBe('running:unhealthy');
});

test('status accessor preserves running:healthy format', function () {
    $redis = new StandaloneRedis;
    $redis->status = 'running:healthy';
    expect($redis->status)->toBe('running:healthy');
});

test('status accessor normalizes exited(0) to exited:0', function () {
    $redis = new StandaloneRedis;
    $redis->status = 'exited(0)';
    expect($redis->status)->toBe('exited:0');
});

test('status accessor normalizes exited to exited:unhealthy', function () {
    $redis = new StandaloneRedis;
    $redis->status = 'exited';
    expect($redis->status)->toBe('exited:unhealthy');
});

// workdir() Tests
test('workdir returns path ending with uuid', function () {
    $redis = new StandaloneRedis;
    $redis->uuid = 'redis-uuid-123';
    expect($redis->workdir())->toEndWith('redis-uuid-123');
});

test('workdir contains database configuration directory', function () {
    $redis = new StandaloneRedis;
    $redis->uuid = 'redis-uuid-456';
    $workdir = $redis->workdir();
    expect($workdir)->toContain('/');
    expect($workdir)->toEndWith('redis-uuid-456');
});

// isLogDrainEnabled Tests
test('isLogDrainEnabled returns true when enabled', function () {
    $redis = new StandaloneRedis;
    $redis->is_log_drain_enabled = true;
    expect($redis->isLogDrainEnabled())->toBeTrue();
});

test('isLogDrainEnabled returns false when disabled', function () {
    $redis = new StandaloneRedis;
    $redis->is_log_drain_enabled = false;
    expect($redis->isLogDrainEnabled())->toBeFalse();
});

test('isLogDrainEnabled returns false when null', function () {
    $redis = new StandaloneRedis;
    $redis->is_log_drain_enabled = null;
    expect($redis->isLogDrainEnabled())->toBeFalse();
});

// portsMappings() Attribute Tests
test('portsMappings setter converts empty string to null', function () {
    $redis = new StandaloneRedis;
    $redis->ports_mappings = '';
    expect($redis->ports_mappings)->toBeNull();
});

test('portsMappings setter preserves non-empty value', function () {
    $redis = new StandaloneRedis;
    $redis->ports_mappings = '6379:6379';
    expect($redis->ports_mappings)->toBe('6379:6379');
});

test('portsMappings setter preserves null', function () {
    $redis = new StandaloneRedis;
    $redis->ports_mappings = null;
    expect($redis->ports_mappings)->toBeNull();
});

// portsMappingsArray() Attribute Tests
test('portsMappingsArray returns empty array when null', function () {
    $redis = new StandaloneRedis;
    $redis->ports_mappings = null;
    expect($redis->ports_mappings_array)->toBe([]);
});

test('portsMappingsArray splits comma-separated values', function () {
    $redis = new StandaloneRedis;
    $redis->ports_mappings = '6379:6379,6380:6380';
    expect($redis->ports_mappings_array)->toBe(['6379:6379', '6380:6380']);
});

test('portsMappingsArray returns single value array for single mapping', function () {
    $redis = new StandaloneRedis;
    $redis->ports_mappings = '6379:6379';
    expect($redis->ports_mappings_array)->toBe(['6379:6379']);
});

// Relationship Tests
test('environment relationship returns BelongsTo', function () {
    $redis = new StandaloneRedis;
    expect($redis->environment())->toBeInstanceOf(BelongsTo::class);
});

test('persistentStorages relationship returns MorphMany', function () {
    $redis = new StandaloneRedis;
    expect($redis->persistentStorages())->toBeInstanceOf(MorphMany::class);
});

test('fileStorages relationship returns MorphMany', function () {
    $redis = new StandaloneRedis;
    expect($redis->fileStorages())->toBeInstanceOf(MorphMany::class);
});

test('destination relationship returns MorphTo', function () {
    $redis = new StandaloneRedis;
    expect($redis->destination())->toBeInstanceOf(MorphTo::class);
});

test('scheduledBackups relationship returns MorphMany', function () {
    $redis = new StandaloneRedis;
    expect($redis->scheduledBackups())->toBeInstanceOf(MorphMany::class);
});

test('environment_variables relationship returns MorphMany', function () {
    $redis = new StandaloneRedis;
    expect($redis->environment_variables())->toBeInstanceOf(MorphMany::class);
});

test('tags relationship returns MorphToMany', function () {
    $redis = new StandaloneRedis;
    expect($redis->tags())->toBeInstanceOf(MorphToMany::class);
});

test('runtime_environment_variables relationship returns MorphMany', function () {
    $redis = new StandaloneRedis;
    expect($redis->runtime_environment_variables())->toBeInstanceOf(MorphMany::class);
});

test('sslCertificates relationship returns MorphMany', function () {
    $redis = new StandaloneRedis;
    expect($redis->sslCertificates())->toBeInstanceOf(MorphMany::class);
});

// SoftDeletes Tests
test('trashed method exists from SoftDeletes trait', function () {
    $redis = new StandaloneRedis;
    expect(method_exists($redis, 'trashed'))->toBeTrue();
});

test('restore method exists from SoftDeletes trait', function () {
    $redis = new StandaloneRedis;
    expect(method_exists($redis, 'restore'))->toBeTrue();
});

test('forceDelete method exists from SoftDeletes trait', function () {
    $redis = new StandaloneRedis;
    expect(method_exists($redis, 'forceDelete'))->toBeTrue();
});

// Fillable Tests
test('fillable array includes uuid', function () {
    $redis = new StandaloneRedis;
    expect($redis->getFillable())->toContain('uuid');
});

test('fillable array includes name', function () {
    $redis = new StandaloneRedis;
    expect($redis->getFillable())->toContain('name');
});

test('fillable array includes description', function () {
    $redis = new StandaloneRedis;
    expect($redis->getFillable())->toContain('description');
});

test('fillable array includes redis_username', function () {
    $redis = new StandaloneRedis;
    expect($redis->getFillable())->toContain('redis_username');
});

test('fillable array includes redis_password', function () {
    $redis = new StandaloneRedis;
    expect($redis->getFillable())->toContain('redis_password');
});

test('fillable array includes status', function () {
    $redis = new StandaloneRedis;
    expect($redis->getFillable())->toContain('status');
});

test('fillable array includes restart_count', function () {
    $redis = new StandaloneRedis;
    expect($redis->getFillable())->toContain('restart_count');
});

test('fillable array includes last_restart_at', function () {
    $redis = new StandaloneRedis;
    expect($redis->getFillable())->toContain('last_restart_at');
});

test('fillable array includes last_restart_type', function () {
    $redis = new StandaloneRedis;
    expect($redis->getFillable())->toContain('last_restart_type');
});

// Casts Tests
test('restart_count is cast to integer', function () {
    $redis = new StandaloneRedis;
    $casts = $redis->getCasts();
    expect($casts)->toHaveKey('restart_count', 'integer');
});

test('last_restart_at is cast to datetime', function () {
    $redis = new StandaloneRedis;
    $casts = $redis->getCasts();
    expect($casts)->toHaveKey('last_restart_at', 'datetime');
});

test('last_restart_type is cast to string', function () {
    $redis = new StandaloneRedis;
    $casts = $redis->getCasts();
    expect($casts)->toHaveKey('last_restart_type', 'string');
});

// Appends Tests
test('appends includes internal_db_url', function () {
    $redis = new StandaloneRedis;
    expect($redis->getAppends())->toContain('internal_db_url');
});

test('appends includes external_db_url', function () {
    $redis = new StandaloneRedis;
    expect($redis->getAppends())->toContain('external_db_url');
});

test('appends includes database_type', function () {
    $redis = new StandaloneRedis;
    expect($redis->getAppends())->toContain('database_type');
});

test('appends includes server_status', function () {
    $redis = new StandaloneRedis;
    expect($redis->getAppends())->toContain('server_status');
});

// Additional Method Tests
test('isBackupSolutionAvailable returns false', function () {
    $redis = new StandaloneRedis;
    expect($redis->isBackupSolutionAvailable())->toBeFalse();
});

test('project returns environment project', function () {
    $redis = new StandaloneRedis;
    $project = (object) ['id' => 1, 'name' => 'Test Project'];
    $redis->environment = (object) ['project' => $project];
    expect($redis->project())->toBe($project);
});

test('project returns null when no environment', function () {
    $redis = new StandaloneRedis;
    $redis->environment = null;
    expect($redis->project())->toBeNull();
});

test('team returns environment project team', function () {
    $redis = new StandaloneRedis;
    $team = (object) ['id' => 1, 'name' => 'Test Team'];
    $redis->environment = (object) ['project' => (object) ['team' => $team]];
    expect($redis->team())->toBe($team);
});

test('team returns null when no environment', function () {
    $redis = new StandaloneRedis;
    $redis->environment = null;
    expect($redis->team())->toBeNull();
});

test('getRedisVersion returns version from image', function () {
    $redis = new StandaloneRedis;
    $redis->setRawAttributes(['image' => 'redis:7.2'], true);
    expect($redis->getRedisVersion())->toBe('7.2');
});

test('getRedisVersion returns default when no version in image', function () {
    $redis = new StandaloneRedis;
    $redis->setRawAttributes(['image' => 'redis'], true);
    expect($redis->getRedisVersion())->toBe('0.0');
});

test('realStatus returns raw status value', function () {
    $redis = new StandaloneRedis;
    $redis->setRawAttributes(['status' => 'running(healthy)'], true);
    expect($redis->realStatus())->toBe('running(healthy)');
});
