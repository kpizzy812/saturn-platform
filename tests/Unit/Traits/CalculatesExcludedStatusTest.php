<?php

use App\Traits\CalculatesExcludedStatus;
use Illuminate\Support\Collection;

// Test class using the trait
class CalculatesExcludedStatusTestClass
{
    use CalculatesExcludedStatus;

    public function testAppendExcludedSuffix(string $status): string
    {
        $reflection = new ReflectionMethod($this, 'appendExcludedSuffix');

        return $reflection->invoke($this, $status);
    }

    public function testGetExcludedContainersFromDockerCompose(?string $dockerComposeRaw): Collection
    {
        return $this->getExcludedContainersFromDockerCompose($dockerComposeRaw);
    }
}

beforeEach(function () {
    $this->testClass = new CalculatesExcludedStatusTestClass;
});

it('appends excluded suffix to running:healthy status', function () {
    $result = $this->testClass->testAppendExcludedSuffix('running:healthy');

    expect($result)->toBe('running:healthy:excluded');
});

it('appends excluded suffix to running:unhealthy status', function () {
    $result = $this->testClass->testAppendExcludedSuffix('running:unhealthy');

    expect($result)->toBe('running:unhealthy:excluded');
});

it('simplifies degraded status to degraded:excluded', function () {
    $result = $this->testClass->testAppendExcludedSuffix('degraded:unhealthy');

    expect($result)->toBe('degraded:excluded');
});

it('simplifies degraded:partial to degraded:excluded', function () {
    $result = $this->testClass->testAppendExcludedSuffix('degraded:partial');

    expect($result)->toBe('degraded:excluded');
});

it('simplifies paused status to paused:excluded', function () {
    $result = $this->testClass->testAppendExcludedSuffix('paused:unknown');

    expect($result)->toBe('paused:excluded');
});

it('simplifies paused to paused:excluded', function () {
    $result = $this->testClass->testAppendExcludedSuffix('paused');

    expect($result)->toBe('paused:excluded');
});

it('simplifies starting status to starting:excluded', function () {
    $result = $this->testClass->testAppendExcludedSuffix('starting:unknown');

    expect($result)->toBe('starting:excluded');
});

it('simplifies starting to starting:excluded', function () {
    $result = $this->testClass->testAppendExcludedSuffix('starting');

    expect($result)->toBe('starting:excluded');
});

it('does not append excluded suffix to exited status', function () {
    $result = $this->testClass->testAppendExcludedSuffix('exited');

    expect($result)->toBe('exited');
});

it('does not append excluded suffix to exited:error status', function () {
    $result = $this->testClass->testAppendExcludedSuffix('exited:error');

    expect($result)->toBe('exited');
});

it('appends excluded suffix to running status without health', function () {
    $result = $this->testClass->testAppendExcludedSuffix('running');

    expect($result)->toBe('running:excluded');
});

it('returns empty collection when docker compose is null', function () {
    $result = $this->testClass->testGetExcludedContainersFromDockerCompose(null);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toBeEmpty();
});

it('returns empty collection when docker compose is empty string', function () {
    $result = $this->testClass->testGetExcludedContainersFromDockerCompose('');

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toBeEmpty();
});

it('finds containers with exclude_from_hc label', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    exclude_from_hc: true
  worker:
    image: worker
YAML;

    $result = $this->testClass->testGetExcludedContainersFromDockerCompose($dockerCompose);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toHaveCount(1);
    expect($result->first())->toBe('web');
});

it('finds containers with restart: no policy', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    restart: always
  temp:
    image: temp
    restart: no
YAML;

    $result = $this->testClass->testGetExcludedContainersFromDockerCompose($dockerCompose);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toHaveCount(1);
    expect($result->first())->toBe('temp');
});

it('finds containers with both exclude_from_hc and restart: no', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    exclude_from_hc: true
  worker:
    image: worker
    restart: no
  db:
    image: postgres
YAML;

    $result = $this->testClass->testGetExcludedContainersFromDockerCompose($dockerCompose);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toHaveCount(2);
    expect($result)->toContain('web');
    expect($result)->toContain('worker');
});

it('returns empty collection when no services are excluded', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    restart: always
  worker:
    image: worker
    restart: unless-stopped
YAML;

    $result = $this->testClass->testGetExcludedContainersFromDockerCompose($dockerCompose);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toBeEmpty();
});

it('handles invalid YAML gracefully', function () {
    $invalidYaml = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    environment: [unclosed array
YAML;

    $result = $this->testClass->testGetExcludedContainersFromDockerCompose($invalidYaml);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toBeEmpty();
});

it('handles YAML that does not parse to array', function () {
    $invalidYaml = 'just a string';

    $result = $this->testClass->testGetExcludedContainersFromDockerCompose($invalidYaml);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toBeEmpty();
});

it('handles docker compose with no services section', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
volumes:
  data:
YAML;

    $result = $this->testClass->testGetExcludedContainersFromDockerCompose($dockerCompose);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toBeEmpty();
});

it('handles docker compose with services not as array', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services: "invalid"
YAML;

    $result = $this->testClass->testGetExcludedContainersFromDockerCompose($dockerCompose);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toBeEmpty();
});

it('handles exclude_from_hc with false value', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    exclude_from_hc: false
  worker:
    image: worker
    exclude_from_hc: true
YAML;

    $result = $this->testClass->testGetExcludedContainersFromDockerCompose($dockerCompose);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toHaveCount(1);
    expect($result->first())->toBe('worker');
});

it('defaults restart policy to always when not specified', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
  worker:
    image: worker
    restart: no
YAML;

    $result = $this->testClass->testGetExcludedContainersFromDockerCompose($dockerCompose);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toHaveCount(1);
    expect($result->first())->toBe('worker');
});
