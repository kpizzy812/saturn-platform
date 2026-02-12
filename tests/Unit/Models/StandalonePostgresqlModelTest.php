<?php

use App\Models\StandalonePostgresql;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

// type() Tests
test('type returns standalone-postgresql', function () {
    $db = new StandalonePostgresql;
    expect($db->type())->toBe('standalone-postgresql');
});

// Fillable security Tests
test('fillable does not contain id', function () {
    $fillable = (new StandalonePostgresql)->getFillable();

    expect($fillable)->not->toContain('id');
});

test('fillable is not empty', function () {
    $fillable = (new StandalonePostgresql)->getFillable();

    expect($fillable)
        ->not->toBeEmpty()
        ->toBeArray();
});

test('fillable contains expected PostgreSQL fields', function () {
    $fillable = (new StandalonePostgresql)->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('postgres_user')
        ->toContain('postgres_password')
        ->toContain('postgres_db')
        ->toContain('image')
        ->toContain('status')
        ->toContain('postgres_initdb_args')
        ->toContain('postgres_host_auth_method')
        ->toContain('init_scripts')
        ->toContain('is_public')
        ->toContain('public_port')
        ->toContain('ports_mappings')
        ->toContain('limits_memory')
        ->toContain('environment_id')
        ->toContain('destination_id')
        ->toContain('destination_type')
        ->toContain('is_log_drain_enabled')
        ->toContain('enable_ssl')
        ->toContain('ssl_mode')
        ->toContain('restart_count')
        ->toContain('last_restart_at');
});

// Casts Tests
test('init_scripts is cast to array', function () {
    $casts = (new StandalonePostgresql)->getCasts();

    expect($casts['init_scripts'])->toBe('array');
});

test('postgres_password is cast to encrypted', function () {
    $casts = (new StandalonePostgresql)->getCasts();

    expect($casts['postgres_password'])->toBe('encrypted');
});

test('restart_count is cast to integer', function () {
    $casts = (new StandalonePostgresql)->getCasts();

    expect($casts['restart_count'])->toBe('integer');
});

test('last_restart_at is cast to datetime', function () {
    $casts = (new StandalonePostgresql)->getCasts();

    expect($casts['last_restart_at'])->toBe('datetime');
});

// Appends Tests
test('appends contains internal_db_url', function () {
    $db = new StandalonePostgresql;
    $appends = (new \ReflectionProperty($db, 'appends'))->getValue($db);

    expect($appends)->toContain('internal_db_url');
});

test('appends contains external_db_url', function () {
    $db = new StandalonePostgresql;
    $appends = (new \ReflectionProperty($db, 'appends'))->getValue($db);

    expect($appends)->toContain('external_db_url');
});

test('appends contains database_type', function () {
    $db = new StandalonePostgresql;
    $appends = (new \ReflectionProperty($db, 'appends'))->getValue($db);

    expect($appends)->toContain('database_type');
});

test('appends contains server_status', function () {
    $db = new StandalonePostgresql;
    $appends = (new \ReflectionProperty($db, 'appends'))->getValue($db);

    expect($appends)->toContain('server_status');
});

// Relationship Tests
test('environment relationship returns BelongsTo', function () {
    $db = new StandalonePostgresql;
    expect($db->environment())->toBeInstanceOf(BelongsTo::class);
});

test('persistentStorages relationship returns MorphMany', function () {
    $db = new StandalonePostgresql;
    expect($db->persistentStorages())->toBeInstanceOf(MorphMany::class);
});

test('fileStorages relationship returns MorphMany', function () {
    $db = new StandalonePostgresql;
    expect($db->fileStorages())->toBeInstanceOf(MorphMany::class);
});

test('destination relationship returns MorphTo', function () {
    $db = new StandalonePostgresql;
    expect($db->destination())->toBeInstanceOf(MorphTo::class);
});

test('scheduledBackups relationship returns MorphMany', function () {
    $db = new StandalonePostgresql;
    expect($db->scheduledBackups())->toBeInstanceOf(MorphMany::class);
});

test('environment_variables relationship returns MorphMany', function () {
    $db = new StandalonePostgresql;
    expect($db->environment_variables())->toBeInstanceOf(MorphMany::class);
});

test('tags relationship returns MorphToMany', function () {
    $db = new StandalonePostgresql;
    expect($db->tags())->toBeInstanceOf(MorphToMany::class);
});

test('runtime_environment_variables relationship returns MorphMany', function () {
    $db = new StandalonePostgresql;
    expect($db->runtime_environment_variables())->toBeInstanceOf(MorphMany::class);
});

test('sslCertificates relationship returns MorphMany', function () {
    $db = new StandalonePostgresql;
    expect($db->sslCertificates())->toBeInstanceOf(MorphMany::class);
});

// isRunning Tests
test('isRunning returns true when status contains running', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['status' => 'running:healthy'], true);
    expect($db->isRunning())->toBeTrue();
});

