<?php

use App\Enums\ApplicationDeploymentStatus;
use App\Jobs\ApplicationDeploymentJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

afterEach(function () {
    Mockery::close();
});

// -------------------------------------------------------------------------
// Job configuration
// -------------------------------------------------------------------------

test('job implements ShouldQueue', function () {
    $interfaces = class_implements(ApplicationDeploymentJob::class);

    expect($interfaces)->toContain(ShouldQueue::class);
});

test('job implements ShouldBeEncrypted', function () {
    $interfaces = class_implements(ApplicationDeploymentJob::class);

    expect($interfaces)->toContain(ShouldBeEncrypted::class);
});

test('job has tries set to 3', function () {
    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);
    $defaults = $reflection->getDefaultProperties();

    expect($defaults['tries'])->toBe(3);
});

test('job has timeout set to 3600', function () {
    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);
    $defaults = $reflection->getDefaultProperties();

    expect($defaults['timeout'])->toBe(3600);
});

test('job dispatches onto the high queue', function () {
    $source = file_get_contents(app_path('Jobs/ApplicationDeploymentJob.php'));

    expect($source)->toContain("onQueue('high')");
});

// -------------------------------------------------------------------------
// backoff()
// -------------------------------------------------------------------------

test('backoff returns [30, 60, 120]', function () {
    $job = Mockery::mock(ApplicationDeploymentJob::class)->makePartial();
    $job->shouldAllowMockingProtectedMethods();

    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);
    $prop = $reflection->getProperty('application_deployment_queue_id');
    $prop->setAccessible(true);
    $prop->setValue($job, 0);

    expect($job->backoff())->toBe([30, 60, 120]);
});

// -------------------------------------------------------------------------
// tags()
// -------------------------------------------------------------------------

test('tags returns queue tag containing the deployment queue id', function () {
    $job = Mockery::mock(ApplicationDeploymentJob::class)->makePartial();
    $job->shouldAllowMockingProtectedMethods();

    $queueId = 42;

    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);
    $prop = $reflection->getProperty('application_deployment_queue_id');
    $prop->setAccessible(true);
    $prop->setValue($job, $queueId);

    $queueProp = $reflection->getProperty('application_deployment_queue');
    $queueProp->setAccessible(true);
    $queueProp->setValue($job, null);

    $tags = $job->tags();

    expect($tags)->toBeArray();
    expect($tags)->toHaveCount(1);
    expect($tags[0])->toBe('App\Models\ApplicationDeploymentQueue:42');
});

test('tags format matches expected pattern', function () {
    $job = Mockery::mock(ApplicationDeploymentJob::class)->makePartial();
    $job->shouldAllowMockingProtectedMethods();

    $queueId = 99;

    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);
    $prop = $reflection->getProperty('application_deployment_queue_id');
    $prop->setAccessible(true);
    $prop->setValue($job, $queueId);

    $queueProp = $reflection->getProperty('application_deployment_queue');
    $queueProp->setAccessible(true);
    $queueProp->setValue($job, null);

    $tags = $job->tags();

    expect($tags[0])->toMatch('/^App\\\\Models\\\\ApplicationDeploymentQueue:\d+$/');
});

// -------------------------------------------------------------------------
// normalizeDockerfileLocation()
// -------------------------------------------------------------------------

/**
 * Build a partial job mock with an Application mock whose base_directory
 * is properly wired through Eloquent's getAttribute (used by magic __get).
 *
 * Returns [job, ReflectionMethod].
 */
function makeJobForNormalize(string $baseDirectory): array
{
    $job = Mockery::mock(ApplicationDeploymentJob::class)->makePartial();
    $job->shouldAllowMockingProtectedMethods();

    // ApplicationDeploymentQueue mock: addLogEntry is called inside the method
    $mockQueue = Mockery::mock(ApplicationDeploymentQueue::class)->shouldIgnoreMissing();

    // Application mock: must respond to getAttribute('base_directory') because
    // Eloquent routes __get through getAttribute, not a raw PHP property read.
    $mockApp = Mockery::mock(Application::class)->shouldIgnoreMissing();
    $mockApp->shouldReceive('getAttribute')
        ->with('base_directory')
        ->andReturn($baseDirectory);

    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);

    $appProp = $reflection->getProperty('application');
    $appProp->setAccessible(true);
    $appProp->setValue($job, $mockApp);

    $queueProp = $reflection->getProperty('application_deployment_queue');
    $queueProp->setAccessible(true);
    $queueProp->setValue($job, $mockQueue);

    $method = $reflection->getMethod('normalizeDockerfileLocation');
    $method->setAccessible(true);

    return [$job, $method];
}

