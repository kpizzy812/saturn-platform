<?php

namespace Tests\Unit\Jobs;

use App\Jobs\AlertEvaluationJob;
use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;

afterEach(function () {
    Mockery::close();
});

function buildAlertMock(array $overrides = []): Alert
{
    $defaults = [
        'id' => 1,
        'name' => 'High CPU Alert',
        'metric' => 'cpu',
        'condition' => '>',
        'threshold' => 80.0,
        'duration' => 5,
        'enabled' => true,
    ];

    $data = array_merge($defaults, $overrides);

    $alert = Mockery::mock(Alert::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $alert->id = $data['id'];
    $alert->name = $data['name'];
    $alert->metric = $data['metric'];
    $alert->condition = $data['condition'];
    $alert->threshold = $data['threshold'];
    $alert->duration = $data['duration'];
    $alert->enabled = $data['enabled'];

    return $alert;
}

function buildServerMock(int $id = 1): Server
{
    $server = Mockery::mock(Server::class)->makePartial();
    $server->id = $id;

    return $server;
}

function buildTeamWithServers(array $serverIds = [1]): Team
{
    $servers = new Collection(array_map(fn ($id) => buildServerMock($id), $serverIds));

    $team = Mockery::mock(Team::class)->makePartial();
    $team->shouldReceive('getAttribute')->with('servers')->andReturn($servers);

    return $team;
}

// ---------------------------------------------------------------------------
// checkCondition tests (pure logic, no mocking needed)
// ---------------------------------------------------------------------------

test('checkCondition evaluates greater-than correctly', function () {
    $job = new AlertEvaluationJob;
    $method = new \ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 90.0, '>', 80.0))->toBeTrue();
    expect($method->invoke($job, 70.0, '>', 80.0))->toBeFalse();
    expect($method->invoke($job, 80.0, '>', 80.0))->toBeFalse();
});

test('checkCondition evaluates less-than correctly', function () {
    $job = new AlertEvaluationJob;
    $method = new \ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 30.0, '<', 50.0))->toBeTrue();
    expect($method->invoke($job, 70.0, '<', 50.0))->toBeFalse();
    expect($method->invoke($job, 50.0, '<', 50.0))->toBeFalse();
});

test('checkCondition evaluates equals correctly', function () {
    $job = new AlertEvaluationJob;
    $method = new \ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 50.0, '=', 50.0))->toBeTrue();
    expect($method->invoke($job, 50.005, '=', 50.0))->toBeTrue();
    expect($method->invoke($job, 50.1, '=', 50.0))->toBeFalse();
});

test('checkCondition returns false for unknown operator', function () {
    $job = new AlertEvaluationJob;
    $method = new \ReflectionMethod($job, 'checkCondition');

    expect($method->invoke($job, 50.0, '!=', 50.0))->toBeFalse();
});

// ---------------------------------------------------------------------------
// evaluateAlert tests — mock getAvgMetric instead of overloading ServerHealthCheck
// ---------------------------------------------------------------------------

test('no crash when team has no servers', function () {
    $team = buildTeamWithServers([]);
    $alert = buildAlertMock(['id' => 10]);
    $alert->shouldReceive('getAttribute')->with('team')->andReturn($team);

    $job = new AlertEvaluationJob;
    $method = new \ReflectionMethod($job, 'evaluateAlert');
    $method->invoke($job, $alert);

    expect(true)->toBeTrue();
});

test('no crash when team is null', function () {
    $alert = buildAlertMock(['id' => 11]);
    $alert->shouldReceive('getAttribute')->with('team')->andReturn(null);

    $job = new AlertEvaluationJob;
    $method = new \ReflectionMethod($job, 'evaluateAlert');
    $method->invoke($job, $alert);

    expect(true)->toBeTrue();
});

test('no crash when metric is unknown', function () {
    $team = buildTeamWithServers([1]);
    $alert = buildAlertMock(['id' => 12, 'metric' => 'unknown_metric']);
    $alert->shouldReceive('getAttribute')->with('team')->andReturn($team);

    $job = new AlertEvaluationJob;
    $method = new \ReflectionMethod($job, 'evaluateAlert');
    $method->invoke($job, $alert);

    expect(true)->toBeTrue();
});