test('isRunning returns true when status is running:unhealthy', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['status' => 'running:unhealthy'], true);
    expect($db->isRunning())->toBeTrue();
});

test('isRunning returns false when status is exited', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['status' => 'exited:0'], true);
    expect($db->isRunning())->toBeFalse();
});

// isExited Tests
test('isExited returns true when status starts with exited', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['status' => 'exited:0'], true);
    expect($db->isExited())->toBeTrue();
});

test('isExited returns false when status is running', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['status' => 'running:healthy'], true);
    expect($db->isExited())->toBeFalse();
});

// Status accessor Tests
test('status setter normalizes running(healthy) to running:healthy', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['status' => 'test'], true);
    $db->status = 'running(healthy)';
    expect($db->getAttributes()['status'])->toBe('running:healthy');
});

test('status setter preserves running:healthy format', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['status' => 'test'], true);
    $db->status = 'running:healthy';
    expect($db->getAttributes()['status'])->toBe('running:healthy');
});

test('status setter converts running to running:unhealthy', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['status' => 'test'], true);
    $db->status = 'running';
    expect($db->getAttributes()['status'])->toBe('running:unhealthy');
});

test('status getter normalizes running(healthy) to running:healthy', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['status' => 'running(healthy)'], true);
    expect($db->status)->toBe('running:healthy');
});

test('status getter normalizes exited to exited:unhealthy', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['status' => 'exited'], true);
    expect($db->status)->toBe('exited:unhealthy');
});

// isLogDrainEnabled Tests
test('isLogDrainEnabled returns false when not set', function () {
    $db = new StandalonePostgresql;
    expect($db->isLogDrainEnabled())->toBeFalse();
});

test('isLogDrainEnabled returns value of is_log_drain_enabled', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['is_log_drain_enabled' => true], true);
    expect($db->isLogDrainEnabled())->toBeTrue();
});

// portsMappings accessor Tests
test('portsMappings setter converts empty string to null', function () {
    $db = new StandalonePostgresql;
    $db->ports_mappings = '';
    expect($db->getAttributes()['ports_mappings'])->toBeNull();
});

test('portsMappings setter preserves non-empty string', function () {
    $db = new StandalonePostgresql;
    $db->ports_mappings = '5432:5432';
    expect($db->getAttributes()['ports_mappings'])->toBe('5432:5432');
});

// portsMappingsArray accessor Tests
test('portsMappingsArray returns empty array when ports_mappings is null', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['ports_mappings' => null], true);
    expect($db->portsMappingsArray)->toBeArray()->toBeEmpty();
});

test('portsMappingsArray returns array when ports_mappings is comma string', function () {
    $db = new StandalonePostgresql;
    $db->setRawAttributes(['ports_mappings' => '5432:5432,5433:5433'], true);
    expect($db->portsMappingsArray)
        ->toBeArray()
        ->toHaveCount(2)
        ->toContain('5432:5432')
        ->toContain('5433:5433');
});

// SoftDeletes Tests
test('trashed method exists', function () {
    $db = new StandalonePostgresql;
    expect(method_exists($db, 'trashed'))->toBeTrue();
});

test('restore method exists', function () {
    $db = new StandalonePostgresql;
    expect(method_exists($db, 'restore'))->toBeTrue();
});

// isBackupSolutionAvailable Tests
test('isBackupSolutionAvailable returns true', function () {
    $db = new StandalonePostgresql;
    expect($db->isBackupSolutionAvailable())->toBeTrue();
});

// Traits Tests
test('model uses LogsActivity trait', function () {
    $db = new StandalonePostgresql;
    expect(class_uses_recursive($db))
        ->toHaveKey('Spatie\Activitylog\Traits\LogsActivity');
});

test('model uses Auditable trait', function () {
    $db = new StandalonePostgresql;
    expect(class_uses_recursive($db))
        ->toHaveKey('App\Traits\Auditable');
});

test('model uses HasSafeStringAttribute trait', function () {
    $db = new StandalonePostgresql;
    expect(class_uses_recursive($db))
        ->toHaveKey('App\Traits\HasSafeStringAttribute');
});

test('model uses SoftDeletes trait', function () {
    $db = new StandalonePostgresql;
    expect(class_uses_recursive($db))
        ->toHaveKey('Illuminate\Database\Eloquent\SoftDeletes');
});

test('model uses ValidatesPublicPort trait', function () {
    $db = new StandalonePostgresql;
    expect(class_uses_recursive($db))
        ->toHaveKey('App\Traits\ValidatesPublicPort');
});

// workdir Tests
test('workdir returns path ending with uuid', function () {
    $db = new StandalonePostgresql;
    $db->uuid = 'test-uuid-123';
    expect($db->workdir())->toEndWith('/test-uuid-123');
});