test('normalizeDockerfileLocation strips base_directory prefix when present', function () {
    [$job, $method] = makeJobForNormalize('/backend');

    $result = $method->invoke($job, '/backend/Dockerfile');

    expect($result)->toBe('/Dockerfile');
});

test('normalizeDockerfileLocation returns path unchanged when prefix absent', function () {
    [$job, $method] = makeJobForNormalize('/backend');

    $result = $method->invoke($job, '/Dockerfile');

    expect($result)->toBe('/Dockerfile');
});

test('normalizeDockerfileLocation returns path unchanged when base_directory is root slash', function () {
    [$job, $method] = makeJobForNormalize('/');

    $result = $method->invoke($job, '/Dockerfile');

    expect($result)->toBe('/Dockerfile');
});

test('normalizeDockerfileLocation returns path unchanged when base_directory is empty string', function () {
    [$job, $method] = makeJobForNormalize('');

    $result = $method->invoke($job, '/Dockerfile');

    expect($result)->toBe('/Dockerfile');
});

test('normalizeDockerfileLocation handles nested subdirectory path correctly', function () {
    // base_directory='/apps/api', dockerfile='/apps/api/docker/Dockerfile'
    // str_starts_with('/apps/api/docker/Dockerfile', '/apps/api/') → true
    // str_replace('/apps/api', '', '/apps/api/docker/Dockerfile') → '/docker/Dockerfile'
    [$job, $method] = makeJobForNormalize('/apps/api');

    $result = $method->invoke($job, '/apps/api/docker/Dockerfile');

    expect($result)->toBe('/docker/Dockerfile');
});

test('normalizeDockerfileLocation does not strip partial prefix match', function () {
    // baseDir = '/back', check: str_starts_with('/backend/Dockerfile', '/back/') = false
    [$job, $method] = makeJobForNormalize('/back');

    $result = $method->invoke($job, '/backend/Dockerfile');

    expect($result)->toBe('/backend/Dockerfile');
});

// -------------------------------------------------------------------------
// findFromInstructionLines()
// -------------------------------------------------------------------------

/**
 * Return [job, ReflectionMethod] for findFromInstructionLines.
 */
function makeJobForFromLines(): array
{
    $job = Mockery::mock(ApplicationDeploymentJob::class)->makePartial();
    $job->shouldAllowMockingProtectedMethods();

    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);
    $method = $reflection->getMethod('findFromInstructionLines');
    $method->setAccessible(true);

    return [$job, $method];
}

test('findFromInstructionLines finds single FROM instruction', function () {
    [$job, $method] = makeJobForFromLines();

    $dockerfile = collect([
        'FROM ubuntu:22.04',
        'RUN apt-get update',
        'COPY . /app',
    ]);

    $result = $method->invoke($job, $dockerfile);

    expect($result)->toBe([0]);
});

test('findFromInstructionLines finds multiple FROM instructions in multi-stage build', function () {
    [$job, $method] = makeJobForFromLines();

    $dockerfile = collect([
        'FROM node:18 AS builder',   // index 0
        'WORKDIR /app',
        'RUN npm install',
        'FROM nginx:alpine',         // index 3
        'COPY --from=builder /app/dist /usr/share/nginx/html',
    ]);

    $result = $method->invoke($job, $dockerfile);

    expect($result)->toBe([0, 3]);
});

test('findFromInstructionLines is case-insensitive for FROM keyword', function () {
    [$job, $method] = makeJobForFromLines();

    $dockerfile = collect([
        'from ubuntu:22.04',
        'FROM alpine:3.18',
        'From debian:12',
    ]);

    $result = $method->invoke($job, $dockerfile);

    expect($result)->toBe([0, 1, 2]);
});

