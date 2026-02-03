<?php

use App\Models\Server;
use App\Models\StandalonePostgresql;
use App\Models\User;
use App\Services\Authorization\ResourceAuthorizationService;

beforeEach(function () {
    $this->service = new ResourceAuthorizationService;
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| Server Authorization Tests
|--------------------------------------------------------------------------
*/

it('allows platform admin to view any server', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();

    expect($this->service->canViewServer($user, $server))->toBeTrue();
});

it('allows team member to view server in their team', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 1;

    expect($this->service->canViewServer($user, $server))->toBeTrue();
});

it('denies user from viewing server in another team', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 2;

    expect($this->service->canViewServer($user, $server))->toBeFalse();
});

it('allows admin to create server', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'admin']])
            ->getMock()
    );

    expect($this->service->canCreateServer($user, 1))->toBeTrue();
});

it('denies developer from creating server', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'developer']])
            ->getMock()
    );

    expect($this->service->canCreateServer($user, 1))->toBeFalse();
});

it('allows admin to update server', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'admin']])
            ->getMock()
    );

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 1;

    expect($this->service->canUpdateServer($user, $server))->toBeTrue();
});

it('denies developer from updating server', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'developer']])
            ->getMock()
    );

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 1;

    expect($this->service->canUpdateServer($user, $server))->toBeFalse();
});

it('allows only owner to delete server', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'owner']])
            ->getMock()
    );

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 1;

    expect($this->service->canDeleteServer($user, $server))->toBeTrue();
});

it('denies admin from deleting server', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'admin']])
            ->getMock()
    );

    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $server->team_id = 1;

    expect($this->service->canDeleteServer($user, $server))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Database Authorization Tests
|--------------------------------------------------------------------------
*/

it('allows team member to view database', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();
    $database->team_id = 1;

    expect($this->service->canViewDatabase($user, $database))->toBeTrue();
});

it('allows admin to view database credentials', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'admin']])
            ->getMock()
    );

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();
    $database->team_id = 1;

    expect($this->service->canViewDatabaseCredentials($user, $database))->toBeTrue();
});

it('denies developer from viewing database credentials', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'developer']])
            ->getMock()
    );

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();
    $database->team_id = 1;

    expect($this->service->canViewDatabaseCredentials($user, $database))->toBeFalse();
});

it('allows only owner to delete database', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'owner']])
            ->getMock()
    );

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();
    $database->team_id = 1;

    expect($this->service->canDeleteDatabase($user, $database))->toBeTrue();
});

it('denies admin from deleting database', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->teams = collect([(object) ['id' => 1]]);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'admin']])
            ->getMock()
    );

    $database = Mockery::mock(StandalonePostgresql::class)->makePartial()->shouldIgnoreMissing();
    $database->team_id = 1;

    expect($this->service->canDeleteDatabase($user, $database))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Sensitive Data Access Tests
|--------------------------------------------------------------------------
*/

it('allows admin to access sensitive data', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'admin']])
            ->getMock()
    );

    expect($this->service->canAccessSensitiveData($user, 1))->toBeTrue();
});

it('denies developer from accessing sensitive data', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('teams')->andReturn(
        Mockery::mock()->shouldReceive('where')->with('team_id', 1)->andReturnSelf()
            ->shouldReceive('first')->andReturn((object) ['pivot' => (object) ['role' => 'developer']])
            ->getMock()
    );

    expect($this->service->canAccessSensitiveData($user, 1))->toBeFalse();
});

it('allows platform admin to access sensitive data', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->shouldReceive('isPlatformAdmin')->andReturn(true);

    expect($this->service->canAccessSensitiveData($user, 1))->toBeTrue();
});
