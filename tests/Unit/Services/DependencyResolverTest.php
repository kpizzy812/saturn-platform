<?php

use App\Models\Application;
use App\Models\Environment;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Services\DependencyResolver;

beforeEach(function () {
    $this->resolver = new DependencyResolver;
});

it('returns empty tiers for empty environment', function () {
    $env = Mockery::mock(Environment::class);
    $env->shouldReceive('getAttribute')->with('applications')->andReturn(collect());
    $env->shouldReceive('databases')->andReturn(collect());
    $env->shouldReceive('getAttribute')->with('services')->andReturn(collect());

    $tiers = $this->resolver->resolve($env);

    expect($tiers)->toBe([]);
});

it('puts databases in tier 0 (no dependencies)', function () {
    $db = Mockery::mock(StandalonePostgresql::class);
    $db->shouldReceive('getAttribute')->with('uuid')->andReturn('db-uuid-1');
    $db->shouldReceive('getAttribute')->with('name')->andReturn('postgres');
    $db->shouldReceive('getAttribute')->with('depends_on')->andReturn(null);

    $redis = Mockery::mock(StandaloneRedis::class);
    $redis->shouldReceive('getAttribute')->with('uuid')->andReturn('redis-uuid-1');
    $redis->shouldReceive('getAttribute')->with('name')->andReturn('redis');
    $redis->shouldReceive('getAttribute')->with('depends_on')->andReturn(null);

    $env = Mockery::mock(Environment::class);
    $env->shouldReceive('getAttribute')->with('applications')->andReturn(collect());
    $env->shouldReceive('databases')->andReturn(collect([$db, $redis]));
    $env->shouldReceive('getAttribute')->with('services')->andReturn(collect());

    $tiers = $this->resolver->resolve($env);

    expect($tiers)->toHaveCount(1);
    expect($tiers[0])->toHaveCount(2);
    expect($tiers[0][0]['type'])->toBe('database');
    expect($tiers[0][1]['type'])->toBe('database');
});

it('resolves simple dependency chain: db -> app', function () {
    $db = Mockery::mock(StandalonePostgresql::class);
    $db->shouldReceive('getAttribute')->with('uuid')->andReturn('db-uuid');
    $db->shouldReceive('getAttribute')->with('name')->andReturn('postgres');
    $db->shouldReceive('getAttribute')->with('depends_on')->andReturn(null);

    $app = Mockery::mock(Application::class);
    $app->shouldReceive('getAttribute')->with('uuid')->andReturn('app-uuid');
    $app->shouldReceive('getAttribute')->with('name')->andReturn('api');
    $app->shouldReceive('getAttribute')->with('depends_on')->andReturn(['db-uuid']);

    $env = Mockery::mock(Environment::class);
    $env->shouldReceive('getAttribute')->with('applications')->andReturn(collect([$app]));
    $env->shouldReceive('databases')->andReturn(collect([$db]));
    $env->shouldReceive('getAttribute')->with('services')->andReturn(collect());

    $tiers = $this->resolver->resolve($env);

    expect($tiers)->toHaveCount(2);
    // Tier 0: database
    expect($tiers[0][0]['uuid'])->toBe('db-uuid');
    expect($tiers[0][0]['type'])->toBe('database');
    // Tier 1: application
    expect($tiers[1][0]['uuid'])->toBe('app-uuid');
    expect($tiers[1][0]['type'])->toBe('application');
});

it('resolves multi-tier chain: db -> api -> worker', function () {
    $db = Mockery::mock(StandalonePostgresql::class);
    $db->shouldReceive('getAttribute')->with('uuid')->andReturn('db-uuid');
    $db->shouldReceive('getAttribute')->with('name')->andReturn('postgres');
    $db->shouldReceive('getAttribute')->with('depends_on')->andReturn(null);

    $api = Mockery::mock(Application::class);
    $api->shouldReceive('getAttribute')->with('uuid')->andReturn('api-uuid');
    $api->shouldReceive('getAttribute')->with('name')->andReturn('api');
    $api->shouldReceive('getAttribute')->with('depends_on')->andReturn(['db-uuid']);

    $worker = Mockery::mock(Application::class);
    $worker->shouldReceive('getAttribute')->with('uuid')->andReturn('worker-uuid');
    $worker->shouldReceive('getAttribute')->with('name')->andReturn('worker');
    $worker->shouldReceive('getAttribute')->with('depends_on')->andReturn(['api-uuid']);

    $env = Mockery::mock(Environment::class);
    $env->shouldReceive('getAttribute')->with('applications')->andReturn(collect([$api, $worker]));
    $env->shouldReceive('databases')->andReturn(collect([$db]));
    $env->shouldReceive('getAttribute')->with('services')->andReturn(collect());

    $tiers = $this->resolver->resolve($env);

    expect($tiers)->toHaveCount(3);
    expect($tiers[0][0]['name'])->toBe('postgres');
    expect($tiers[1][0]['name'])->toBe('api');
    expect($tiers[2][0]['name'])->toBe('worker');
});

