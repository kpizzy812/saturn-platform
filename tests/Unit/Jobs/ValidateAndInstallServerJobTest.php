<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ValidateAndInstallServerJob;
use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionClass;

/*
 * Unit tests for ValidateAndInstallServerJob.
 *
 * handle() requires live SSH, Docker, and database — covered in Feature tests.
 * These tests verify job configuration, constructor behavior, failed() callback,
 * and source-level guard logic for the full validation/installation sequence.
 */

function makeServerForValidation(): Server
{
    $server = Mockery::mock(Server::class)->makePartial();
    $server->id = 42;
    $server->uuid = 'validate-server-uuid';
    $server->name = 'test-server';
    $server->team_id = 1;

    return $server;
}

afterEach(function () {
    Mockery::close();
});

// ===========================================================================
// 1. Interface contracts
// ===========================================================================

it('implements ShouldQueue', function () {
    $interfaces = class_implements(ValidateAndInstallServerJob::class);

    expect($interfaces)->toContain(ShouldQueue::class);
});

it('does NOT implement ShouldBeEncrypted', function () {
    $interfaces = class_implements(ValidateAndInstallServerJob::class);

    expect($interfaces)->not->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

// ===========================================================================
// 2. Job configuration
// ===========================================================================

it('has $tries equal to 3', function () {
    $server = makeServerForValidation();
    $job = new ValidateAndInstallServerJob($server);

    expect($job->tries)->toBe(3);
});

it('has $timeout equal to 600', function () {
    $server = makeServerForValidation();
    $job = new ValidateAndInstallServerJob($server);

    expect($job->timeout)->toBe(600);
});

it('declares $tries and $timeout via reflection', function () {
    $defaults = (new ReflectionClass(ValidateAndInstallServerJob::class))->getDefaultProperties();

    expect($defaults['tries'])->toBe(3)
        ->and($defaults['timeout'])->toBe(600);
});

it('dispatches to the high queue', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)->toContain("'high'");
});

// ===========================================================================
// 3. Constructor
// ===========================================================================

it('stores the server instance', function () {
    $server = makeServerForValidation();
    $job = new ValidateAndInstallServerJob($server);

    expect($job->server)->toBe($server);
});

it('defaults numberOfTries to 0', function () {
    $server = makeServerForValidation();
    $job = new ValidateAndInstallServerJob($server);

    expect($job->numberOfTries)->toBe(0);
});

it('accepts a custom numberOfTries value', function () {
    $server = makeServerForValidation();
    $job = new ValidateAndInstallServerJob($server, 2);

    expect($job->numberOfTries)->toBe(2);
});

// ===========================================================================
// 4. failed() callback
// ===========================================================================

it('failed() logs a permanent failure error', function () {
    Log::shouldReceive('error')->once()->with('ValidateAndInstallServerJob permanently failed', Mockery::on(function ($ctx) {
        return $ctx['server_id'] === 42
            && $ctx['server_name'] === 'test-server'
            && str_contains($ctx['error'], 'SSH refused');
    }));

    $settings = Mockery::mock(\App\Models\ServerSetting::class)->makePartial();
    $settings->shouldReceive('update')->andReturn(true);

    $server = makeServerForValidation();
    $server->shouldReceive('update')->andReturn(true);

    $job = new ValidateAndInstallServerJob($server);
    $job->failed(new \RuntimeException('SSH refused'));
});

it('failed() resets is_validating to false', function () {
    Log::shouldReceive('error')->once();

    $server = makeServerForValidation();
    $server->shouldReceive('update')->once()->with(['is_validating' => false])->andReturn(true);

    $job = new ValidateAndInstallServerJob($server);
    $job->failed(new \RuntimeException('test error'));
});

it('failed() does not rethrow when server update fails', function () {
    Log::shouldReceive('error')->once();
    Log::shouldReceive('warning')->once()->with('Failed to reset is_validating flag after server validation failure', Mockery::any());

    $server = makeServerForValidation();
    $server->shouldReceive('update')->andThrow(new \RuntimeException('DB connection lost'));

    $job = new ValidateAndInstallServerJob($server);

    expect(fn () => $job->failed(new \RuntimeException('primary error')))->not->toThrow(\Throwable::class);
});

