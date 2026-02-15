<?php

use App\Traits\EnvironmentVariableProtection;

// Anonymous class using the trait to test protected methods
class EnvVarProtectionTestClass
{
    use EnvironmentVariableProtection;

    public function testIsProtected(string $key): bool
    {
        return $this->isProtectedEnvironmentVariable($key);
    }

    public function testIsUsedInDockerCompose(string $key, ?string $dockerCompose): array
    {
        return $this->isEnvironmentVariableUsedInDockerCompose($key, $dockerCompose);
    }
}

beforeEach(function () {
    $this->testClass = new EnvVarProtectionTestClass;
});

it('identifies SERVICE_FQDN_ prefixed variables as protected', function () {
    expect($this->testClass->testIsProtected('SERVICE_FQDN_WEB'))->toBeTrue();
    expect($this->testClass->testIsProtected('SERVICE_FQDN_API_1'))->toBeTrue();
    expect($this->testClass->testIsProtected('SERVICE_FQDN_'))->toBeTrue();
});

it('identifies SERVICE_URL_ prefixed variables as protected', function () {
    expect($this->testClass->testIsProtected('SERVICE_URL_DATABASE'))->toBeTrue();
    expect($this->testClass->testIsProtected('SERVICE_URL_REDIS_2'))->toBeTrue();
    expect($this->testClass->testIsProtected('SERVICE_URL_'))->toBeTrue();
});

it('identifies SERVICE_NAME_ prefixed variables as protected', function () {
    expect($this->testClass->testIsProtected('SERVICE_NAME_BACKEND'))->toBeTrue();
    expect($this->testClass->testIsProtected('SERVICE_NAME_QUEUE_WORKER'))->toBeTrue();
    expect($this->testClass->testIsProtected('SERVICE_NAME_'))->toBeTrue();
});

it('identifies non-protected variables correctly', function () {
    expect($this->testClass->testIsProtected('DATABASE_URL'))->toBeFalse();
    expect($this->testClass->testIsProtected('APP_KEY'))->toBeFalse();
    expect($this->testClass->testIsProtected('REDIS_HOST'))->toBeFalse();
    expect($this->testClass->testIsProtected('SERVICE_'))->toBeFalse();
    expect($this->testClass->testIsProtected('MY_SERVICE_FQDN'))->toBeFalse();
    expect($this->testClass->testIsProtected(''))->toBeFalse();
});

it('returns false when docker compose is null', function () {
    [$isUsed, $reason] = $this->testClass->testIsUsedInDockerCompose('APP_ENV', null);

    expect($isUsed)->toBeFalse();
    expect($reason)->toBe('');
});

it('returns false when docker compose is empty string', function () {
    [$isUsed, $reason] = $this->testClass->testIsUsedInDockerCompose('APP_ENV', '');

    expect($isUsed)->toBeFalse();
    expect($reason)->toBe('');
});

it('detects direct variable usage in docker compose', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    environment:
      APP_ENV: production
      DATABASE_URL: postgresql://db
YAML;

    [$isUsed, $reason] = $this->testClass->testIsUsedInDockerCompose('APP_ENV', $dockerCompose);

    expect($isUsed)->toBeTrue();
    expect($reason)->toContain('APP_ENV');
    expect($reason)->toContain('used directly');
});

it('detects variable reference with dollar sign in docker compose', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    environment:
      CONNECTION_STRING: "postgresql://$DATABASE_HOST:$DATABASE_PORT"
YAML;

    [$isUsed, $reason] = $this->testClass->testIsUsedInDockerCompose('DATABASE_HOST', $dockerCompose);

    expect($isUsed)->toBeTrue();
    expect($reason)->toContain('DATABASE_HOST');
    expect($reason)->toContain('referenced');
});

it('returns false when variable is not used in docker compose', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    environment:
      APP_ENV: production
YAML;

    [$isUsed, $reason] = $this->testClass->testIsUsedInDockerCompose('REDIS_HOST', $dockerCompose);

    expect($isUsed)->toBeFalse();
    expect($reason)->toBe('');
});

it('handles invalid YAML gracefully', function () {
    $invalidYaml = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    environment: [unclosed array
YAML;

    [$isUsed, $reason] = $this->testClass->testIsUsedInDockerCompose('APP_ENV', $invalidYaml);

    expect($isUsed)->toBeFalse();
    expect($reason)->toBe('');
});

it('handles docker compose with no services section', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
YAML;

    [$isUsed, $reason] = $this->testClass->testIsUsedInDockerCompose('APP_ENV', $dockerCompose);

    expect($isUsed)->toBeFalse();
    expect($reason)->toBe('');
});

it('handles docker compose with non-array environment values', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    environment: "APP_ENV=production"
YAML;

    [$isUsed, $reason] = $this->testClass->testIsUsedInDockerCompose('APP_ENV', $dockerCompose);

    expect($isUsed)->toBeFalse();
    expect($reason)->toBe('');
});

it('detects variable usage across multiple services', function () {
    $dockerCompose = <<<'YAML'
version: '3.8'
services:
  web:
    image: nginx
    environment:
      NODE_ENV: production
  worker:
    image: worker
    environment:
      DATABASE_URL: "postgres://$DB_HOST"
YAML;

    [$isUsed, $reason] = $this->testClass->testIsUsedInDockerCompose('DB_HOST', $dockerCompose);

    expect($isUsed)->toBeTrue();
    expect($reason)->toContain('DB_HOST');
});
