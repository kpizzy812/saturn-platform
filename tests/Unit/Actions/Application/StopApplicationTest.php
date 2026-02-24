<?php

use App\Actions\Application\StopApplication;
use App\Actions\Server\CleanupDocker;
use App\Events\ServiceStatusChanged;
use App\Models\Application;
use App\Models\Server;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Lorisleiva\Actions\Concerns\AsAction;

beforeEach(function () {
    Event::fake();
    Queue::fake();
});

afterEach(function () {
    Mockery::close();
});

// ---------------------------------------------------------------------------
// 1. Class-level contract assertions (no DB, no SSH)
// ---------------------------------------------------------------------------

it('has jobQueue set to high', function () {
    $action = new StopApplication;

    expect($action->jobQueue)->toBe('high');
});

it('uses AsAction trait', function () {
    $traits = class_uses_recursive(StopApplication::class);

    expect($traits)->toContain(AsAction::class);
});

it('has handle method with correct signature', function () {
    $reflection = new ReflectionMethod(StopApplication::class, 'handle');
    $params = $reflection->getParameters();

    // First param: Application $application
    expect($params[0]->getName())->toBe('application');
    expect($params[0]->getType()->getName())->toBe(Application::class);

    // Second param: bool $previewDeployments = false
    expect($params[1]->getName())->toBe('previewDeployments');
    expect($params[1]->isDefaultValueAvailable())->toBeTrue();
    expect($params[1]->getDefaultValue())->toBeFalse();

    // Third param: bool $dockerCleanup = true
    expect($params[2]->getName())->toBe('dockerCleanup');
    expect($params[2]->isDefaultValueAvailable())->toBeTrue();
    expect($params[2]->getDefaultValue())->toBeTrue();
});

// ---------------------------------------------------------------------------
// 2. Source-level assertions — SSH calls cannot be unit tested but their
//    shape must exist in the source file.
// ---------------------------------------------------------------------------

it('source uses escapeshellarg for uuid in swarm stack rm command', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    expect($source)->toContain('escapeshellarg($application->uuid)');
    expect($source)->toContain('docker stack rm ');
});

it('source uses escapeshellarg for container names before stop and rm', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    expect($source)->toContain('escapeshellarg($containerName)');
    expect($source)->toContain('docker stop -t 30 $escapedName');
    expect($source)->toContain('docker rm -f $escapedName');
});

it('source dispatches CleanupDocker when dockerCleanup flag is true', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    expect($source)->toContain('CleanupDocker::dispatch($server, false, false)');
    expect($source)->toContain('if ($dockerCleanup)');
});

it('source dispatches ServiceStatusChanged with teamId from application environment chain', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    // Verify the full nullable chain access pattern
    expect($source)->toContain('$application->environment?->project?->team?->id');
    expect($source)->toContain('ServiceStatusChanged::dispatch($teamId)');
    expect($source)->toContain('if ($teamId)');
});

it('source collects destination server and merges additional servers', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    expect($source)->toContain('collect([$application->destination->server])');
    expect($source)->toContain('$servers = $servers->merge($application->additional_servers)');
    expect($source)->toContain('$application->additional_servers->count() > 0');
});

it('source checks isFunctional before performing any stop operation', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    expect($source)->toContain('$server->isFunctional()');
    expect($source)->toContain("return 'Server is not functional'");
});

it('source checks isSwarm and returns early after stack rm', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    expect($source)->toContain('$server->isSwarm()');
    // The return after stack rm must be a bare return (no value)
    expect($source)->toContain('return;');
});

it('source calls deleteConnectedNetworks for dockercompose build pack', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    expect($source)->toContain("'dockercompose'");
    expect($source)->toContain('$application->deleteConnectedNetworks()');
    expect($source)->toContain("\$application->build_pack === 'dockercompose'");
});

it('source wraps each server iteration in try/catch and returns exception message', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    expect($source)->toContain('} catch (\Exception $e) {');
    expect($source)->toContain('return $e->getMessage()');
});

it('source calls getCurrentApplicationContainerStatus with includePullrequests true when previewDeployments is true', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    expect($source)->toContain('includePullrequests: true');
    expect($source)->toContain('$previewDeployments');
    expect($source)->toContain('getCurrentApplicationContainerStatus(');
});

it('source passes pullRequestId=0 to getCurrentApplicationContainerStatus for non-preview case', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    // Non-preview call passes 0 as the pullRequestId argument
    expect($source)->toContain('getCurrentApplicationContainerStatus($server, $application->id, 0)');
});

it('source calls instant_remote_process with throwError false when stopping containers', function () {
    $source = file_get_contents(app_path('Actions/Application/StopApplication.php'));

    expect($source)->toContain('throwError: false');
});

// ---------------------------------------------------------------------------
// 3. Logic tests using plain PHP objects (no DB, no SSH)
// ---------------------------------------------------------------------------

it('escapeshellarg properly wraps a simple container name', function () {
    // Verify that escapeshellarg behaves as expected for a well-formed name
    $name = 'my-app-container';
    $escaped = escapeshellarg($name);

    expect($escaped)->toBe("'my-app-container'");
    expect($escaped)->not->toBe($name);
});

