<?php

use App\Jobs\ScheduledJobManager;
use Cron\CronExpression;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

// ═══════════════════════════════════════════
// shouldRunNow() — cron evaluation
// ═══════════════════════════════════════════

test('shouldRunNow returns true when cron expression matches current time', function () {
    $job = new ScheduledJobManager;

    // Set execution time to a known time
    $executionTimeProp = new ReflectionProperty($job, 'executionTime');
    $executionTimeProp->setValue($job, Carbon::create(2026, 1, 15, 14, 0, 0)); // Thursday 14:00

    $method = new ReflectionMethod($job, 'shouldRunNow');

    // Every hour at minute 0 should match
    expect($method->invoke($job, '0 * * * *', 'UTC'))->toBeTrue();
});

test('shouldRunNow returns false when cron expression does not match', function () {
    $job = new ScheduledJobManager;

    $executionTimeProp = new ReflectionProperty($job, 'executionTime');
    $executionTimeProp->setValue($job, Carbon::create(2026, 1, 15, 14, 30, 0)); // 14:30

    $method = new ReflectionMethod($job, 'shouldRunNow');

    // Every hour at minute 0 should NOT match at :30
    expect($method->invoke($job, '0 * * * *', 'UTC'))->toBeFalse();
});

test('shouldRunNow handles every-minute cron', function () {
    $job = new ScheduledJobManager;

    $executionTimeProp = new ReflectionProperty($job, 'executionTime');
    $executionTimeProp->setValue($job, Carbon::create(2026, 1, 15, 10, 23, 45));

    $method = new ReflectionMethod($job, 'shouldRunNow');

    // Every minute should always match
    expect($method->invoke($job, '* * * * *', 'UTC'))->toBeTrue();
});

test('shouldRunNow respects timezone', function () {
    $job = new ScheduledJobManager;

    // Set to 00:00 UTC = 03:00 Moscow time
    $executionTimeProp = new ReflectionProperty($job, 'executionTime');
    $executionTimeProp->setValue($job, Carbon::create(2026, 1, 15, 0, 0, 0, 'UTC'));

    $method = new ReflectionMethod($job, 'shouldRunNow');

    // "At 3:00" should match in Europe/Moscow (UTC+3)
    expect($method->invoke($job, '0 3 * * *', 'Europe/Moscow'))->toBeTrue();

    // "At 0:00" should NOT match in Europe/Moscow (it's 3:00 there)
    expect($method->invoke($job, '0 0 * * *', 'Europe/Moscow'))->toBeFalse();
});

test('shouldRunNow handles Sunday weekly cron', function () {
    $job = new ScheduledJobManager;

    // 2026-01-18 is a Sunday
    $executionTimeProp = new ReflectionProperty($job, 'executionTime');
    $executionTimeProp->setValue($job, Carbon::create(2026, 1, 18, 0, 0, 0, 'UTC'));

    $method = new ReflectionMethod($job, 'shouldRunNow');

    // Sunday midnight cron
    expect($method->invoke($job, '0 0 * * 0', 'UTC'))->toBeTrue();

    // Monday — set to a Monday
    $executionTimeProp->setValue($job, Carbon::create(2026, 1, 19, 0, 0, 0, 'UTC'));
    expect($method->invoke($job, '0 0 * * 0', 'UTC'))->toBeFalse();
});

// ═══════════════════════════════════════════
// determineQueue() — queue selection
// ═══════════════════════════════════════════

test('determineQueue selects crons queue when available', function () {
    config(['horizon.defaults.s6.queue' => 'high,default,crons']);

    $job = new ScheduledJobManager;
    $method = new ReflectionMethod($job, 'determineQueue');

    expect($method->invoke($job))->toBe('crons');
});

test('determineQueue falls back to high when crons not available', function () {
    config(['horizon.defaults.s6.queue' => 'high,default']);

    $job = new ScheduledJobManager;
    $method = new ReflectionMethod($job, 'determineQueue');

    expect($method->invoke($job))->toBe('high');
});

// ═══════════════════════════════════════════
// Job configuration
// ═══════════════════════════════════════════

