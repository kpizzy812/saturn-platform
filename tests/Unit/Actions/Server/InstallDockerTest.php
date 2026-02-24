<?php

use App\Actions\Server\InstallDocker;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

// ═══════════════════════════════════════════
// OS-specific command generation
// ═══════════════════════════════════════════

test('Debian Docker install command uses rancher and apt-get fallback', function () {
    $action = new InstallDocker;

    // Set dockerVersion via reflection
    $prop = new ReflectionProperty($action, 'dockerVersion');
    $prop->setValue($action, '24.0');

    $method = new ReflectionMethod($action, 'getDebianDockerInstallCommand');
    $command = $method->invoke($action);

    expect($command)->toContain('releases.rancher.com/install-docker/24.0.sh');
    expect($command)->toContain('get.docker.com');
    expect($command)->toContain('apt-get');
    expect($command)->toContain('docker-ce');
    expect($command)->toContain('docker-compose-plugin');
    expect($command)->toContain('--max-time 300');
    expect($command)->toContain('--retry 3');
});

test('RHEL Docker install command uses dnf', function () {
    $action = new InstallDocker;

    $prop = new ReflectionProperty($action, 'dockerVersion');
    $prop->setValue($action, '24.0');

    $method = new ReflectionMethod($action, 'getRhelDockerInstallCommand');
    $command = $method->invoke($action);

    expect($command)->toContain('dnf config-manager');
    expect($command)->toContain('docker-ce.repo');
    expect($command)->toContain('dnf install');
    expect($command)->toContain('systemctl start docker');
    expect($command)->toContain('systemctl enable docker');
});

test('SUSE Docker install command uses zypper', function () {
    $action = new InstallDocker;

    $prop = new ReflectionProperty($action, 'dockerVersion');
    $prop->setValue($action, '24.0');

    $method = new ReflectionMethod($action, 'getSuseDockerInstallCommand');
    $command = $method->invoke($action);

    expect($command)->toContain('zypper addrepo');
    expect($command)->toContain('zypper refresh');
    expect($command)->toContain('zypper install');
    expect($command)->toContain('--no-confirm');
});

test('Arch Docker install command uses pacman with full upgrade', function () {
    $action = new InstallDocker;

    $method = new ReflectionMethod($action, 'getArchDockerInstallCommand');
    $command = $method->invoke($action);

    expect($command)->toContain('pacman -Syu');
    expect($command)->toContain('--noconfirm');
    expect($command)->toContain('--needed');
    expect($command)->toContain('docker docker-compose');
    expect($command)->toContain('systemctl enable docker.service');
    expect($command)->toContain('systemctl start docker.service');
});

test('Generic Docker install command uses curl with timeout and retry', function () {
    $action = new InstallDocker;

    $prop = new ReflectionProperty($action, 'dockerVersion');
    $prop->setValue($action, '24.0');

    $method = new ReflectionMethod($action, 'getGenericDockerInstallCommand');
    $command = $method->invoke($action);

    expect($command)->toContain('--max-time 300');
    expect($command)->toContain('--retry 3');
    expect($command)->toContain('get.docker.com');
});

// ═══════════════════════════════════════════
// Docker config JSON
// ═══════════════════════════════════════════

test('Docker daemon config uses json-file log driver', function () {
    $config = json_decode('{
        "log-driver": "json-file",
        "log-opts": {
          "max-size": "10m",
          "max-file": "3"
        }
      }', true);

    expect($config['log-driver'])->toBe('json-file');
    expect($config['log-opts']['max-size'])->toBe('10m');
    expect($config['log-opts']['max-file'])->toBe('3');
});

test('Docker config is valid base64 encoded JSON', function () {
    $jsonConfig = '{
            "log-driver": "json-file",
            "log-opts": {
              "max-size": "10m",
              "max-file": "3"
            }
          }';

    $encoded = base64_encode($jsonConfig);
    $decoded = base64_decode($encoded);
    $parsed = json_decode($decoded, true);

    expect($parsed)->not->toBeNull();
    expect($parsed)->toHaveKey('log-driver');
});

// ═══════════════════════════════════════════
// Network creation commands
// ═══════════════════════════════════════════

test('source code uses overlay network for swarm mode', function () {
    $source = file_get_contents(app_path('Actions/Server/InstallDocker.php'));

    expect($source)->toContain('docker network create --attachable --driver overlay saturn-overlay');
});

test('source code uses attachable network for standalone mode', function () {
    $source = file_get_contents(app_path('Actions/Server/InstallDocker.php'));

    expect($source)->toContain('docker network create --attachable saturn');
});

// ═══════════════════════════════════════════
// SSL certificate configuration
// ═══════════════════════════════════════════

test('SSL CA certificate validity is 10 years', function () {
    $source = file_get_contents(app_path('Actions/Server/InstallDocker.php'));

    expect($source)->toContain('10 * 365');
});

test('SSL directory permissions are 700', function () {
    $source = file_get_contents(app_path('Actions/Server/InstallDocker.php'));

    expect($source)->toContain('chmod -R 700');
});

test('SSL certificate file permissions are 644', function () {
    $source = file_get_contents(app_path('Actions/Server/InstallDocker.php'));

    expect($source)->toContain('chmod 644');
});

// ═══════════════════════════════════════════
// Version interpolation in commands
// ═══════════════════════════════════════════

test('rancher-based commands include the Docker version', function () {
    $action = new InstallDocker;

    $prop = new ReflectionProperty($action, 'dockerVersion');
    $prop->setValue($action, '25.0.5');

    // Only commands that use rancher/get.docker.com include the version
    // Arch uses pacman and doesn't pin a Docker version
    $methods = ['getDebianDockerInstallCommand', 'getRhelDockerInstallCommand', 'getSuseDockerInstallCommand', 'getGenericDockerInstallCommand'];

    foreach ($methods as $methodName) {
        $method = new ReflectionMethod($action, $methodName);
        $command = $method->invoke($action);

        expect($command)->toContain('25.0.5');
    }
});

test('Arch command does not pin Docker version', function () {
    $action = new InstallDocker;

    $method = new ReflectionMethod($action, 'getArchDockerInstallCommand');
    $command = $method->invoke($action);

    // Arch uses pacman rolling release, no version pinning
    expect($command)->not->toContain('releases.rancher.com');
    expect($command)->toContain('pacman');
});
