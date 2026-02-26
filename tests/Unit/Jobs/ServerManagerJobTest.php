<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ServerManagerJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionClass;

/*
 * Unit tests for ServerManagerJob.
 *
 * handle() requires a live database and SSH — covered in Feature tests.
 * These tests verify job configuration, interface contracts, failed() callback,
 * and source-level logic (cron expressions, dispatched job types, error isolation).
 */

afterEach(function () {
    Mockery::close();
});

// ===========================================================================
// 1. Interface contracts
// ===========================================================================

it('implements ShouldQueue', function () {
    $interfaces = class_implements(ServerManagerJob::class);

    expect($interfaces)->toContain(ShouldQueue::class);
});

it('does NOT implement ShouldBeEncrypted (no sensitive data in constructor)', function () {
    $interfaces = class_implements(ServerManagerJob::class);

    expect($interfaces)->not->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

// ===========================================================================
// 2. Job configuration
// ===========================================================================

it('has $tries equal to 1', function () {
    $job = new ServerManagerJob;

    expect($job->tries)->toBe(1);
});

it('has $timeout equal to 120', function () {
    $job = new ServerManagerJob;

    expect($job->timeout)->toBe(120);
});

it('declares $tries and $timeout via reflection', function () {
    $defaults = (new ReflectionClass(ServerManagerJob::class))->getDefaultProperties();

    expect($defaults['tries'])->toBe(1)
        ->and($defaults['timeout'])->toBe(120);
});

it('dispatches to the high queue', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain("'high'");
});

// ===========================================================================
// 3. Constructor — no parameters required
// ===========================================================================

it('can be instantiated with no arguments', function () {
    $job = new ServerManagerJob;

    expect($job)->toBeInstanceOf(ServerManagerJob::class);
});

// ===========================================================================
// 4. failed() callback
// ===========================================================================

it('failed() logs an error with the exception message', function () {
    Log::shouldReceive('error')->once()->with('ServerManagerJob permanently failed', Mockery::on(function ($ctx) {
        return str_contains($ctx['error'], 'Database unavailable');
    }));

    $job = new ServerManagerJob;
    $job->failed(new \RuntimeException('Database unavailable'));
});

it('failed() logs using the error channel', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain("Log::error('ServerManagerJob permanently failed'");
});

// ===========================================================================
// 5. Cron expressions — verified via source
// ===========================================================================

it('uses every-minute cron for standalone check frequency', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain("'* * * * *'");
});

it('uses every-5-minutes cron for cloud check frequency', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain("'*/5 * * * *'");
});

it('uses daily-midnight cron for sentinel restart', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain("'0 0 * * *'");
});

it('uses weekly-sunday cron for patch checks', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain("'0 0 * * 0'");
});

it('uses hourly cron for sentinel update checks', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain("'0 * * * *'");
});

// ===========================================================================
// 6. Job dispatch targets — verified via source
// ===========================================================================

it('dispatches ServerConnectionCheckJob for connection checks', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain('ServerConnectionCheckJob::dispatch');
});

it('dispatches ServerCheckJob when sentinel is out of sync', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain('ServerCheckJob::dispatch');
});

it('dispatches ServerStorageCheckJob for disk usage checks', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain('ServerStorageCheckJob::dispatch');
});

it('dispatches ServerPatchCheckJob for weekly patch checks', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain('ServerPatchCheckJob::dispatch');
});

it('dispatches CheckAndStartSentinelJob for hourly sentinel updates', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain('CheckAndStartSentinelJob::dispatch');
});

// ===========================================================================
// 7. Error isolation — per-server exceptions don't cascade
// ===========================================================================

it('wraps per-server task dispatch in try/catch to isolate failures', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    // Both dispatchConnectionChecks and processScheduledTasks have try/catch
    expect(substr_count($source, 'catch (\Exception $e)'))->toBeGreaterThanOrEqual(2);
});

it('logs to scheduled-errors channel when server dispatch fails', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain("Log::channel('scheduled-errors')->error");
});

// ===========================================================================
// 8. Execution time is frozen at job start
// ===========================================================================

it('freezes execution time at job start to prevent time drift', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)
        ->toContain('$this->executionTime = Carbon::now()')
        ->toContain('$this->executionTime');
});

// ===========================================================================
// 9. shouldRunNow() — private method verifiable via reflection
// ===========================================================================

it('shouldRunNow is a private method', function () {
    $reflection = new ReflectionClass(ServerManagerJob::class);

    expect($reflection->hasMethod('shouldRunNow'))->toBeTrue()
        ->and($reflection->getMethod('shouldRunNow')->isPrivate())->toBeTrue();
});

it('shouldRunNow uses CronExpression::isDue against frozen execution time', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)
        ->toContain('CronExpression')
        ->toContain('isDue(')
        ->toContain('$this->executionTime');
});

// ===========================================================================
// 10. Cloud mode — eager loads subscription for subscription check
// ===========================================================================

it('eager loads team.subscription when running in cloud mode', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain("'team.subscription'");
});

it('filters servers by stripe_invoice_paid in cloud mode', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain('stripe_invoice_paid');
});

it('includes system team servers (id=0) in cloud mode', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain('Team::find(0)');
});

// ===========================================================================
// 11. Dummy server exclusion
// ===========================================================================

it('excludes the dummy server (1.2.3.4) from server collection', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)->toContain("where('ip', '!=', '1.2.3.4')");
});

// ===========================================================================
// 12. Sentinel restart uses closure dispatch (anonymous function)
// ===========================================================================

it('restarts sentinel container via closure dispatch', function () {
    $source = file_get_contents(app_path('Jobs/ServerManagerJob.php'));

    expect($source)
        ->toContain('dispatch(function () use ($server)')
        ->toContain("restartContainer('saturn-sentinel')");
});