test('findFromInstructionLines returns empty array for Dockerfile without FROM', function () {
    [$job, $method] = makeJobForFromLines();

    $dockerfile = collect([
        'RUN echo hello',
        'COPY . /app',
    ]);

    $result = $method->invoke($job, $dockerfile);

    expect($result)->toBe([]);
});

test('findFromInstructionLines ignores lines that contain FROM but do not start with it', function () {
    [$job, $method] = makeJobForFromLines();

    $dockerfile = collect([
        'FROM ubuntu:22.04',           // index 0 — valid
        '# COPY --from=builder /dist', // comment with from — not a FROM instruction
        'COPY --from=builder . /app',  // inline --from — not a FROM instruction
        'RUN echo "FROM here"',        // string containing FROM — not a FROM instruction
    ]);

    $result = $method->invoke($job, $dockerfile);

    expect($result)->toBe([0]);
});

test('findFromInstructionLines handles leading whitespace on FROM lines', function () {
    [$job, $method] = makeJobForFromLines();

    $dockerfile = collect([
        '  FROM ubuntu:22.04',  // leading spaces — regex matches after trim
        'RUN apt-get update',
    ]);

    $result = $method->invoke($job, $dockerfile);

    expect($result)->toBe([0]);
});

// -------------------------------------------------------------------------
// generate_healthcheck_commands()
// -------------------------------------------------------------------------

/**
 * Build a partial job mock whose Application and settings mocks are fully
 * wired for generate_healthcheck_commands.
 *
 * Eloquent magic __get routes through getAttribute, so every attribute that
 * the method accesses must be stubbed with shouldReceive('getAttribute').
 *
 * @param  array<string, mixed>  $attrs
 */
function makeJobForHealthcheck(array $attrs): array
{
    $job = Mockery::mock(ApplicationDeploymentJob::class)->makePartial();
    $job->shouldAllowMockingProtectedMethods();

    // Settings object — accessed as $this->application->settings->is_static
    $mockSettings = new stdClass;
    $mockSettings->is_static = $attrs['is_static'] ?? false;

    // Application mock — wire every attribute that generate_healthcheck_commands reads
    $mockApp = Mockery::mock(Application::class)->shouldIgnoreMissing();

    // Use array_key_exists so that an explicit null value is preserved (not replaced by ??)
    $port = array_key_exists('health_check_port', $attrs) ? $attrs['health_check_port'] : 3000;
    $mockApp->shouldReceive('getAttribute')->with('health_check_port')->andReturn($port);
    $mockApp->shouldReceive('getAttribute')->with('ports_exposes_array')->andReturn($attrs['ports_exposes_array'] ?? ['3000']);
    $mockApp->shouldReceive('getAttribute')->with('build_pack')->andReturn($attrs['build_pack'] ?? 'nixpacks');
    $mockApp->shouldReceive('getAttribute')->with('health_check_host')->andReturn($attrs['health_check_host'] ?? 'localhost');
    $mockApp->shouldReceive('getAttribute')->with('health_check_scheme')->andReturn($attrs['health_check_scheme'] ?? 'http');
    $mockApp->shouldReceive('getAttribute')->with('health_check_method')->andReturn($attrs['health_check_method'] ?? 'GET');
    $mockApp->shouldReceive('getAttribute')->with('health_check_path')->andReturn($attrs['health_check_path'] ?? '/');
    $mockApp->shouldReceive('getAttribute')->with('settings')->andReturn($mockSettings);

    // The method also accesses settings via $this->application->settings (property syntax)
    // Because shouldIgnoreMissing is set, unknown calls return null — but 'settings'
    // must return our stdClass so that ->is_static works.
    $mockApp->shouldReceive('__get')->with('settings')->andReturn($mockSettings)->byDefault();

    $reflection = new ReflectionClass(ApplicationDeploymentJob::class);

    $appProp = $reflection->getProperty('application');
    $appProp->setAccessible(true);
    $appProp->setValue($job, $mockApp);

    $fullUrlProp = $reflection->getProperty('full_healthcheck_url');
    $fullUrlProp->setAccessible(true);
    $fullUrlProp->setValue($job, null);

    $methodRef = $reflection->getMethod('generate_healthcheck_commands');
    $methodRef->setAccessible(true);

    return [$job, $methodRef, $reflection];
}

