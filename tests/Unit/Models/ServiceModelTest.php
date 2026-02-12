<?php

use App\Models\Service;

// type() Tests
test('type returns service', function () {
    $service = new Service;
    expect($service->type())->toBe('service');
});

// isRunning Tests - need to mock getStatusAttribute since it queries DB
test('isRunning returns true when status contains running', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('running:healthy');
    expect($service->isRunning())->toBeTrue();
});

test('isRunning returns true when status contains running among others', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('running');
    expect($service->isRunning())->toBeTrue();
});

test('isRunning returns false when status is exited', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('exited');
    expect($service->isRunning())->toBeFalse();
});

test('isRunning returns false when status is stopped', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('stopped');
    expect($service->isRunning())->toBeFalse();
});

// isExited Tests
test('isExited returns true when status contains exited', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('exited');
    expect($service->isExited())->toBeTrue();
});

test('isExited returns true when status has exited in mixed status', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('exited:0');
    expect($service->isExited())->toBeTrue();
});

test('isExited returns false when status is running', function () {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getStatusAttribute')->andReturn('running:healthy');
    expect($service->isExited())->toBeFalse();
});

// project() and team() Tests
test('project returns environment project', function () {
    $service = new Service;
    $project = (object) ['id' => 1, 'name' => 'Test Project'];
    $service->setRelation('environment', (object) ['project' => $project]);

    expect($service->project())->toBe($project);
});

test('project returns null when no environment', function () {
    $service = new Service;

    expect($service->project())->toBeNull();
});

test('team returns environment project team', function () {
    $service = new Service;
    $team = (object) ['id' => 1, 'name' => 'Test Team'];
    $service->setRelation('environment', (object) ['project' => (object) ['team' => $team]]);

    expect($service->team())->toBe($team);
});

test('team returns null when no environment', function () {
    $service = new Service;

    expect($service->team())->toBeNull();
});