test('alert triggers when metric exceeds threshold', function () {
    Cache::shouldReceive('has')->with('alert-triggered-1')->andReturn(false);
    Cache::shouldReceive('put')->with('alert-triggered-1', true, Mockery::any())->once();
    Log::shouldReceive('info')->once();

    $team = buildTeamWithServers([1, 2]);
    $alert = buildAlertMock(['metric' => 'cpu', 'condition' => '>', 'threshold' => 80.0]);
    $alert->shouldReceive('getAttribute')->with('team')->andReturn($team);

    $historyRelation = Mockery::mock(HasMany::class);
    $historyRelation->shouldReceive('create')->once()->with(Mockery::on(function ($data) {
        return $data['status'] === 'triggered' && $data['value'] === 92.5;
    }))->andReturn(new AlertHistory);

    $alert->shouldReceive('histories')->andReturn($historyRelation);
    $alert->shouldReceive('increment')->with('triggered_count')->once();
    $alert->shouldReceive('update')->with(Mockery::on(function ($data) {
        return isset($data['last_triggered_at']);
    }))->once();

    $job = Mockery::mock(AlertEvaluationJob::class)->makePartial();
    $job->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('getAvgMetric')->andReturn(92.5);

    $method = new \ReflectionMethod(AlertEvaluationJob::class, 'evaluateAlert');
    $method->invoke($job, $alert);
});

test('alert does NOT trigger when metric is below threshold', function () {
    Cache::shouldReceive('has')->with('alert-triggered-2')->andReturn(false);
    Cache::shouldReceive('put')->never();

    $team = buildTeamWithServers([1]);
    $alert = buildAlertMock(['id' => 2, 'metric' => 'cpu', 'condition' => '>', 'threshold' => 80.0]);
    $alert->shouldReceive('getAttribute')->with('team')->andReturn($team);

    $job = Mockery::mock(AlertEvaluationJob::class)->makePartial();
    $job->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('getAvgMetric')->andReturn(45.0);

    $method = new \ReflectionMethod(AlertEvaluationJob::class, 'evaluateAlert');
    $method->invoke($job, $alert);
});

test('deduplication prevents double fire within cache window', function () {
    Cache::shouldReceive('has')->with('alert-triggered-3')->andReturn(true);
    Cache::shouldReceive('put')->never();
    Cache::shouldReceive('forget')->never();

    $team = buildTeamWithServers([1]);
    $alert = buildAlertMock(['id' => 3, 'metric' => 'cpu', 'condition' => '>', 'threshold' => 80.0]);
    $alert->shouldReceive('getAttribute')->with('team')->andReturn($team);

    $job = Mockery::mock(AlertEvaluationJob::class)->makePartial();
    $job->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('getAvgMetric')->andReturn(95.0);

    $method = new \ReflectionMethod(AlertEvaluationJob::class, 'evaluateAlert');
    $method->invoke($job, $alert);
});

test('alert resolves when metric drops below threshold', function () {
    Cache::shouldReceive('has')->with('alert-triggered-4')->andReturn(true);
    Cache::shouldReceive('forget')->with('alert-triggered-4')->once();
    Log::shouldReceive('info')->once();

    $team = buildTeamWithServers([1]);
    $alert = buildAlertMock(['id' => 4, 'metric' => 'cpu', 'condition' => '>', 'threshold' => 80.0]);
    $alert->shouldReceive('getAttribute')->with('team')->andReturn($team);

    $lastHistory = Mockery::mock(AlertHistory::class)->makePartial();
    $lastHistory->shouldReceive('update')->with(Mockery::on(function ($data) {
        return $data['status'] === 'resolved' && isset($data['resolved_at']);
    }))->once();

    $historyRelation = Mockery::mock(HasMany::class);
    $historyRelation->shouldReceive('where')->with('status', 'triggered')->andReturnSelf();
    $historyRelation->shouldReceive('whereNull')->with('resolved_at')->andReturnSelf();
    $historyRelation->shouldReceive('latest')->with('triggered_at')->andReturnSelf();
    $historyRelation->shouldReceive('first')->andReturn($lastHistory);

    $alert->shouldReceive('histories')->andReturn($historyRelation);

    $job = Mockery::mock(AlertEvaluationJob::class)->makePartial();
    $job->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('getAvgMetric')->andReturn(50.0);

    $method = new \ReflectionMethod(AlertEvaluationJob::class, 'evaluateAlert');
    $method->invoke($job, $alert);
});

test('job has correct configuration', function () {
    $job = new AlertEvaluationJob;

    expect($job->tries)->toBe(1);
    expect($job->timeout)->toBe(60);
    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

// ---------------------------------------------------------------------------
// failed() callback
// ---------------------------------------------------------------------------

test('job has a public failed() method', function () {
    $reflection = new \ReflectionClass(AlertEvaluationJob::class);

    expect($reflection->hasMethod('failed'))->toBeTrue();
    expect($reflection->getMethod('failed')->isPublic())->toBeTrue();
});

test('failed() logs error with exception message', function () {
    Log::shouldReceive('error')->once()->with('AlertEvaluationJob permanently failed', Mockery::on(function ($context) {
        return str_contains($context['error'], 'DB connection lost');
    }));

    $job = new AlertEvaluationJob;
    $job->failed(new \RuntimeException('DB connection lost'));
});
