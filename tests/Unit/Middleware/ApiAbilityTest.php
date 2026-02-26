<?php

use App\Http\Middleware\ApiAbility;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\TransientToken;

// Helper: create a mock user with a given team role and no token (session auth)
function makeSessionUser(string $role, bool $isSuperAdmin = false): object
{
    $user = Mockery::mock(\App\Models\User::class)->makePartial();
    $user->shouldReceive('currentAccessToken')->andReturn(new TransientToken);
    $user->shouldReceive('role')->andReturn($role);
    $user->shouldReceive('isSuperAdmin')->andReturn($isSuperAdmin);
    $user->shouldReceive('tokenCan')->andReturn(false);

    return $user;
}

// Helper: create a mock user with a Sanctum API token
function makeTokenUser(array $abilities): object
{
    $token = Mockery::mock(\Laravel\Sanctum\Contracts\HasAbilities::class);
    $token->shouldReceive('cant')->andReturnUsing(fn ($a) => ! in_array($a, $abilities));

    $user = Mockery::mock(\App\Models\User::class)->makePartial();
    $user->shouldReceive('currentAccessToken')->andReturn($token);
    $user->shouldReceive('tokenCan')->andReturnUsing(fn ($a) => in_array($a, $abilities));

    return $user;
}

function makeRequest(?object $user): Request
{
    $request = Request::create('/api/v1/test', 'POST');
    $request->setUserResolver(fn () => $user);

    return $request;
}

$next = fn () => new Response('OK', 200);

afterEach(fn () => Mockery::close());

// --- Session user: role-based access ---

test('session owner can access deploy endpoint', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(makeSessionUser('owner'));

    $response = $middleware->handle($request, $next, 'deploy');

    expect($response->getStatusCode())->toBe(200);
});

test('session member can access deploy endpoint', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(makeSessionUser('member'));

    $response = $middleware->handle($request, $next, 'deploy');

    expect($response->getStatusCode())->toBe(200);
});

test('session developer can access read:sensitive endpoint', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(makeSessionUser('developer'));

    $response = $middleware->handle($request, $next, 'read:sensitive');

    expect($response->getStatusCode())->toBe(200);
});

test('session viewer can access read endpoint', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(makeSessionUser('viewer'));

    $response = $middleware->handle($request, $next, 'read');

    expect($response->getStatusCode())->toBe(200);
});

test('session viewer is denied deploy endpoint', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(makeSessionUser('viewer'));

    $response = $middleware->handle($request, $next, 'deploy');

    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toContain('Missing required permissions');
});

test('session viewer is denied write endpoint', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(makeSessionUser('viewer'));

    $response = $middleware->handle($request, $next, 'write');

    expect($response->getStatusCode())->toBe(403);
});

test('session member is denied root endpoint', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(makeSessionUser('member'));

    $response = $middleware->handle($request, $next, 'root');

    expect($response->getStatusCode())->toBe(403);
});

test('session member is denied read:sensitive endpoint', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(makeSessionUser('member'));

    $response = $middleware->handle($request, $next, 'read:sensitive');

    expect($response->getStatusCode())->toBe(403);
});

test('session owner can access root endpoint', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(makeSessionUser('owner'));

    $response = $middleware->handle($request, $next, 'root');

    expect($response->getStatusCode())->toBe(200);
});

test('session admin can access root endpoint', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(makeSessionUser('admin'));

    $response = $middleware->handle($request, $next, 'root');

    expect($response->getStatusCode())->toBe(200);
});

test('superadmin session user bypasses all ability checks', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(makeSessionUser('viewer', isSuperAdmin: true));

    $response = $middleware->handle($request, $next, 'root');

    expect($response->getStatusCode())->toBe(200);
});

// --- Unauthenticated ---

test('unauthenticated request returns 401', function () use ($next) {
    $middleware = new ApiAbility;
    $request = makeRequest(null);

    // parent::handle() will throw AuthenticationException for null user
    // We mock it to throw directly
    $request->setUserResolver(function () {
        throw new \Illuminate\Auth\AuthenticationException;
    });

    $response = $middleware->handle($request, $next, 'read');

    expect($response->getStatusCode())->toBe(401);
    expect($response->getContent())->toContain('Unauthenticated');
});
