<?php

use App\Jobs\CheckAndStartSentinelJob;
use App\Jobs\ServerCheckJob;
use App\Jobs\ServerConnectionCheckJob;
use App\Jobs\ServerManagerJob;
use App\Jobs\ServerPatchCheckJob;
use App\Jobs\ServerStorageCheckJob;

/**
 * ServerManagerJob tests for Sentinel scheduling behavior.
 *
 * These tests verify the job structure and scheduling logic using
 * reflection and source code analysis since static Eloquent method
 * mocking (Server::where) is not available in Unit tests.
 */
beforeEach(function () {
    $this->reflection = new ReflectionClass(ServerManagerJob::class);
    $this->sourceFile = $this->reflection->getFileName();
    $this->sourceCode = file_get_contents($this->sourceFile);
});

afterEach(function () {
    \Mockery::close();
});

// Helper function to extract method source code
function getServerManagerMethodSource(string $file, string $methodName): string
{
    $reflection = new ReflectionMethod(ServerManagerJob::class, $methodName);
    $startLine = $reflection->getStartLine();
    $endLine = $reflection->getEndLine();

    return implode('', array_slice(file($file), $startLine - 1, $endLine - $startLine + 1));
}

it('dispatches CheckAndStartSentinelJob hourly for sentinel-enabled servers', function () {
    // Verify the job dispatches CheckAndStartSentinelJob with hourly cron
    expect($this->sourceCode)
        ->toContain('CheckAndStartSentinelJob::dispatch')
        ->toContain("shouldRunNow('0 * * * *')") // Hourly cron expression
        ->toContain('isSentinelEnabled()');

    // Verify the condition checks sentinel enabled status
    $methodSource = getServerManagerMethodSource($this->sourceFile, 'processServerTasks');
    expect($methodSource)
        ->toContain('$isSentinelEnabled && $this->shouldRunNow')
        ->toContain('CheckAndStartSentinelJob::dispatch($server)');
});

it('does not dispatch CheckAndStartSentinelJob for servers without sentinel enabled', function () {
    // Verify sentinel check requires isSentinelEnabled() to be true
    $methodSource = getServerManagerMethodSource($this->sourceFile, 'processServerTasks');

    // The CheckAndStartSentinelJob dispatch is conditional on $isSentinelEnabled
    expect($methodSource)
        ->toContain('$isSentinelEnabled = $server->isSentinelEnabled()')
        ->toContain('if ($isSentinelEnabled && $this->shouldRunNow');
});

it('respects server timezone when scheduling sentinel checks', function () {
    // Verify server timezone is used for scheduling
    $methodSource = getServerManagerMethodSource($this->sourceFile, 'processServerTasks');

    expect($methodSource)
        ->toContain('$serverTimezone = data_get($server->settings')
        ->toContain('server_timezone')
        ->toContain('validate_timezone($serverTimezone)');
});

it('uses cron expression for hourly sentinel checks', function () {
    // Verify the cron expression for hourly checks
    expect($this->sourceCode)
        ->toContain("'0 * * * *'"); // Hourly at minute 0

    // Verify shouldRunNow method uses CronExpression
    $methodSource = getServerManagerMethodSource($this->sourceFile, 'shouldRunNow');
    expect($methodSource)
        ->toContain('new CronExpression($frequency)')
        ->toContain('$cron->isDue($executionTime)');
});

it('has proper job structure for scheduled tasks', function () {
    // Verify job implements ShouldQueue
    expect(ServerManagerJob::class)->toImplement(Illuminate\Contracts\Queue\ShouldQueue::class);

    // Verify job uses required traits
    $traits = class_uses_recursive(ServerManagerJob::class);
    expect($traits)
        ->toContain('Illuminate\Bus\Queueable')
        ->toContain('Illuminate\Foundation\Bus\Dispatchable')
        ->toContain('Illuminate\Queue\InteractsWithQueue')
        ->toContain('Illuminate\Queue\SerializesModels');
});

it('dispatches ServerConnectionCheckJob based on check frequency', function () {
    // Verify ServerConnectionCheckJob dispatch
    $methodSource = getServerManagerMethodSource($this->sourceFile, 'dispatchConnectionChecks');

    expect($methodSource)
        ->toContain('ServerConnectionCheckJob::dispatch($server)')
        ->toContain('shouldRunNow($this->checkFrequency)');
});

it('dispatches ServerCheckJob when sentinel is out of sync', function () {
    // Verify ServerCheckJob is dispatched when sentinel is out of sync
    $methodSource = getServerManagerMethodSource($this->sourceFile, 'processServerTasks');

    expect($methodSource)
        ->toContain('$sentinelOutOfSync')
        ->toContain('ServerCheckJob::dispatch($server)');
});

it('dispatches ServerStorageCheckJob when sentinel is out of sync', function () {
    // Verify ServerStorageCheckJob is dispatched when sentinel is out of sync
    $methodSource = getServerManagerMethodSource($this->sourceFile, 'processServerTasks');

    expect($methodSource)
        ->toContain('if ($sentinelOutOfSync)')
        ->toContain('ServerStorageCheckJob::dispatch($server)');
});

it('dispatches ServerPatchCheckJob weekly', function () {
    // Verify ServerPatchCheckJob is dispatched weekly
    $methodSource = getServerManagerMethodSource($this->sourceFile, 'processServerTasks');

    expect($methodSource)
        ->toContain("shouldRunNow('0 0 * * 0'") // Weekly on Sunday at midnight
        ->toContain('ServerPatchCheckJob::dispatch($server)');
});

it('restarts sentinel container daily for sentinel-enabled servers', function () {
    // Verify sentinel container restart logic
    $methodSource = getServerManagerMethodSource($this->sourceFile, 'processServerTasks');

    expect($methodSource)
        ->toContain("shouldRunNow('0 0 * * *'") // Daily at midnight
        ->toContain('$shouldRestartSentinel = $isSentinelEnabled')
        ->toContain("restartContainer('saturn-sentinel')");
});

it('uses frozen execution time for consistent scheduling', function () {
    // Verify the job freezes execution time at start
    $handleSource = getServerManagerMethodSource($this->sourceFile, 'handle');
    $shouldRunSource = getServerManagerMethodSource($this->sourceFile, 'shouldRunNow');

    expect($handleSource)->toContain('$this->executionTime = Carbon::now()');
    expect($shouldRunSource)->toContain('$baseTime = $this->executionTime ?? Carbon::now()');
});

it('has different check frequency for cloud instances', function () {
    // Verify cloud instances use different frequency
    $handleSource = getServerManagerMethodSource($this->sourceFile, 'handle');

    expect($handleSource)
        ->toContain('if (isCloud())')
        ->toContain("'*/5 * * * *'"); // Every 5 minutes for cloud
});
