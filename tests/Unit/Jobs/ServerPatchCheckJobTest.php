<?php

use App\Jobs\ServerPatchCheckJob;
use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

afterEach(function () {
    Mockery::close();
});

function makePatchServerMock(): Server
{
    $server = Mockery::mock(Server::class)->makePartial();
    $server->id = 1;
    $server->uuid = 'patch-test-uuid';
    $server->name = 'patch-test-server';

    return $server;
}

// ---------------------------------------------------------------------------
// Job configuration
// ---------------------------------------------------------------------------

it('implements ShouldQueue and ShouldBeEncrypted', function () {
    $interfaces = class_implements(ServerPatchCheckJob::class);

    expect($interfaces)->toContain(ShouldQueue::class)
        ->and($interfaces)->toContain(ShouldBeEncrypted::class);
});

it('has correct tries, backoff, and timeout', function () {
    $server = makePatchServerMock();
    $job = new ServerPatchCheckJob($server);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 30, 60])
        ->and($job->timeout)->toBe(600);
});

// ---------------------------------------------------------------------------
// Middleware
// ---------------------------------------------------------------------------

it('returns a single WithoutOverlapping middleware', function () {
    $server = makePatchServerMock();
    $job = new ServerPatchCheckJob($server);
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
});

it('middleware key includes server uuid', function () {
    $server = makePatchServerMock();
    $server->uuid = 'unique-patch-uuid';
    $job = new ServerPatchCheckJob($server);

    $serialized = serialize($job->middleware()[0]);

    expect($serialized)->toContain('server-patch-check-unique-patch-uuid');
});

// ---------------------------------------------------------------------------
// Constructor
// ---------------------------------------------------------------------------

it('stores server from constructor', function () {
    $server = makePatchServerMock();
    $job = new ServerPatchCheckJob($server);

    expect($job->server)->toBe($server);
});

// ---------------------------------------------------------------------------
// handle() source-level checks
// ---------------------------------------------------------------------------

it('handle wraps logic in try-catch Throwable', function () {
    $source = file_get_contents(app_path('Jobs/ServerPatchCheckJob.php'));

    expect($source)->toContain('catch (\\Throwable $e)');
});

it('handle logs error on failure', function () {
    $source = file_get_contents(app_path('Jobs/ServerPatchCheckJob.php'));

    expect($source)->toContain('ServerPatchCheckJob failed:');
});

it('handle checks server status before proceeding', function () {
    $source = file_get_contents(app_path('Jobs/ServerPatchCheckJob.php'));

    expect($source)->toContain('$this->server->serverStatus()');
});

// ---------------------------------------------------------------------------
// failed() callback
// ---------------------------------------------------------------------------

it('has a public failed() method', function () {
    $reflection = new ReflectionClass(ServerPatchCheckJob::class);

    expect($reflection->hasMethod('failed'))->toBeTrue()
        ->and($reflection->getMethod('failed')->isPublic())->toBeTrue();
});

it('failed() logs error with server context', function () {
    Log::shouldReceive('error')->once()->with('ServerPatchCheckJob permanently failed', Mockery::on(function ($context) {
        return $context['server_id'] === 1
            && $context['server_name'] === 'patch-test-server'
            && str_contains($context['error'], 'SSH timeout');
    }));

    $server = makePatchServerMock();
    $job = new ServerPatchCheckJob($server);

    $job->failed(new \RuntimeException('SSH timeout'));
});