// Relationship Type Tests
test('applications relationship returns hasMany', function () {
    $relation = (new Service)->applications();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('databases relationship returns hasMany', function () {
    $relation = (new Service)->databases();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('destination relationship returns morphTo', function () {
    $relation = (new Service)->destination();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

test('environment relationship returns belongsTo', function () {
    $relation = (new Service)->environment();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('server relationship returns belongsTo', function () {
    $relation = (new Service)->server();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('scheduled_tasks relationship returns hasMany', function () {
    $relation = (new Service)->scheduled_tasks();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('environment_variables relationship returns morphMany', function () {
    $relation = (new Service)->environment_variables();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
});

test('tags relationship returns morphToMany', function () {
    $relation = (new Service)->tags();

    expect($relation)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphToMany::class);
});

// Fillable Security Tests
test('model uses fillable array for mass assignment protection', function () {
    $service = new Service;

    expect($service->getFillable())->not->toBeEmpty();
});

test('fillable does not include id or uuid', function () {
    $fillable = (new Service)->getFillable();

    expect($fillable)
        ->not->toContain('id')
        ->not->toContain('uuid');
});

test('fillable includes expected fields', function () {
    $fillable = (new Service)->getFillable();

    expect($fillable)
        ->toContain('name')
        ->toContain('description')
        ->toContain('docker_compose_raw')
        ->toContain('docker_compose')
        ->toContain('environment_id')
        ->toContain('server_id')
        ->toContain('service_type')
        ->toContain('limits_memory')
        ->toContain('limits_cpus');
});

test('fillable includes autoscaling fields', function () {
    $fillable = (new Service)->getFillable();

    expect($fillable)
        ->toContain('min_replicas')
        ->toContain('max_replicas')
        ->toContain('target_cpu_percent')
        ->toContain('target_memory_percent')
        ->toContain('cooldown_seconds');
});

test('fillable includes webhook secret fields', function () {
    $fillable = (new Service)->getFillable();

    expect($fillable)
        ->toContain('manual_webhook_secret_github')
        ->toContain('manual_webhook_secret_gitlab')
        ->toContain('manual_webhook_secret_bitbucket')
        ->toContain('manual_webhook_secret_gitea');
});

// workdir() Tests
test('workdir returns path with uuid', function () {
    $service = new Service;
    $service->uuid = 'svc-uuid-123';

    $workdir = $service->workdir();

    expect($workdir)->toEndWith('/svc-uuid-123');
});

// getLimits() Tests
test('getLimits returns all limit fields', function () {
    $service = new Service;
    $service->limits_memory = '512m';
    $service->limits_memory_swap = '1g';
    $service->limits_memory_swappiness = 60;
    $service->limits_memory_reservation = '256m';
    $service->limits_cpus = '2';
    $service->limits_cpuset = '0,1';
    $service->limits_cpu_shares = 1024;

    $limits = $service->getLimits();

    expect($limits)
        ->toBeArray()
        ->toHaveKey('limits_memory', '512m')
        ->toHaveKey('limits_memory_swap', '1g')
        ->toHaveKey('limits_memory_swappiness', 60)
        ->toHaveKey('limits_memory_reservation', '256m')
        ->toHaveKey('limits_cpus', '2')
        ->toHaveKey('limits_cpuset', '0,1')
        ->toHaveKey('limits_cpu_shares', 1024);
});

// hasResourceLimits() Tests
test('hasResourceLimits returns false when all zero', function () {
    $service = new Service;
    $service->limits_memory = '0';
    $service->limits_cpus = '0';
    $service->limits_memory_swap = '0';
    $service->limits_memory_reservation = '0';

    expect($service->hasResourceLimits())->toBeFalse();
});

test('hasResourceLimits returns true when memory is set', function () {
    $service = new Service;
    $service->limits_memory = '512m';
    $service->limits_cpus = '0';
    $service->limits_memory_swap = '0';
    $service->limits_memory_reservation = '0';

    expect($service->hasResourceLimits())->toBeTrue();
});

test('hasResourceLimits returns true when cpus is set', function () {
    $service = new Service;
    $service->limits_memory = '0';
    $service->limits_cpus = '2';
    $service->limits_memory_swap = '0';
    $service->limits_memory_reservation = '0';

    expect($service->hasResourceLimits())->toBeTrue();
});

test('hasResourceLimits returns true when memory_swap is set', function () {
    $service = new Service;
    $service->limits_memory = '0';
    $service->limits_cpus = '0';
    $service->limits_memory_swap = '1g';
    $service->limits_memory_reservation = '0';

    expect($service->hasResourceLimits())->toBeTrue();
});

test('hasResourceLimits returns true when memory_reservation is set', function () {
    $service = new Service;
    $service->limits_memory = '0';
    $service->limits_cpus = '0';
    $service->limits_memory_swap = '0';
    $service->limits_memory_reservation = '256m';

    expect($service->hasResourceLimits())->toBeTrue();
});

// getHealthcheckConfig() Tests
test('getHealthcheckConfig returns defaults when docker_compose_raw is empty', function () {
    $service = new Service;
    $service->docker_compose_raw = null;

    $config = $service->getHealthcheckConfig();

    expect($config)
        ->toBeArray()
        ->toHaveKey('enabled', true)
        ->toHaveKey('type', 'http')
        ->toHaveKey('interval', 30)
        ->toHaveKey('timeout', 10)
        ->toHaveKey('retries', 3)
        ->toHaveKey('start_period', 30)
        ->toHaveKey('service_name', null);
});

test('getHealthcheckConfig parses healthcheck from docker compose', function () {
    $service = new Service;
    $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
    healthcheck:
      test: ["CMD-SHELL", "curl -f http://localhost/ || exit 1"]
      interval: 60s
      timeout: 5s
      retries: 5
      start_period: 10s
YAML;

    $config = $service->getHealthcheckConfig();

    expect($config['enabled'])->toBeTrue()
        ->and($config['type'])->toBe('http')
        ->and($config['test'])->toBe('curl -f http://localhost/ || exit 1')
        ->and($config['interval'])->toBe(60)
        ->and($config['timeout'])->toBe(5)
        ->and($config['retries'])->toBe(5)
        ->and($config['start_period'])->toBe(10)
        ->and($config['service_name'])->toBe('web');
});

test('getHealthcheckConfig detects disabled healthcheck', function () {
    $service = new Service;
    $service->docker_compose_raw = <<<'YAML'
services:
  app:
    image: myapp
    healthcheck:
      disable: true
YAML;

    $config = $service->getHealthcheckConfig();

    expect($config['enabled'])->toBeFalse()
        ->and($config['service_name'])->toBe('app');
});

test('getHealthcheckConfig detects tcp type from nc command', function () {
    $service = new Service;
    $service->docker_compose_raw = <<<'YAML'
services:
  redis:
    image: redis
    healthcheck:
      test: ["CMD", "nc -z localhost 6379"]
      interval: 10s
YAML;

    $config = $service->getHealthcheckConfig();

    expect($config['type'])->toBe('tcp');
});

test('getHealthcheckConfig returns defaults for invalid YAML', function () {
    $service = new Service;
    $service->docker_compose_raw = 'invalid: [yaml: broken';

    $config = $service->getHealthcheckConfig();

    expect($config['enabled'])->toBeTrue()
        ->and($config['service_name'])->toBeNull();
});

test('getHealthcheckConfig returns defaults when no services have healthcheck', function () {
    $service = new Service;
    $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
YAML;

    $config = $service->getHealthcheckConfig();

    expect($config['enabled'])->toBeTrue()
        ->and($config['service_name'])->toBeNull();
});

test('getHealthcheckConfig parses minute durations', function () {
    $service = new Service;
    $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
    healthcheck:
      test: ["CMD-SHELL", "curl -f http://localhost/"]
      interval: 2m
      timeout: 1m
YAML;

    $config = $service->getHealthcheckConfig();

    expect($config['interval'])->toBe(120)
        ->and($config['timeout'])->toBe(60);
});

test('getHealthcheckConfig parses hour durations', function () {
    $service = new Service;
    $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
    healthcheck:
      test: ["CMD-SHELL", "curl -f http://localhost/"]
      interval: 1h
YAML;

    $config = $service->getHealthcheckConfig();

    expect($config['interval'])->toBe(3600);
});

// getServicesHealthcheckStatus() Tests
test('getServicesHealthcheckStatus returns empty for null compose', function () {
    $service = new Service;
    $service->docker_compose_raw = null;

    expect($service->getServicesHealthcheckStatus())->toBeEmpty();
});

test('getServicesHealthcheckStatus returns status for each service', function () {
    $service = new Service;
    $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
  db:
    image: postgres
YAML;

    $result = $service->getServicesHealthcheckStatus();

    expect($result)
        ->toHaveKey('web')
        ->toHaveKey('db');
    expect($result['web']['has_healthcheck'])->toBeTrue();
    expect($result['db']['has_healthcheck'])->toBeFalse();
});

test('getServicesHealthcheckStatus handles disabled healthcheck', function () {
    $service = new Service;
    $service->docker_compose_raw = <<<'YAML'
services:
  app:
    image: myapp
    healthcheck:
      disable: true
YAML;

    $result = $service->getServicesHealthcheckStatus();

    expect($result['app']['has_healthcheck'])->toBeFalse();
});

// Attribute Tests
test('service has name attribute', function () {
    $service = new Service;
    $service->name = 'my-service';

    expect($service->name)->toBe('my-service');
});

test('service has docker_compose_raw attribute', function () {
    $service = new Service;
    $service->docker_compose_raw = 'version: "3"';

    expect($service->docker_compose_raw)->toBe('version: "3"');
});

test('service has service_type attribute', function () {
    $service = new Service;
    $service->service_type = 'wordpress';

    expect($service->service_type)->toBe('wordpress');
});

test('service has config_hash attribute', function () {
    $service = new Service;
    $service->config_hash = 'abc123hash';

    expect($service->config_hash)->toBe('abc123hash');
});

// Appends Tests
test('server_status and status are appended', function () {
    $service = new Service;
    $appends = (new \ReflectionProperty($service, 'appends'))->getValue($service);

    expect($appends)->toContain('server_status')
        ->toContain('status');
});

// SoftDeletes Tests
test('service uses soft deletes', function () {
    $service = new Service;

    expect(method_exists($service, 'trashed'))->toBeTrue()
        ->and(method_exists($service, 'restore'))->toBeTrue();
});
