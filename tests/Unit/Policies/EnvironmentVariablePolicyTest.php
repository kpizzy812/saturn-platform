<?php

use App\Models\EnvironmentVariable;
use App\Models\User;
use App\Policies\EnvironmentVariablePolicy;
use App\Services\Authorization\ResourceAuthorizationService;

beforeEach(function () {
    $this->authService = Mockery::mock(ResourceAuthorizationService::class);
    $this->policy = new EnvironmentVariablePolicy($this->authService);
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
| View/Update/Delete Tests
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

it('denies updating environment variable when resource is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $envVar = Mockery::mock(EnvironmentVariable::class)->makePartial()->shouldIgnoreMissing();

    $envVar->resourceable = null;

    expect($this->policy->update($user, $envVar))->toBeFalse();
});

it('denies deleting environment variable when resource is null', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $envVar = Mockery::mock(EnvironmentVariable::class)->makePartial()->shouldIgnoreMissing();

    $envVar->resourceable = null;

    expect($this->policy->delete($user, $envVar))->toBeFalse();
});