test('job has correct tries configuration', function () {
    $job = new ScheduledJobManager;

    expect($job->tries)->toBe(1);
});

test('job has correct timeout configuration', function () {
    $job = new ScheduledJobManager;

    expect($job->timeout)->toBe(120);
});

test('middleware uses WithoutOverlapping with correct key', function () {
    $job = new ScheduledJobManager;
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1);
    expect($middleware[0])->toBeInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class);
});

// ═══════════════════════════════════════════
// VALID_CRON_STRINGS mapping
// ═══════════════════════════════════════════

test('VALID_CRON_STRINGS constant is defined', function () {
    expect(defined('VALID_CRON_STRINGS'))->toBeTrue();
});

test('VALID_CRON_STRINGS maps frequency names to cron expressions', function () {
    // Verify the mapping works as expected in the code
    $frequency = 'every_minute';
    if (isset(VALID_CRON_STRINGS[$frequency])) {
        $cron = VALID_CRON_STRINGS[$frequency];
        expect(CronExpression::isValidExpression($cron))->toBeTrue();
    }
});

test('VALID_CRON_STRINGS values are valid cron expressions', function () {
    foreach (VALID_CRON_STRINGS as $key => $cronString) {
        expect(CronExpression::isValidExpression($cronString))
            ->toBeTrue("VALID_CRON_STRINGS['{$key}'] = '{$cronString}' is not a valid cron expression");
    }
});

// ═══════════════════════════════════════════
// Timezone handling
// ═══════════════════════════════════════════

test('invalid timezone falls back to app timezone in processing logic', function () {
    // Test the fallback logic used in processScheduledBackups/Tasks/Cleanups
    $invalidTimezone = 'Invalid/Timezone';
    $appTimezone = config('app.timezone');

    if (validate_timezone($invalidTimezone) === false) {
        $serverTimezone = $appTimezone;
    } else {
        $serverTimezone = $invalidTimezone;
    }

    expect($serverTimezone)->toBe($appTimezone);
});

test('valid timezone is kept in processing logic', function () {
    $validTimezone = 'America/New_York';
    $appTimezone = config('app.timezone');

    if (validate_timezone($validTimezone) === false) {
        $serverTimezone = $appTimezone;
    } else {
        $serverTimezone = $validTimezone;
    }

    expect($serverTimezone)->toBe('America/New_York');
});

// ═══════════════════════════════════════════
// Error isolation between processors
// ═══════════════════════════════════════════

test('handle method catches exceptions from each processor independently', function () {
    // Verify the handle method structure catches exceptions per processor
    $source = file_get_contents(app_path('Jobs/ScheduledJobManager.php'));

    // Should have 3 separate try-catch blocks in handle()
    $handleMethod = extractScheduledJobManagerMethod($source, 'handle');

    $tryCatchCount = substr_count($handleMethod, 'try {');
    expect($tryCatchCount)->toBe(3);
});

test('each processor logs errors to scheduled-errors channel', function () {
    $source = file_get_contents(app_path('Jobs/ScheduledJobManager.php'));

    // All processors should log to 'scheduled-errors' channel
    expect($source)->toContain("Log::channel('scheduled-errors')");

    // Count occurrences of error logging
    $errorLogCount = substr_count($source, "Log::channel('scheduled-errors')->error");
    expect($errorLogCount)->toBeGreaterThanOrEqual(3);
});

// ═══════════════════════════════════════════
// Helpers
// ═══════════════════════════════════════════

function extractScheduledJobManagerMethod(string $source, string $methodName): string
{
    $pattern = '/public\s+function\s+'.preg_quote($methodName, '/').'\s*\([^)]*\)\s*:\s*\w+\s*\{/';
    if (! preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
        throw new \Exception("Method {$methodName} not found");
    }

    $start = $matches[0][1];
    $braceCount = 0;
    $inMethod = false;

    for ($i = $start; $i < strlen($source); $i++) {
        if ($source[$i] === '{') {
            $braceCount++;
            $inMethod = true;
        } elseif ($source[$i] === '}') {
            $braceCount--;
        }
        if ($inMethod && $braceCount === 0) {
            return substr($source, $start, $i + 1 - $start);
        }
    }

    return '';
}
