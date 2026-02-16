<?php

use App\Models\Application;
use App\Models\Environment;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandalonePostgresql;
use App\Models\Team;

// Helper to invoke private method via reflection
function callPrivate(object $object, string $method, array $params = []): mixed
{
    $ref = new ReflectionMethod($object, $method);

    return $ref->invoke($object, ...$params);
}

// ---------------------------------------------------------------
// hasSearchableChanges() tests
// ---------------------------------------------------------------

it('detects dirty fqdn field as searchable change on Application', function () {
    $app = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $app->shouldReceive('getAttributes')->andReturn([
        'name' => 'old',
        'fqdn' => 'old.test',
    ]);
    $app->shouldReceive('isDirty')->with('name')->andReturn(false);
    $app->shouldReceive('isDirty')->with('fqdn')->andReturn(true);

    expect(callPrivate($app, 'hasSearchableChanges'))->toBeTrue();
});

it('detects dirty ip field as searchable change on Server', function () {
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->shouldReceive('getAttributes')->andReturn([
        'name' => 'srv',
        'ip' => '1.2.3.4',
    ]);
    $server->shouldReceive('isDirty')->with('name')->andReturn(false);
    $server->shouldReceive('isDirty')->with('description')->andReturn(false);
    $server->shouldReceive('isDirty')->with('ip')->andReturn(true);

    expect(callPrivate($server, 'hasSearchableChanges'))->toBeTrue();
});

it('returns false when no searchable fields are dirty', function () {
    $app = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $app->shouldReceive('getAttributes')->andReturn([
        'name' => 'app',
        'fqdn' => 'test.com',
    ]);
    $app->shouldReceive('isDirty')->andReturn(false);

    expect(callPrivate($app, 'hasSearchableChanges'))->toBeFalse();
});

it('returns true when exception is thrown in hasSearchableChanges', function () {
    $app = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $app->shouldReceive('getAttributes')->andThrow(new \RuntimeException('Error'));

    expect(callPrivate($app, 'hasSearchableChanges'))->toBeTrue();
});

// ---------------------------------------------------------------
// getTeamIdForCache() tests
// ---------------------------------------------------------------

it('Project resolves team_id from direct BelongsTo team', function () {
    $team = Mockery::mock(Team::class);
    $team->id = 42;

    $project = Mockery::mock(Project::class)->makePartial()->shouldIgnoreMissing();
    $project->shouldReceive('__get')->with('team')->andReturn($team);

    expect(callPrivate($project, 'getTeamIdForCache'))->toBe(42);
});

it('Server resolves team_id from direct BelongsTo team', function () {
    $team = Mockery::mock(Team::class);
    $team->id = 99;

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->shouldReceive('__get')->with('team')->andReturn($team);

    expect(callPrivate($server, 'getTeamIdForCache'))->toBe(99);
});

it('Application resolves team_id from team() accessor via call_user_func', function () {
    $team = Mockery::mock(Team::class);
    $team->id = 5;

    $app = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $app->shouldReceive('team')->andReturn($team);

    expect(callPrivate($app, 'getTeamIdForCache'))->toBe(5);
});

it('Service resolves team_id from team() accessor', function () {
    $team = Mockery::mock(Team::class);
    $team->id = 77;

    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $service->shouldReceive('team')->andReturn($team);

    expect(callPrivate($service, 'getTeamIdForCache'))->toBe(77);
});

it('StandalonePostgresql resolves team_id from team() accessor', function () {
    $team = Mockery::mock(Team::class);
    $team->id = 33;

    $db = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();
    $db->shouldReceive('team')->andReturn($team);

    expect(callPrivate($db, 'getTeamIdForCache'))->toBe(33);
});

it('returns null when team is not found', function () {
    $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
    $service->shouldReceive('team')->andReturn(null);

    expect(callPrivate($service, 'getTeamIdForCache'))->toBeNull();
});

it('returns null when exception is thrown in getTeamIdForCache', function () {
    $app = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
    $app->shouldReceive('team')->andThrow(new \RuntimeException('Connection error'));

    expect(callPrivate($app, 'getTeamIdForCache'))->toBeNull();
});

// ---------------------------------------------------------------
// Verify match dispatch works for all model types
// ---------------------------------------------------------------

it('getTeamIdForCache handles Environment model through project chain', function () {
    $team = Mockery::mock(Team::class);
    $team->id = 7;

    $project = new \stdClass;
    $project->team = $team;

    $env = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $env->shouldReceive('__get')->with('project')->andReturn($project);

    expect(callPrivate($env, 'getTeamIdForCache'))->toBe(7);
});

it('getTeamIdForCache returns null when Environment has no project', function () {
    $env = Mockery::mock(Environment::class)->makePartial()->shouldIgnoreMissing();
    $env->shouldReceive('__get')->with('project')->andReturn(null);

    expect(callPrivate($env, 'getTeamIdForCache'))->toBeNull();
});
