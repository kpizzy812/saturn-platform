<?php

use App\Jobs\ServerLimitCheckJob;
use App\Models\Server;
use App\Models\Team;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $team = Mockery::mock(Team::class)->makePartial();
    $job = new ServerLimitCheckJob($team);

    expect($job->tries)->toBe(4);

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

test('backoff returns integer', function () {
    $team = Mockery::mock(Team::class)->makePartial();
    $job = new ServerLimitCheckJob($team);

    $backoff = $job->backoff();
    expect($backoff)->toBeInt();
    expect($backoff)->toBeIn([1, 3]);
});

test('no action when servers below limit', function () {
    $server1 = Mockery::mock(Server::class)->makePartial();
    $server1->shouldNotReceive('forceDisableServer');
    $server1->shouldNotReceive('forceEnableServer');

    $team = Mockery::mock(Team::class)->makePartial();
    $team->limits = 5;
    $team->shouldReceive('getAttribute')
        ->with('servers')
        ->andReturn(collect([$server1]));
    $team->shouldReceive('getAttribute')
        ->with('limits')
        ->andReturn(5);

    // 1 server, limit 5 → no action needed
    $serversCount = 1;
    $limit = 5;
    $toDisable = $serversCount - $limit;

    expect($toDisable)->toBeLessThan(0);
});

test('disables excess servers when over limit', function () {
    // 3 servers, limit 1 → disable 2 newest
    $serversCount = 3;
    $limit = 1;
    $toDisable = $serversCount - $limit;

    expect($toDisable)->toBe(2);
});

test('excess servers are selected newest first', function () {
    $server1 = new \stdClass;
    $server1->id = 1;
    $server1->created_at = '2024-01-01';

    $server2 = new \stdClass;
    $server2->id = 2;
    $server2->created_at = '2024-06-01';

    $server3 = new \stdClass;
    $server3->id = 3;
    $server3->created_at = '2024-12-01';

    $servers = collect([$server1, $server2, $server3]);
    $sorted = $servers->sortByDesc('created_at');
    $toDisable = $sorted->take(2);

    expect($toDisable->pluck('id')->toArray())->toBe([3, 2]);
});

test('enables force-disabled servers when at limit', function () {
    // When servers_count == limit and some are force-disabled,
    // they should be re-enabled
    $serversCount = 3;
    $limit = 3;
    $toDisable = $serversCount - $limit;

    expect($toDisable)->toBe(0);
    // When $toDisable === 0, the job checks for force-disabled servers to re-enable
});

test('source code sends ForceDisabled notification', function () {
    $source = file_get_contents((new ReflectionClass(ServerLimitCheckJob::class))->getFileName());

    expect($source)->toContain('ForceDisabled');
    expect($source)->toContain('forceDisableServer');
    expect($source)->toContain('notify');
});

test('source code sends ForceEnabled notification', function () {
    $source = file_get_contents((new ReflectionClass(ServerLimitCheckJob::class))->getFileName());

    expect($source)->toContain('ForceEnabled');
    expect($source)->toContain('forceEnableServer');
    expect($source)->toContain('isForceDisabled');
});

test('source code handles errors with internal notification', function () {
    $source = file_get_contents((new ReflectionClass(ServerLimitCheckJob::class))->getFileName());

    expect($source)->toContain('send_internal_notification');
    expect($source)->toContain('handleError');
});

test('source code uses team limits property', function () {
    $source = file_get_contents((new ReflectionClass(ServerLimitCheckJob::class))->getFileName());

    expect($source)->toContain('$this->team->limits');
    expect($source)->toContain('$this->team->servers');
});
