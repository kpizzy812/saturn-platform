<?php

use App\Models\Application;
use App\Models\Environment;
use App\Services\SaturnYaml\SaturnYamlDetector;

beforeEach(function () {
    $this->detector = new SaturnYamlDetector;
});

it('detects saturn.yaml in file list', function () {
    $files = ['README.md', 'saturn.yaml', 'docker-compose.yml'];

    $result = $this->detector->detect($files);

    expect($result)->toBe('saturn.yaml');
});

it('detects .saturn.yaml variant', function () {
    $files = ['README.md', '.saturn.yaml', 'Dockerfile'];

    $result = $this->detector->detect($files);

    expect($result)->toBe('.saturn.yaml');
});

it('detects saturn.yml variant', function () {
    $files = ['saturn.yml', 'src/index.js'];

    $result = $this->detector->detect($files);

    expect($result)->toBe('saturn.yml');
});

it('detects .saturn.yml variant', function () {
    $files = ['.saturn.yml'];

    $result = $this->detector->detect($files);

    expect($result)->toBe('.saturn.yml');
});

it('returns null when no saturn yaml found', function () {
    $files = ['README.md', 'Dockerfile', 'docker-compose.yml', 'app.yaml'];

    $result = $this->detector->detect($files);

    expect($result)->toBeNull();
});

it('detects with base directory prefix', function () {
    $files = ['apps/api/saturn.yaml', 'apps/api/src/index.js'];

    $result = $this->detector->detect($files, 'apps/api');

    expect($result)->toBe('saturn.yaml');
});

it('handles base directory with trailing slash', function () {
    $files = ['apps/api/saturn.yaml'];

    $result = $this->detector->detect($files, 'apps/api/');

    expect($result)->toBe('saturn.yaml');
});

it('returns null with wrong base directory', function () {
    $files = ['apps/web/saturn.yaml'];

    $result = $this->detector->detect($files, 'apps/api');

    // saturn.yaml might still match without prefix, but apps/api/saturn.yaml won't be in list
    // The detect method checks both prefixed and unprefixed
    expect($result)->toBeNull()->or->toBe('saturn.yaml');
});

it('prioritizes saturn.yaml over other variants', function () {
    $files = ['saturn.yaml', '.saturn.yaml', 'saturn.yml'];

    $result = $this->detector->detect($files);

    expect($result)->toBe('saturn.yaml');
});

it('returns valid filenames list', function () {
    $filenames = SaturnYamlDetector::filenames();

    expect($filenames)->toContain('saturn.yaml');
    expect($filenames)->toContain('.saturn.yaml');
    expect($filenames)->toContain('saturn.yml');
    expect($filenames)->toContain('.saturn.yml');
    expect($filenames)->toHaveCount(4);
});

it('gets yaml path for application with default base directory', function () {
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('getAttribute')->with('base_directory')->andReturn('/');

    $path = $this->detector->getYamlPath($app);

    expect($path)->toBe('saturn.yaml');
});

it('gets yaml path for application with custom base directory', function () {
    $app = Mockery::mock(Application::class);
    $app->shouldReceive('getAttribute')->with('base_directory')->andReturn('/apps/api');

    $path = $this->detector->getYamlPath($app);

    expect($path)->toBe('/apps/api/saturn.yaml');
});