it('detects circular dependencies', function () {
    $app1 = Mockery::mock(Application::class);
    $app1->shouldReceive('getAttribute')->with('uuid')->andReturn('app1-uuid');
    $app1->shouldReceive('getAttribute')->with('name')->andReturn('app1');
    $app1->shouldReceive('getAttribute')->with('depends_on')->andReturn(['app2-uuid']);

    $app2 = Mockery::mock(Application::class);
    $app2->shouldReceive('getAttribute')->with('uuid')->andReturn('app2-uuid');
    $app2->shouldReceive('getAttribute')->with('name')->andReturn('app2');
    $app2->shouldReceive('getAttribute')->with('depends_on')->andReturn(['app1-uuid']);

    $env = Mockery::mock(Environment::class);
    $env->shouldReceive('getAttribute')->with('applications')->andReturn(collect([$app1, $app2]));
    $env->shouldReceive('databases')->andReturn(collect());
    $env->shouldReceive('getAttribute')->with('services')->andReturn(collect());

    expect(fn () => $this->resolver->resolve($env))
        ->toThrow(InvalidArgumentException::class, 'Circular dependency detected');
});

it('validates missing dependency references', function () {
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('getAttribute')->with('uuid')->andReturn('app-uuid');
    $app->shouldReceive('getAttribute')->with('name')->andReturn('api');
    $app->shouldReceive('getAttribute')->with('depends_on')->andReturn(['nonexistent-uuid']);

    $env = Mockery::mock(Environment::class);
    $env->shouldReceive('getAttribute')->with('applications')->andReturn(collect([$app]));
    $env->shouldReceive('databases')->andReturn(collect());
    $env->shouldReceive('getAttribute')->with('services')->andReturn(collect());

    $errors = $this->resolver->validate($env);

    expect($errors)->toContain("Resource 'api' depends on unknown UUID 'nonexistent-uuid'.");
});

it('validates self-reference', function () {
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('getAttribute')->with('uuid')->andReturn('app-uuid');
    $app->shouldReceive('getAttribute')->with('name')->andReturn('api');
    $app->shouldReceive('getAttribute')->with('depends_on')->andReturn(['app-uuid']);

    $env = Mockery::mock(Environment::class);
    $env->shouldReceive('getAttribute')->with('applications')->andReturn(collect([$app]));
    $env->shouldReceive('databases')->andReturn(collect());
    $env->shouldReceive('getAttribute')->with('services')->andReturn(collect());

    $errors = $this->resolver->validate($env);

    expect($errors)->toContain("Resource 'api' depends on itself.");
});

it('returns flat ordered list', function () {
    $db = Mockery::mock(StandalonePostgresql::class);
    $db->shouldReceive('getAttribute')->with('uuid')->andReturn('db-uuid');
    $db->shouldReceive('getAttribute')->with('name')->andReturn('postgres');
    $db->shouldReceive('getAttribute')->with('depends_on')->andReturn(null);

    $app = Mockery::mock(Application::class);
    $app->shouldReceive('getAttribute')->with('uuid')->andReturn('app-uuid');
    $app->shouldReceive('getAttribute')->with('name')->andReturn('api');
    $app->shouldReceive('getAttribute')->with('depends_on')->andReturn(['db-uuid']);

    $env = Mockery::mock(Environment::class);
    $env->shouldReceive('getAttribute')->with('applications')->andReturn(collect([$app]));
    $env->shouldReceive('databases')->andReturn(collect([$db]));
    $env->shouldReceive('getAttribute')->with('services')->andReturn(collect());

    $flat = $this->resolver->resolveFlat($env);

    expect($flat)->toBe(['db-uuid', 'app-uuid']);
});

it('handles parallel resources in same tier', function () {
    $db = Mockery::mock(StandalonePostgresql::class);
    $db->shouldReceive('getAttribute')->with('uuid')->andReturn('db-uuid');
    $db->shouldReceive('getAttribute')->with('name')->andReturn('postgres');
    $db->shouldReceive('getAttribute')->with('depends_on')->andReturn(null);

    $redis = Mockery::mock(StandaloneRedis::class);
    $redis->shouldReceive('getAttribute')->with('uuid')->andReturn('redis-uuid');
    $redis->shouldReceive('getAttribute')->with('name')->andReturn('redis');
    $redis->shouldReceive('getAttribute')->with('depends_on')->andReturn(null);

    // Both apps depend on both db and redis → same tier
    $api = Mockery::mock(Application::class);
    $api->shouldReceive('getAttribute')->with('uuid')->andReturn('api-uuid');
    $api->shouldReceive('getAttribute')->with('name')->andReturn('api');
    $api->shouldReceive('getAttribute')->with('depends_on')->andReturn(['db-uuid', 'redis-uuid']);

    $web = Mockery::mock(Application::class);
    $web->shouldReceive('getAttribute')->with('uuid')->andReturn('web-uuid');
    $web->shouldReceive('getAttribute')->with('name')->andReturn('web');
    $web->shouldReceive('getAttribute')->with('depends_on')->andReturn(['db-uuid', 'redis-uuid']);

    $env = Mockery::mock(Environment::class);
    $env->shouldReceive('getAttribute')->with('applications')->andReturn(collect([$api, $web]));
    $env->shouldReceive('databases')->andReturn(collect([$db, $redis]));
    $env->shouldReceive('getAttribute')->with('services')->andReturn(collect());

    $tiers = $this->resolver->resolve($env);

    expect($tiers)->toHaveCount(2);
    // Tier 0: both databases
    expect($tiers[0])->toHaveCount(2);
    // Tier 1: both applications
    expect($tiers[1])->toHaveCount(2);
});
