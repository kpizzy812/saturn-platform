<?php

use App\Models\S3Storage;
use App\Models\User;
use App\Policies\S3StoragePolicy;
use App\Services\Authorization\ResourceAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ResourceAuthorizationService::class);
    $this->policy = new S3StoragePolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

it('allows team member to view S3 storage from their team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'member']],
    ]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $storage = Mockery::mock(S3Storage::class)->makePartial();
    $storage->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $storage->team_id = 1;

    expect($this->policy->view($user, $storage))->toBeTrue();
});

it('denies team member to view S3 storage from another team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'owner']],
    ]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $storage = Mockery::mock(S3Storage::class)->makePartial();
    $storage->shouldReceive('getAttribute')->with('team_id')->andReturn(2);
    $storage->team_id = 2;

    expect($this->policy->view($user, $storage))->toBeFalse();
});

it('allows user with permission to update S3 storage from their team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $storage = Mockery::mock(S3Storage::class)->makePartial();
    $storage->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $storage->team_id = 1;

    $this->authService->shouldReceive('canManageIntegrations')
        ->with($user, 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->update($user, $storage))->toBeTrue();
});

it('denies user without permission from updating S3 storage', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'member']],
    ]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $storage = Mockery::mock(S3Storage::class)->makePartial();
    $storage->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $storage->team_id = 1;

    $this->authService->shouldReceive('canManageIntegrations')
        ->with($user, 1)
        ->once()
        ->andReturn(false);

    expect($this->policy->update($user, $storage))->toBeFalse();
});

it('denies team member to update S3 storage from another team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $storage = Mockery::mock(S3Storage::class)->makePartial();
    $storage->shouldReceive('getAttribute')->with('team_id')->andReturn(2);
    $storage->team_id = 2;

    expect($this->policy->update($user, $storage))->toBeFalse();
});

it('allows user with permission to delete S3 storage from their team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $storage = Mockery::mock(S3Storage::class)->makePartial();
    $storage->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $storage->team_id = 1;

    $this->authService->shouldReceive('canManageIntegrations')
        ->with($user, 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->delete($user, $storage))->toBeTrue();
});

it('denies team member to delete S3 storage from another team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'owner']],
    ]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $storage = Mockery::mock(S3Storage::class)->makePartial();
    $storage->shouldReceive('getAttribute')->with('team_id')->andReturn(2);
    $storage->team_id = 2;

    expect($this->policy->delete($user, $storage))->toBeFalse();
});

it('allows team member to validate connection of S3 storage from their team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'member']],
    ]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $storage = Mockery::mock(S3Storage::class)->makePartial();
    $storage->shouldReceive('getAttribute')->with('team_id')->andReturn(1);
    $storage->team_id = 1;

    expect($this->policy->validateConnection($user, $storage))->toBeTrue();
});

it('denies team member to validate connection of S3 storage from another team', function () {
    $teams = collect([
        (object) ['id' => 1, 'pivot' => (object) ['role' => 'admin']],
    ]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('teams')->andReturn($teams);

    $storage = Mockery::mock(S3Storage::class)->makePartial();
    $storage->shouldReceive('getAttribute')->with('team_id')->andReturn(2);
    $storage->team_id = 2;

    expect($this->policy->validateConnection($user, $storage))->toBeFalse();
});
