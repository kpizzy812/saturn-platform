<?php

use App\Actions\Server\ValidateServer;
use App\Models\Server;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

// ═══════════════════════════════════════════
// Validation chain — connection check
// ═══════════════════════════════════════════

test('throws exception when server is not reachable', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('update')->twice();
    $server->shouldReceive('validateConnection')->once()->andReturn([
        'uptime' => null,
        'error' => 'Connection refused',
    ]);

    $action = new ValidateServer;

    expect(fn () => $action->handle($server))->toThrow(Exception::class);
    expect($action->uptime)->toBeNull();
    expect($action->error)->toContain('Server is not reachable');
    expect($action->error)->toContain('Connection refused');
});

test('throws exception when OS is not supported', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('update')->times(2);
    $server->shouldReceive('validateConnection')->once()->andReturn([
        'uptime' => '5 days',
        'error' => null,
    ]);
    $server->shouldReceive('validateOS')->once()->andReturn(false);

    $action = new ValidateServer;

    expect(fn () => $action->handle($server))->toThrow(Exception::class);
    expect($action->uptime)->toBe('5 days');
    expect($action->error)->toContain('Server OS type is not supported');
});

test('throws exception when prerequisites are missing', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('update')->times(2);
    $server->shouldReceive('validateConnection')->once()->andReturn([
        'uptime' => '5 days',
        'error' => null,
    ]);
    $server->shouldReceive('validateOS')->once()->andReturn('ubuntu');
    $server->shouldReceive('validatePrerequisites')->once()->andReturn([
        'success' => false,
        'missing' => ['curl', 'git'],
    ]);

    $action = new ValidateServer;

    expect(fn () => $action->handle($server))->toThrow(Exception::class);
    expect($action->error)->toContain('curl, git');
    expect($action->error)->toContain('Prerequisites');
});

// ═══════════════════════════════════════════
// Validation chain — Docker checks
// ═══════════════════════════════════════════

test('throws exception when Docker Engine is not installed', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('update')->times(2);
    $server->shouldReceive('validateConnection')->once()->andReturn([
        'uptime' => '5 days',
        'error' => null,
    ]);
    $server->shouldReceive('validateOS')->once()->andReturn('ubuntu');
    $server->shouldReceive('validatePrerequisites')->once()->andReturn([
        'success' => true,
        'missing' => [],
    ]);
    $server->shouldReceive('validateDockerEngine')->once()->andReturn(false);
    $server->shouldReceive('validateDockerCompose')->once()->andReturn(true);

    $action = new ValidateServer;

    expect(fn () => $action->handle($server))->toThrow(Exception::class);
    expect($action->error)->toContain('Docker Engine is not installed');
});

test('throws exception when Docker Compose is not installed', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('update')->times(2);
    $server->shouldReceive('validateConnection')->once()->andReturn([
        'uptime' => '5 days',
        'error' => null,
    ]);
    $server->shouldReceive('validateOS')->once()->andReturn('ubuntu');
    $server->shouldReceive('validatePrerequisites')->once()->andReturn([
        'success' => true,
        'missing' => [],
    ]);
    $server->shouldReceive('validateDockerEngine')->once()->andReturn(true);
    $server->shouldReceive('validateDockerCompose')->once()->andReturn(false);

    $action = new ValidateServer;

    expect(fn () => $action->handle($server))->toThrow(Exception::class);
    expect($action->error)->toContain('Docker Engine is not installed');
});

test('throws exception when Docker version check fails', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('update')->times(2);
    $server->shouldReceive('validateConnection')->once()->andReturn([
        'uptime' => '5 days',
        'error' => null,
    ]);
    $server->shouldReceive('validateOS')->once()->andReturn('ubuntu');
    $server->shouldReceive('validatePrerequisites')->once()->andReturn([
        'success' => true,
        'missing' => [],
    ]);
    $server->shouldReceive('validateDockerEngine')->once()->andReturn(true);
    $server->shouldReceive('validateDockerCompose')->once()->andReturn(true);
    $server->shouldReceive('validateDockerEngineVersion')->once()->andReturn(false);

    $action = new ValidateServer;

    expect(fn () => $action->handle($server))->toThrow(Exception::class);
});

