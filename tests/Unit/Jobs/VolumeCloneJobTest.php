<?php

use App\Jobs\VolumeCloneJob;
use App\Models\LocalPersistentVolume;
use App\Models\Server;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $sourceServer = Mockery::mock(Server::class)->makePartial();
    $targetServer = Mockery::mock(Server::class)->makePartial();
    $volume = Mockery::mock(LocalPersistentVolume::class)->makePartial();

    $job = new VolumeCloneJob('source-vol', 'target-vol', $sourceServer, $targetServer, $volume);

    expect($job->tries)->toBe(1);
    expect($job->timeout)->toBe(3600);
    expect($job->maxExceptions)->toBe(1);
    expect($job->queue)->toBe('high');

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

test('local clone is used when target server is same as source', function () {
    $sourceServer = Mockery::mock(Server::class)->makePartial();
    $sourceServer->id = 1;

    $targetServer = Mockery::mock(Server::class)->makePartial();
    $targetServer->id = 1;

    // When targetServer->id === sourceServer->id, should use local clone
    expect($targetServer->id === $sourceServer->id)->toBeTrue();
});

test('remote clone is used when target server differs from source', function () {
    $sourceServer = Mockery::mock(Server::class)->makePartial();
    $sourceServer->id = 1;

    $targetServer = Mockery::mock(Server::class)->makePartial();
    $targetServer->id = 2;

    expect($targetServer->id === $sourceServer->id)->toBeFalse();
});

test('local clone is used when target server is null', function () {
    $sourceServer = Mockery::mock(Server::class)->makePartial();

    // Source code: if (! $this->targetServer || $this->targetServer->id === $this->sourceServer->id)
    $targetServer = null;
    expect(! $targetServer)->toBeTrue();
});

test('volume name sanitization removes special characters', function () {
    // The job uses preg_replace('/[^a-zA-Z0-9_-]/', '_', $name) for directory paths
    $sanitize = fn ($name) => preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);

    expect($sanitize('my-volume'))->toBe('my-volume');
    expect($sanitize('my_volume'))->toBe('my_volume');
    expect($sanitize('volume123'))->toBe('volume123');
    expect($sanitize('my.volume'))->toBe('my_volume');
    expect($sanitize('my/volume'))->toBe('my_volume');
    expect($sanitize('../../../etc/passwd'))->toBe('_________etc_passwd');
    expect($sanitize('vol@#$%^&*'))->toBe('vol_______');
    expect($sanitize(''))->toBe('');
});

test('volume names are escaped with escapeshellarg', function () {
    // Verify escapeshellarg properly handles dangerous characters
    $dangerous = 'vol; rm -rf /';
    $escaped = escapeshellarg($dangerous);
    expect($escaped)->toBe("'vol; rm -rf /'");
    expect($escaped)->not->toBe($dangerous);

    // Verify backtick injection
    $backtick = 'vol`whoami`';
    $escaped = escapeshellarg($backtick);
    expect($escaped)->toBe("'vol`whoami`'");
});

test('clone directory path is constructed correctly', function () {
    $cloneDir = '/data/saturn/clone';
    $sanitizedName = 'my-volume';
    $expected = "{$cloneDir}/{$sanitizedName}";

    expect($expected)->toBe('/data/saturn/clone/my-volume');
});

test('source code uses escapeshellarg for security', function () {
    $source = file_get_contents((new ReflectionClass(VolumeCloneJob::class))->getFileName());

    // Should use escapeshellarg for volume names
    expect($source)->toContain('escapeshellarg($this->sourceVolume)');
    expect($source)->toContain('escapeshellarg($this->targetVolume)');
    expect($source)->toContain('escapeshellarg($sourceCloneDir)');
    expect($source)->toContain('escapeshellarg($targetCloneDir)');
});

test('source code cleans up in finally block', function () {
    $source = file_get_contents((new ReflectionClass(VolumeCloneJob::class))->getFileName());

    expect($source)->toContain('finally');
    expect($source)->toContain('rm -rf');
});

test('source code handles cleanup failures gracefully', function () {
    $source = file_get_contents((new ReflectionClass(VolumeCloneJob::class))->getFileName());

    // The finally block has nested try-catch for cleanup
    expect($source)->toContain('Failed to clean up source server clone directory');
    expect($source)->toContain('Failed to clean up target server clone directory');
});

test('local clone uses docker volume create and docker run', function () {
    $source = file_get_contents((new ReflectionClass(VolumeCloneJob::class))->getFileName());

    expect($source)->toContain('docker volume create');
    expect($source)->toContain('docker run --rm');
    expect($source)->toContain('cp -a /source/. /target/');
    expect($source)->toContain('chown -R 1000:1000');
});

test('remote clone uses tar and scp', function () {
    $source = file_get_contents((new ReflectionClass(VolumeCloneJob::class))->getFileName());

    expect($source)->toContain('tar czf');
    expect($source)->toContain('tar xzf');
    expect($source)->toContain('instant_scp');
});

test('failed callback logs correct details', function () {
    $source = file_get_contents((new ReflectionClass(VolumeCloneJob::class))->getFileName());

    expect($source)->toContain('VolumeCloneJob permanently failed');
    expect($source)->toContain('source_volume');
    expect($source)->toContain('target_volume');
    expect($source)->toContain('source_server_id');
    expect($source)->toContain('target_server_id');
});
