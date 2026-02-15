<?php

use App\Models\User;
use App\Policies\NotificationPolicy;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->policy = new NotificationPolicy;
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

it('allows viewing notification settings when team exists', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock();

    $notificationSettings->shouldReceive('getAttribute')
        ->with('team')
        ->andReturn($team);

    expect($this->policy->view($user, $notificationSettings))->toBeTrue();
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

it('allows updating notification settings when team exists', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();
    $team = Mockery::mock();

    $notificationSettings->shouldReceive('getAttribute')
        ->with('team')
        ->andReturn($team);

    expect($this->policy->update($user, $notificationSettings))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Manage Tests
|--------------------------------------------------------------------------
*/

it('allows managing notification settings', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->manage($user, $notificationSettings))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| SendTest Tests
|--------------------------------------------------------------------------
*/

it('allows sending test notifications', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $notificationSettings = Mockery::mock(Model::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->sendTest($user, $notificationSettings))->toBeTrue();
});