// ═══════════════════════════════════════════
// Success case
// ═══════════════════════════════════════════

test('returns OK when all validations pass', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('update')->once();
    $server->shouldReceive('validateConnection')->once()->andReturn([
        'uptime' => '10 days, 5:30',
        'error' => null,
    ]);
    $server->shouldReceive('validateOS')->once()->andReturn('ubuntu');
    $server->shouldReceive('validatePrerequisites')->once()->andReturn([
        'success' => true,
        'missing' => [],
    ]);
    $server->shouldReceive('validateDockerEngine')->once()->andReturn(true);
    $server->shouldReceive('validateDockerCompose')->once()->andReturn(true);
    $server->shouldReceive('validateDockerEngineVersion')->once()->andReturn('24.0.7');

    $action = new ValidateServer;
    $result = $action->handle($server);

    expect($result)->toBe('OK');
    expect($action->uptime)->toBe('10 days, 5:30');
    expect($action->error)->toBeNull();
    expect($action->supported_os_type)->toBeTruthy();
    expect($action->docker_installed)->toBeTruthy();
    expect($action->docker_compose_installed)->toBeTruthy();
    expect($action->docker_version)->toBe('24.0.7');
});

// ═══════════════════════════════════════════
// Properties and configuration
// ═══════════════════════════════════════════

test('action initializes with null properties', function () {
    $action = new ValidateServer;

    expect($action->uptime)->toBeNull();
    expect($action->error)->toBeNull();
    expect($action->supported_os_type)->toBeNull();
    expect($action->docker_installed)->toBeNull();
    expect($action->docker_compose_installed)->toBeNull();
    expect($action->docker_version)->toBeNull();
});

test('action has high priority job queue', function () {
    $action = new ValidateServer;

    expect($action->jobQueue)->toBe('high');
});

// ═══════════════════════════════════════════
// Error message content
// ═══════════════════════════════════════════

test('connection error message contains documentation link', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('update')->twice();
    $server->shouldReceive('validateConnection')->once()->andReturn([
        'uptime' => null,
        'error' => 'timeout',
    ]);

    $action = new ValidateServer;

    try {
        $action->handle($server);
    } catch (\Exception $e) {
        // Expected
    }

    expect($action->error)->toContain('knowledge-base/server/openssh');
    expect($action->error)->toContain('timeout');
});

test('missing prerequisites error lists all missing commands', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('update')->times(2);
    $server->shouldReceive('validateConnection')->once()->andReturn([
        'uptime' => '5 days',
        'error' => null,
    ]);
    $server->shouldReceive('validateOS')->once()->andReturn('ubuntu');
    $server->shouldReceive('validatePrerequisites')->once()->andReturn([
        'success' => false,
        'missing' => ['curl', 'wget', 'jq'],
    ]);

    $action = new ValidateServer;

    try {
        $action->handle($server);
    } catch (\Exception $e) {
        // Expected
    }

    expect($action->error)->toContain('curl, wget, jq');
});

// ═══════════════════════════════════════════
// Validation logs are stored
// ═══════════════════════════════════════════

test('validation logs are reset at start', function () {
    $server = Mockery::mock(Server::class)->makePartial();
    $server->shouldReceive('update')
        ->once()
        ->with(['validation_logs' => null]);
    $server->shouldReceive('update')
        ->once()
        ->with(Mockery::on(fn ($arg) => isset($arg['validation_logs']) && $arg['validation_logs'] !== null));
    $server->shouldReceive('validateConnection')->once()->andReturn([
        'uptime' => null,
        'error' => 'refused',
    ]);

    $action = new ValidateServer;

    try {
        $action->handle($server);
    } catch (\Exception $e) {
        // Expected
    }
});