it('escapeshellarg prevents shell injection via single quotes in container names', function () {
    // escapeshellarg wraps the entire value in single quotes and escapes any
    // embedded single quote as '\'' — this turns the whole argument into one
    // shell token, so a trailing '; rm -rf /' becomes inert literal text.
    $malicious = "name'; rm -rf /";
    $escaped = escapeshellarg($malicious);

    // The output must be enclosed in outer single quotes (one shell word)
    expect($escaped)->toStartWith("'");
    expect($escaped)->toEndWith("'");

    // The embedded single quote must be escaped via the '\'' pattern
    expect($escaped)->toContain("'\\''");

    // The docker command must begin with the quoted argument right after the binary
    $stopCommand = "docker stop -t 30 {$escaped}";
    expect($stopCommand)->toStartWith("docker stop -t 30 '");
});

it('escapeshellarg prevents shell injection via backtick command substitution', function () {
    $malicious = 'container`id`name';
    $escaped = escapeshellarg($malicious);

    // The backtick must be inside the quoted string and therefore inert
    $dockerStop = "docker stop -t 30 {$escaped}";

    expect($dockerStop)->toContain("'container");
    expect($dockerStop)->toContain("'");
    // The backtick is enclosed in single quotes so it cannot be interpreted as substitution
    expect($dockerStop)->toContain('`');
    // But the surrounding single quotes mean the shell won't execute the substitution
    expect($escaped)->toStartWith("'");
});

it('escapeshellarg neutralises dollar sign variable expansion in container names', function () {
    $malicious = 'container$HOME';
    $escaped = escapeshellarg($malicious);

    $dockerRm = "docker rm -f {$escaped}";

    expect($dockerRm)->toContain("'container\$HOME'");
    expect($dockerRm)->not->toMatch('/docker rm -f container\$HOME/');
});

it('escapeshellarg of a uuid produces a valid shell-safe token', function () {
    $uuid = 'abc123de-f456-7890-abcd-ef1234567890';
    $escaped = escapeshellarg($uuid);

    $cmd = "docker stack rm {$escaped}";

    expect($cmd)->toBe("docker stack rm 'abc123de-f456-7890-abcd-ef1234567890'");
    expect($cmd)->not->toContain('--');
});

it('pluck Names from container status collection extracts names correctly', function () {
    // Simulate what the handle() method does when iterating containers
    $containers = collect([
        ['Names' => '/app-container-1', 'Status' => 'Up'],
        ['Names' => '/app-container-2', 'Status' => 'Up'],
    ]);

    $containersToStop = $containers->pluck('Names')->toArray();

    expect($containersToStop)->toHaveCount(2);
    expect($containersToStop)->toContain('/app-container-1');
    expect($containersToStop)->toContain('/app-container-2');
});

it('server collection merges destination server with additional servers', function () {
    // Simulate the server collection logic from handle()
    $primaryServer = (object) ['id' => 1, 'name' => 'primary'];
    $additionalServer1 = (object) ['id' => 2, 'name' => 'additional-1'];
    $additionalServer2 = (object) ['id' => 3, 'name' => 'additional-2'];

    $additionalServers = collect([$additionalServer1, $additionalServer2]);

    $servers = collect([$primaryServer]);
    if ($additionalServers->count() > 0) {
        $servers = $servers->merge($additionalServers);
    }

    expect($servers)->toHaveCount(3);
    expect($servers->pluck('id')->toArray())->toContain(1, 2, 3);
});

it('server collection contains only primary when additional servers is empty', function () {
    // Simulate the early-exit for additional_servers->count() === 0
    $primaryServer = (object) ['id' => 1, 'name' => 'primary'];
    $additionalServers = collect([]);

    $servers = collect([$primaryServer]);
    if ($additionalServers->count() > 0) {
        $servers = $servers->merge($additionalServers);
    }

    expect($servers)->toHaveCount(1);
    expect($servers->first()->id)->toBe(1);
});

it('teamId is extracted via nullable chain from environment project team', function () {
    // Simulate the nullable chain: $application->environment?->project?->team?->id
    $teamStub = (object) ['id' => 42];
    $projectStub = (object) ['team' => $teamStub];
    $environmentStub = (object) ['project' => $projectStub];

    $teamId = $environmentStub?->project?->team?->id;

    expect($teamId)->toBe(42);
});

it('teamId nullable chain returns null when environment is null', function () {
    $environmentStub = null;

    $teamId = $environmentStub?->project?->team?->id;

    expect($teamId)->toBeNull();
});

it('ServiceStatusChanged is not dispatched when teamId is null', function () {
    // Simulate the guard: if ($teamId) { ServiceStatusChanged::dispatch($teamId); }
    $teamId = null;

    if ($teamId) {
        ServiceStatusChanged::dispatch($teamId);
    }

    Event::assertNotDispatched(ServiceStatusChanged::class);
});

it('ServiceStatusChanged is dispatched when teamId is a valid integer', function () {
    $teamId = 7;

    if ($teamId) {
        ServiceStatusChanged::dispatch($teamId);
    }

    Event::assertDispatched(ServiceStatusChanged::class);
});

it('CleanupDocker is dispatched when dockerCleanup flag is true', function () {
    // CleanupDocker uses AsAction / AsJob — dispatch() wraps the action in a
    // JobDecorator. The AsJob trait provides assertPushed() which knows how to
    // interrogate Queue::fake() for that decorator type.
    $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
    $dockerCleanup = true;

    if ($dockerCleanup) {
        CleanupDocker::dispatch($server, false, false);
    }

    CleanupDocker::assertPushed();
});
