<?php

use App\Models\EnvironmentVariable;
use App\Models\User;
use App\Policies\EnvironmentVariablePolicy;

beforeEach(function () {
    $this->policy = new EnvironmentVariablePolicy;
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| ViewAny Tests
|--------------------------------------------------------------------------
*/

it('allows viewing any environment variables', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->viewAny($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Create Tests
|--------------------------------------------------------------------------
*/

it('allows creating environment variables', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->create($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| View/Update/Delete/Restore/ForceDelete/ManageEnvironment Tests
|--------------------------------------------------------------------------
| NOTE: These tests require complex mocking of Eloquent relationships and
| direct property access ($user->teams) which is difficult in unit tests.
| These methods should be tested in feature tests instead where real database
| models can be used.
|--------------------------------------------------------------------------
*/

it('denies viewing environment variable when resource is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $envVar = Mockery::mock(EnvironmentVariable::class)->makePartial()->shouldIgnoreMissing();

    $envVar->resourceable = null;

    expect($this->policy->view($user, $envVar))->toBeFalse();
});

it('denies restoring environment variable when resource is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $envVar = Mockery::mock(EnvironmentVariable::class)->makePartial()->shouldIgnoreMissing();

    $envVar->resourceable = null;

    expect($this->policy->restore($user, $envVar))->toBeFalse();
});
