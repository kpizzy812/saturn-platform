<?php

use App\Models\User;
use App\Policies\NotificationPolicy;
use App\Services\Authorization\ResourceAuthorizationService;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->authService = Mockery::mock(ResourceAuthorizationService::class);
    $this->policy = new NotificationPolicy($this->authService);
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| View Tests
|--------------------------------------------------------------------------
*/

it('denies viewing notification settings when team is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();

    $notificationSettings->shouldReceive('getAttribute')
        ->with('team')
        ->andReturn(null);

    expect($this->policy->view($user, $notificationSettings))->toBeFalse();
});

it('allows viewing notification settings when user is team member', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->teams = collect([(object) ['id' => 1]]);

    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();
    $team = (object) ['id' => 1];

    $notificationSettings->shouldReceive('getAttribute')
        ->with('team')
        ->andReturn($team);

    expect($this->policy->view($user, $notificationSettings))->toBeTrue();
});

it('denies viewing notification settings when user is not team member', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $user->teams = collect([(object) ['id' => 2]]);

    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();
    $team = (object) ['id' => 1];

    $notificationSettings->shouldReceive('getAttribute')
        ->with('team')
        ->andReturn($team);

    expect($this->policy->view($user, $notificationSettings))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Update Tests
|--------------------------------------------------------------------------
*/

it('denies updating notification settings when team is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();

    $notificationSettings->shouldReceive('getAttribute')
        ->with('team')
        ->andReturn(null);

    expect($this->policy->update($user, $notificationSettings))->toBeFalse();
});

it('allows updating notification settings when user has permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();
    $team = (object) ['id' => 1];

    $notificationSettings->shouldReceive('getAttribute')
        ->with('team')
        ->andReturn($team);

    $this->authService->shouldReceive('canManageNotifications')
        ->with($user, 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->update($user, $notificationSettings))->toBeTrue();
});

it('denies updating notification settings when user lacks permission', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();
    $team = (object) ['id' => 1];

    $notificationSettings->shouldReceive('getAttribute')
        ->with('team')
        ->andReturn($team);

    $this->authService->shouldReceive('canManageNotifications')
        ->with($user, 1)
        ->once()
        ->andReturn(false);

    expect($this->policy->update($user, $notificationSettings))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Manage Tests
|--------------------------------------------------------------------------
*/

it('delegates manage to update check', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();
    $team = (object) ['id' => 1];

    $notificationSettings->shouldReceive('getAttribute')
        ->with('team')
        ->andReturn($team);

    $this->authService->shouldReceive('canManageNotifications')
        ->with($user, 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->manage($user, $notificationSettings))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| SendTest Tests
|--------------------------------------------------------------------------
*/

it('delegates sendTest to update check', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();
    $team = (object) ['id' => 1];

    $notificationSettings->shouldReceive('getAttribute')
        ->with('team')
        ->andReturn($team);

    $this->authService->shouldReceive('canManageNotifications')
        ->with($user, 1)
        ->once()
        ->andReturn(true);

    expect($this->policy->sendTest($user, $notificationSettings))->toBeTrue();
});
