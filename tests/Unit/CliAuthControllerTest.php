<?php

use App\Http\Controllers\Api\CliAuthController;
use App\Models\CliAuthSession;
use App\Models\User;
use Illuminate\Http\Request;

test('check validates secret is required', function () {
    $controller = new CliAuthController;

    $request = Request::create('/api/v1/cli/auth/check', 'GET');

    try {
        $controller->check($request);
        test()->fail('Expected validation exception');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('secret');
    }
});

test('check validates secret must be exactly 40 characters', function () {
    $controller = new CliAuthController;

    $request = Request::create('/api/v1/cli/auth/check?secret=tooshort', 'GET');

    try {
        $controller->check($request);
        test()->fail('Expected validation exception');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('secret');
    }
});

test('approve validates code must be 9 characters', function () {
    $controller = new CliAuthController;

    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1;

    // Code is too short — validation should reject before any DB query
    $request = Request::create('/cli/auth/approve', 'POST', [
        'code' => 'bad',
    ]);
    $request->setUserResolver(fn () => $user);

    try {
        $controller->approve($request);
        test()->fail('Expected validation exception');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('code');
    }
});

test('approve validates team_id is required', function () {
    $controller = new CliAuthController;

    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1;

    $request = Request::create('/cli/auth/approve', 'POST', [
        'code' => 'ABCD-EFGH',
    ]);
    $request->setUserResolver(fn () => $user);

    try {
        $controller->approve($request);
        test()->fail('Expected validation exception');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('team_id');
    }
});

test('deny validates code must be 9 characters', function () {
    $controller = new CliAuthController;

    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1;

    $request = Request::create('/cli/auth/deny', 'POST', [
        'code' => 'bad',
    ]);
    $request->setUserResolver(fn () => $user);

    try {
        $controller->deny($request);
        test()->fail('Expected validation exception');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('code');
    }
});

test('deny validates code is required', function () {
    $controller = new CliAuthController;

    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 1;

    $request = Request::create('/cli/auth/deny', 'POST', []);
    $request->setUserResolver(fn () => $user);

    try {
        $controller->deny($request);
        test()->fail('Expected validation exception');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('code');
    }
});

test('CliAuthSession model has correct fillable fields for security', function () {
    $model = new CliAuthSession;
    $fillable = $model->getFillable();

    // Ensure no sensitive fields are fillable
    expect($fillable)->not->toContain('is_superadmin')
        ->not->toContain('platform_role');

    // Ensure required fields are present
    expect($fillable)->toContain('code')
        ->toContain('secret')
        ->toContain('status')
        ->toContain('ip_address')
        ->toContain('expires_at');
});