test('generate_healthcheck_commands returns string with curl wget nc bash fallback chain', function () {
    [$job, $method] = makeJobForHealthcheck([
        'health_check_port' => 3000,
        'health_check_host' => 'localhost',
        'health_check_scheme' => 'http',
        'health_check_method' => 'GET',
        'health_check_path' => '/health',
    ]);

    $result = $method->invoke($job);

    expect($result)->toBeString();
    expect($result)->toContain('curl');
    expect($result)->toContain('wget');
    expect($result)->toContain('nc');
    expect($result)->toContain('bash -c');
    expect($result)->toContain('|| exit 1');
});

test('generate_healthcheck_commands uses explicit health_check_port when set', function () {
    [$job, $method] = makeJobForHealthcheck([
        'health_check_port' => 8080,
        'health_check_host' => 'localhost',
        'health_check_scheme' => 'http',
        'health_check_method' => 'GET',
        'health_check_path' => '/',
    ]);

    $result = $method->invoke($job);

    expect($result)->toContain('8080');
});

test('generate_healthcheck_commands falls back to first exposed port when health_check_port is falsy', function () {
    [$job, $method] = makeJobForHealthcheck([
        'health_check_port' => null,
        'ports_exposes_array' => ['5000'],
        'health_check_host' => 'localhost',
        'health_check_scheme' => 'http',
        'health_check_method' => 'GET',
        'health_check_path' => '/',
    ]);

    $result = $method->invoke($job);

    expect($result)->toContain('5000');
});

test('generate_healthcheck_commands forces port 80 for static buildpack', function () {
    [$job, $method] = makeJobForHealthcheck([
        'health_check_port' => 3000,
        'ports_exposes_array' => ['3000'],
        'build_pack' => 'static',
        'is_static' => false,
        'health_check_host' => 'localhost',
        'health_check_scheme' => 'http',
        'health_check_method' => 'GET',
        'health_check_path' => '/',
    ]);

    $result = $method->invoke($job);

    expect($result)->toContain(':80');
    expect($result)->not->toContain(':3000');
});

test('generate_healthcheck_commands forces port 80 when application is_static flag is true', function () {
    [$job, $method] = makeJobForHealthcheck([
        'health_check_port' => 9000,
        'ports_exposes_array' => ['9000'],
        'build_pack' => 'nixpacks',
        'is_static' => true,
        'health_check_host' => 'localhost',
        'health_check_scheme' => 'http',
        'health_check_method' => 'GET',
        'health_check_path' => '/',
    ]);

    $result = $method->invoke($job);

    expect($result)->toContain(':80');
    expect($result)->not->toContain(':9000');
});

test('generate_healthcheck_commands uses health_check_path in URL', function () {
    [$job, $method] = makeJobForHealthcheck([
        'health_check_port' => 3000,
        'health_check_host' => 'localhost',
        'health_check_scheme' => 'http',
        'health_check_method' => 'GET',
        'health_check_path' => '/api/healthz',
    ]);

    $result = $method->invoke($job);

    expect($result)->toContain('/api/healthz');
});

test('generate_healthcheck_commands defaults path to slash when health_check_path is null', function () {
    [$job, $method] = makeJobForHealthcheck([
        'health_check_port' => 3000,
        'health_check_host' => 'localhost',
        'health_check_scheme' => 'http',
        'health_check_method' => 'GET',
        'health_check_path' => null,
    ]);

    $result = $method->invoke($job);

    // Null path falls back to '/' via the ?: operator in the source
    expect($result)->toContain('http://localhost:3000/');
});

test('generate_healthcheck_commands sets full_healthcheck_url property', function () {
    [$job, $method, $reflection] = makeJobForHealthcheck([
        'health_check_port' => 8000,
        'health_check_host' => 'localhost',
        'health_check_scheme' => 'https',
        'health_check_method' => 'GET',
        'health_check_path' => '/ready',
    ]);

    $method->invoke($job);

    $fullUrlProp = $reflection->getProperty('full_healthcheck_url');
    $fullUrlProp->setAccessible(true);

    expect($fullUrlProp->getValue($job))->toBe('GET: https://localhost:8000/ready');
});

