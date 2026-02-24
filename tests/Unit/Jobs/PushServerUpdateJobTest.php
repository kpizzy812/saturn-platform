<?php

use App\Jobs\PushServerUpdateJob;
use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

afterEach(function () {
    Mockery::close();
});

function makePushServerMock(): Server
{
    $server = Mockery::mock(Server::class)->makePartial();
    $server->id = 1;
    $server->uuid = 'push-test-uuid';
    $server->name = 'push-test-server';

    return $server;
}

// ---------------------------------------------------------------------------
// Job configuration
// ---------------------------------------------------------------------------

it('implements ShouldQueue and ShouldBeEncrypted', function () {
    $interfaces = class_implements(PushServerUpdateJob::class);

    expect($interfaces)->toContain(ShouldQueue::class)
        ->and($interfaces)->toContain(ShouldBeEncrypted::class);
});

it('has $tries equal to 1 and $timeout equal to 30', function () {
    $server = makePushServerMock();
    $job = new PushServerUpdateJob($server, []);

    expect($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(30);
});

// ---------------------------------------------------------------------------
// Middleware
// ---------------------------------------------------------------------------

it('returns a single WithoutOverlapping middleware', function () {
    $server = makePushServerMock();
    $job = new PushServerUpdateJob($server, []);
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithoutOverlapping::class);
});

it('middleware key includes server uuid', function () {
    $server = makePushServerMock();
    $server->uuid = 'unique-uuid-456';
    $job = new PushServerUpdateJob($server, []);

    $serialized = serialize($job->middleware()[0]);

    expect($serialized)->toContain('push-server-update-unique-uuid-456');
});

// ---------------------------------------------------------------------------
// Constructor
// ---------------------------------------------------------------------------

it('stores server and data from constructor', function () {
    $server = makePushServerMock();
    $data = ['containers' => []];
    $job = new PushServerUpdateJob($server, $data);

    expect($job->server)->toBe($server)
        ->and($job->data)->toBe($data);
});

it('initializes all collection properties in constructor', function () {
    $server = makePushServerMock();
    $job = new PushServerUpdateJob($server, []);

    expect($job->containers)->toBeEmpty()
        ->and($job->foundApplicationIds)->toBeEmpty()
        ->and($job->foundDatabaseUuids)->toBeEmpty()
        ->and($job->foundServiceApplicationIds)->toBeEmpty()
        ->and($job->foundApplicationPreviewsIds)->toBeEmpty()
        ->and($job->foundServiceDatabaseIds)->toBeEmpty();
});

// ---------------------------------------------------------------------------
// failed() callback
// ---------------------------------------------------------------------------

it('has a public failed() method', function () {
    $reflection = new ReflectionClass(PushServerUpdateJob::class);

    expect($reflection->hasMethod('failed'))->toBeTrue()
        ->and($reflection->getMethod('failed')->isPublic())->toBeTrue();
});

it('failed() logs error with server context', function () {
    Log::shouldReceive('error')->once()->with('PushServerUpdateJob permanently failed', Mockery::on(function ($context) {
        return $context['server_id'] === 1
            && $context['server_name'] === 'push-test-server'
            && str_contains($context['error'], 'Sentinel timeout');
    }));

    $server = makePushServerMock();
    $job = new PushServerUpdateJob($server, []);

    $job->failed(new \RuntimeException('Sentinel timeout'));
});