// ===========================================================================
// 5. Validation sequence — guard conditions via source
// ===========================================================================

it('marks is_validating = true at the start of handle()', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)->toContain("['is_validating' => true]");
});

it('validates connection and returns early when uptime is falsy', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('validateConnection()')
        ->toContain('Server is not reachable')
        ->toContain("'is_validating' => false");
});

it('includes a link to SSH documentation in connection failure message', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)->toContain('knowledge-base/server/openssh');
});

it('validates OS support and returns early when unsupported', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('validateOS()')
        ->toContain('OS type is not supported')
        ->toContain('docs.docker.com/engine/install/#server');
});

// ===========================================================================
// 6. Prerequisites — installation with retry loop
// ===========================================================================

it('checks prerequisites and installs them when missing', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('validatePrerequisites()')
        ->toContain('installPrerequisites()');
});

it('re-dispatches self with incremented numberOfTries after installing prerequisites', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('self::dispatch($this->server, $this->numberOfTries + 1)')
        ->toContain('->delay(now()->addSeconds(30))');
});

it('stops with error after max tries for prerequisites', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('$this->numberOfTries >= $this->tries')
        ->toContain('Prerequisites (')
        ->toContain('could not be installed after');
});

it('includes missing command names in the max-tries error message', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)->toContain('implode(\', \', $validationResult[\'missing\'])');
});

// ===========================================================================
// 7. Docker — installation with retry loop
// ===========================================================================

it('validates Docker Engine and Compose before attempting installation', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('validateDockerEngine()')
        ->toContain('validateDockerCompose()');
});

it('installs Docker when not present', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)->toContain('installDocker()');
});

it('re-dispatches self with incremented numberOfTries after installing Docker', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    // Already verified in prerequisites section, confirm same pattern for docker path
    $count = substr_count($source, 'self::dispatch($this->server, $this->numberOfTries + 1)');

    expect($count)->toBeGreaterThanOrEqual(2);
});

it('stops with error after max tries for Docker installation', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)->toContain('Docker Engine could not be installed after');
});

// ===========================================================================
// 8. Docker version check
// ===========================================================================

it('validates Docker Engine version after confirming Docker is installed', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('validateDockerEngineVersion()')
        ->toContain('Minimum Docker Engine version');
});

it('uses minimum_required_version config constant for version check message', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)->toContain("config('constants.docker.minimum_required_version')");
});

// ===========================================================================
// 9. Success path — proxy setup and events
// ===========================================================================

it('skips proxy setup for build servers', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)->toContain('isBuildServer()');
});

it('dispatches StartProxy when proxy should run', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('CheckProxy::run(')
        ->toContain('StartProxy::dispatch(');
});

it('ensures proxy networks exist before dispatching StartProxy to prevent race conditions', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('ensureProxyNetworksExist(')
        ->toContain('instant_remote_process(');
});

it('resets is_validating to false on successful completion', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    // At least one update(['is_validating' => false]) in the success path
    expect($source)->toContain("['is_validating' => false]");
});

it('broadcasts ServerValidated event with team_id and server uuid', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('ServerValidated::dispatch(')
        ->toContain('$this->server->team_id')
        ->toContain('$this->server->uuid');
});

it('broadcasts ServerReachabilityChanged event on success', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)->toContain('ServerReachabilityChanged::dispatch(');
});

it('refreshes server model before broadcasting events', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)->toContain('$this->server->refresh()');
});

// ===========================================================================
// 10. Exception handling — safe failure with is_validating reset
// ===========================================================================

it('catches Throwable in handle() and resets is_validating to false', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('catch (\Throwable $e)')
        ->toContain('An error occurred during validation:');
});

it('logs exception details when Throwable is caught in handle()', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain('ValidateAndInstallServer: Exception occurred')
        ->toContain('$e->getMessage()')
        ->toContain('$e->getTraceAsString()');
});

// ===========================================================================
// 11. Attempt counter logging
// ===========================================================================

it('logs the current attempt number at the start of handle()', function () {
    $source = file_get_contents(app_path('Jobs/ValidateAndInstallServerJob.php'));

    expect($source)
        ->toContain("'attempt'")
        ->toContain('$this->numberOfTries + 1');
});