test('generate_healthcheck_commands includes bash /dev/tcp fallback for TCP check', function () {
    [$job, $method] = makeJobForHealthcheck([
        'health_check_port' => 3000,
        'health_check_host' => 'localhost',
        'health_check_scheme' => 'http',
        'health_check_method' => 'GET',
        'health_check_path' => '/',
    ]);

    $result = $method->invoke($job);

    expect($result)->toContain('/dev/tcp/localhost/3000');
});

test('generate_healthcheck_commands includes nc TCP check with correct host and port', function () {
    [$job, $method] = makeJobForHealthcheck([
        'health_check_port' => 4200,
        'health_check_host' => '127.0.0.1',
        'health_check_scheme' => 'http',
        'health_check_method' => 'GET',
        'health_check_path' => '/',
    ]);

    $result = $method->invoke($job);

    expect($result)->toContain('nc -w5 -z 127.0.0.1 4200');
});

// -------------------------------------------------------------------------
// ApplicationDeploymentStatus enum
// -------------------------------------------------------------------------

test('ApplicationDeploymentStatus has QUEUED value', function () {
    expect(ApplicationDeploymentStatus::QUEUED->value)->toBe('queued');
});

test('ApplicationDeploymentStatus has IN_PROGRESS value', function () {
    expect(ApplicationDeploymentStatus::IN_PROGRESS->value)->toBe('in_progress');
});

test('ApplicationDeploymentStatus has FINISHED value', function () {
    expect(ApplicationDeploymentStatus::FINISHED->value)->toBe('finished');
});

test('ApplicationDeploymentStatus has FAILED value', function () {
    expect(ApplicationDeploymentStatus::FAILED->value)->toBe('failed');
});

test('ApplicationDeploymentStatus has CANCELLED_BY_USER value', function () {
    expect(ApplicationDeploymentStatus::CANCELLED_BY_USER->value)->toBe('cancelled-by-user');
});

test('ApplicationDeploymentStatus has PENDING_APPROVAL value', function () {
    expect(ApplicationDeploymentStatus::PENDING_APPROVAL->value)->toBe('pending_approval');
});

test('ApplicationDeploymentStatus has TIMED_OUT value', function () {
    expect(ApplicationDeploymentStatus::TIMED_OUT->value)->toBe('timed-out');
});

test('ApplicationDeploymentStatus contains all seven expected cases', function () {
    $cases = ApplicationDeploymentStatus::cases();

    expect($cases)->toHaveCount(7);

    $names = array_map(fn ($case) => $case->name, $cases);

    expect($names)->toContain('QUEUED');
    expect($names)->toContain('IN_PROGRESS');
    expect($names)->toContain('FINISHED');
    expect($names)->toContain('FAILED');
    expect($names)->toContain('CANCELLED_BY_USER');
    expect($names)->toContain('PENDING_APPROVAL');
    expect($names)->toContain('TIMED_OUT');
});

test('ApplicationDeploymentStatus can be constructed from raw string values', function () {
    expect(ApplicationDeploymentStatus::from('queued'))->toBe(ApplicationDeploymentStatus::QUEUED);
    expect(ApplicationDeploymentStatus::from('in_progress'))->toBe(ApplicationDeploymentStatus::IN_PROGRESS);
    expect(ApplicationDeploymentStatus::from('finished'))->toBe(ApplicationDeploymentStatus::FINISHED);
    expect(ApplicationDeploymentStatus::from('failed'))->toBe(ApplicationDeploymentStatus::FAILED);
    expect(ApplicationDeploymentStatus::from('cancelled-by-user'))->toBe(ApplicationDeploymentStatus::CANCELLED_BY_USER);
    expect(ApplicationDeploymentStatus::from('pending_approval'))->toBe(ApplicationDeploymentStatus::PENDING_APPROVAL);
    expect(ApplicationDeploymentStatus::from('timed-out'))->toBe(ApplicationDeploymentStatus::TIMED_OUT);
});
