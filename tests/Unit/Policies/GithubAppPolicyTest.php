<?php

use App\Models\GithubApp;
use App\Models\User;
use App\Policies\GithubAppPolicy;

beforeEach(function () {
    $this->policy = new GithubAppPolicy;
});

afterEach(function () {
    Mockery::close();
});

/*
|--------------------------------------------------------------------------
| ViewAny Tests
|--------------------------------------------------------------------------
*/

it('allows viewing any github apps', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->viewAny($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| View Tests
|--------------------------------------------------------------------------
*/

it('allows viewing github app', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $githubApp = Mockery::mock(GithubApp::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->view($user, $githubApp))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Create Tests
|--------------------------------------------------------------------------
*/

it('allows creating github apps', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->create($user))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Update Tests
|--------------------------------------------------------------------------
*/

it('allows updating github app when system wide', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $githubApp = Mockery::mock(GithubApp::class)->makePartial()->shouldIgnoreMissing();

    $githubApp->shouldReceive('getAttribute')
        ->with('is_system_wide')
        ->andReturn(true);

    expect($this->policy->update($user, $githubApp))->toBeTrue();
});

it('allows updating github app when not system wide', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $githubApp = Mockery::mock(GithubApp::class)->makePartial()->shouldIgnoreMissing();

    $githubApp->shouldReceive('getAttribute')
        ->with('is_system_wide')
        ->andReturn(false);

    expect($this->policy->update($user, $githubApp))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Delete Tests
|--------------------------------------------------------------------------
*/

it('allows deleting github app when system wide', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $githubApp = Mockery::mock(GithubApp::class)->makePartial()->shouldIgnoreMissing();

    $githubApp->shouldReceive('getAttribute')
        ->with('is_system_wide')
        ->andReturn(true);

    expect($this->policy->delete($user, $githubApp))->toBeTrue();
});

it('allows deleting github app when not system wide', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $githubApp = Mockery::mock(GithubApp::class)->makePartial()->shouldIgnoreMissing();

    $githubApp->shouldReceive('getAttribute')
        ->with('is_system_wide')
        ->andReturn(false);

    expect($this->policy->delete($user, $githubApp))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Restore Tests
|--------------------------------------------------------------------------
*/

it('denies restoring github app', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $githubApp = Mockery::mock(GithubApp::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->restore($user, $githubApp))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| ForceDelete Tests
|--------------------------------------------------------------------------
*/

it('denies force deleting github app', function () {
    $user = Mockery::mock(User::class)->makePartial()->shouldIgnoreMissing();
    $githubApp = Mockery::mock(GithubApp::class)->makePartial()->shouldIgnoreMissing();

    expect($this->policy->forceDelete($user, $githubApp))->toBeFalse();
});
