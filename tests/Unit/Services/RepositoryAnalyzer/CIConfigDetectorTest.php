<?php

namespace Tests\Unit\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\CIConfigDetector;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/ci-config-test-'.uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->detector = new CIConfigDetector;
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        exec('rm -rf '.escapeshellarg($this->tempDir));
    }
});

test('detects github actions config', function () {
    mkdir($this->tempDir.'/.github/workflows', 0755, true);
    file_put_contents($this->tempDir.'/.github/workflows/ci.yml', <<<'YAML'
name: CI
on: [push]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - run: npm ci
      - run: npm run build
      - run: npm test
YAML);

    $config = $this->detector->detect($this->tempDir);

    expect($config)->not->toBeNull();
    expect($config->installCommand)->toBe('npm ci');
    expect($config->buildCommand)->toBe('npm run build');
    expect($config->testCommand)->toBe('npm test');
    expect($config->nodeVersion)->toBe('20');
    expect($config->detectedFrom)->toBe('GitHub Actions');
});

test('detects gitlab ci config', function () {
    file_put_contents($this->tempDir.'/.gitlab-ci.yml', <<<'YAML'
image: node:18

stages:
  - build
  - test

build:
  stage: build
  script:
    - npm ci
    - npm run build

test:
  stage: test
  script:
    - npm test
YAML);

    $config = $this->detector->detect($this->tempDir);

    expect($config)->not->toBeNull();
    expect($config->installCommand)->toBe('npm ci');
    expect($config->buildCommand)->toBe('npm run build');
    expect($config->testCommand)->toBe('npm test');
    expect($config->nodeVersion)->toBe('18');
    expect($config->detectedFrom)->toBe('GitLab CI');
});

test('detects circleci config', function () {
    mkdir($this->tempDir.'/.circleci', 0755, true);
    file_put_contents($this->tempDir.'/.circleci/config.yml', <<<'YAML'
version: 2.1
jobs:
  build:
    docker:
      - image: node:18
    steps:
      - checkout
      - run: yarn install
      - run: yarn build
      - run: yarn test
YAML);

    $config = $this->detector->detect($this->tempDir);

    expect($config)->not->toBeNull();
    expect($config->installCommand)->toBe('yarn install');
    expect($config->buildCommand)->toBe('yarn build');
    expect($config->testCommand)->toBe('yarn test');
    expect($config->detectedFrom)->toBe('CircleCI');
});

test('detects python version from github actions', function () {
    mkdir($this->tempDir.'/.github/workflows', 0755, true);
    file_put_contents($this->tempDir.'/.github/workflows/ci.yml', <<<'YAML'
name: Python CI
on: [push]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-python@v4
        with:
          python-version: '3.11'
      - run: pip install -r requirements.txt
      - run: pytest
YAML);

    $config = $this->detector->detect($this->tempDir);

    expect($config)->not->toBeNull();
    expect($config->pythonVersion)->toBe('3.11');
    expect($config->installCommand)->toContain('pip install');
    expect($config->testCommand)->toBe('pytest');
});

test('detects from package json as fallback', function () {
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'name' => 'test-app',
        'scripts' => [
            'build' => 'vite build',
            'test' => 'vitest',
            'start' => 'node dist/main.js',
        ],
        'engines' => [
            'node' => '>=18.0.0',
        ],
    ]));

    $config = $this->detector->detect($this->tempDir);

    expect($config)->not->toBeNull();
    expect($config->installCommand)->toBe('npm ci');
    expect($config->buildCommand)->toBe('npm run build');
    expect($config->testCommand)->toBe('npm test');
    expect($config->startCommand)->toBe('npm start');
    expect($config->nodeVersion)->toBe('18.0.0');
    expect($config->detectedFrom)->toBe('package.json');
});

test('detects pnpm from lockfile', function () {
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'name' => 'test-app',
        'scripts' => [
            'build' => 'next build',
        ],
    ]));
    file_put_contents($this->tempDir.'/pnpm-lock.yaml', 'lockfileVersion: 6.0');

    $config = $this->detector->detect($this->tempDir);

    expect($config)->not->toBeNull();
    expect($config->installCommand)->toBe('pnpm install');
    expect($config->buildCommand)->toBe('pnpm run build');
});

test('detects yarn from lockfile', function () {
    file_put_contents($this->tempDir.'/package.json', json_encode([
        'name' => 'test-app',
        'scripts' => [
            'build' => 'tsc',
            'test' => 'jest',
        ],
    ]));
    file_put_contents($this->tempDir.'/yarn.lock', '# yarn lockfile v1');

    $config = $this->detector->detect($this->tempDir);

    expect($config)->not->toBeNull();
    expect($config->installCommand)->toBe('yarn install');
    expect($config->buildCommand)->toBe('yarn build');
    expect($config->testCommand)->toBe('yarn test');
});

test('returns null when no ci config found', function () {
    $config = $this->detector->detect($this->tempDir);

    expect($config)->toBeNull();
});

test('detects go version from github actions', function () {
    mkdir($this->tempDir.'/.github/workflows', 0755, true);
    file_put_contents($this->tempDir.'/.github/workflows/go.yml', <<<'YAML'
name: Go
on: [push]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-go@v4
        with:
          go-version: '1.21'
      - run: go mod download
      - run: go build -v ./...
      - run: go test -v ./...
YAML);

    $config = $this->detector->detect($this->tempDir);

    expect($config)->not->toBeNull();
    expect($config->goVersion)->toBe('1.21');
    expect($config->installCommand)->toBe('go mod download');
    expect($config->testCommand)->toContain('go test');
});
