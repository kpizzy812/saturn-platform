<?php

use App\Services\SaturnYaml\SaturnYamlParser;

beforeEach(function () {
    $this->parser = new SaturnYamlParser;
});

it('parses minimal saturn.yaml', function () {
    $yaml = <<<'YAML'
version: "1"
applications:
  api:
    build: railpack
    ports: "3000"
YAML;

    $config = $this->parser->parse($yaml);

    expect($config->version)->toBe('1');
    expect($config->applications)->toHaveCount(1);
    expect($config->applications['api']->name)->toBe('api');
    expect($config->applications['api']->build)->toBe('railpack');
    expect($config->applications['api']->ports)->toBe('3000');
});

it('parses full saturn.yaml with all sections', function () {
    $yaml = <<<'YAML'
version: "1"

applications:
  api:
    build: dockerfile
    git_branch: main
    base_directory: /apps/api
    domains:
      - api.example.com
    ports: "3000"
    watch_paths:
      - apps/api/**
      - packages/shared/**
    environment:
      NODE_ENV: production
      DATABASE_URL: "@db.connection_string"
    depends_on: [db, redis]
    hooks:
      pre_deploy: "php artisan down"
      post_deploy: "php artisan migrate --force"
    healthcheck:
      path: /health
      interval: 30

  worker:
    build: railpack
    application_type: worker
    command: "php artisan queue:work"
    depends_on: [db]

databases:
  db:
    type: postgresql
    version: "15"
    backups:
      schedule: "0 3 * * *"
      retention: 7
  redis:
    type: redis

cron:
  cleanup:
    container: api
    command: "php artisan schedule:run"
    schedule: "* * * * *"

shared_variables:
  APP_KEY: "base64:testkey"
YAML;

    $config = $this->parser->parse($yaml);

    expect($config->applications)->toHaveCount(2);
    expect($config->databases)->toHaveCount(2);
    expect($config->cron)->toHaveCount(1);
    expect($config->sharedVariables)->toHaveCount(1);

    // Application details
    $api = $config->applications['api'];
    expect($api->build)->toBe('dockerfile');
    expect($api->gitBranch)->toBe('main');
    expect($api->baseDirectory)->toBe('/apps/api');
    expect($api->domains)->toBe(['api.example.com']);
    expect($api->watchPaths)->toBe(['apps/api/**', 'packages/shared/**']);
    expect($api->dependsOn)->toBe(['db', 'redis']);
    expect($api->hooks)->toBe(['pre_deploy' => 'php artisan down', 'post_deploy' => 'php artisan migrate --force']);
    expect($api->healthcheck['path'])->toBe('/health');
    expect($api->environment['DATABASE_URL'])->toBe('@db.connection_string');

    // Worker
    $worker = $config->applications['worker'];
    expect($worker->applicationType)->toBe('worker');
    expect($worker->dependsOn)->toBe(['db']);

    // Databases
    $db = $config->databases['db'];
    expect($db->type)->toBe('postgresql');
    expect($db->version)->toBe('15');
    expect($db->backups['schedule'])->toBe('0 3 * * *');

    $redis = $config->databases['redis'];
    expect($redis->type)->toBe('redis');

    // Cron
    $cron = $config->cron['cleanup'];
    expect($cron->container)->toBe('api');
    expect($cron->command)->toBe('php artisan schedule:run');
    expect($cron->schedule)->toBe('* * * * *');

    // Shared variables
    expect($config->sharedVariables['APP_KEY'])->toBe('base64:testkey');
});

it('validates invalid build pack', function () {
    $yaml = <<<'YAML'
version: "1"
applications:
  api:
    build: invalid_pack
YAML;

    $errors = $this->parser->validate($yaml);
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('build');
    expect($errors[0])->toContain('invalid_pack');
});

it('validates invalid database type', function () {
    $yaml = <<<'YAML'
version: "1"
databases:
  db:
    type: oracle
YAML;

    $errors = $this->parser->validate($yaml);
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('oracle');
});

it('validates unknown dependency reference', function () {
    $yaml = <<<'YAML'
version: "1"
applications:
  api:
    depends_on: [nonexistent_db]
YAML;

    $errors = $this->parser->validate($yaml);
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('nonexistent_db');
});

it('validates self-dependency', function () {
    $yaml = <<<'YAML'
version: "1"
applications:
  api:
    depends_on: [api]
YAML;

    $errors = $this->parser->validate($yaml);
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('itself');
});

it('validates invalid cron schedule', function () {
    $yaml = <<<'YAML'
version: "1"
cron:
  cleanup:
    command: "php artisan cleanup"
    schedule: "not a cron"
YAML;

    $errors = $this->parser->validate($yaml);
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('cron');
});

it('validates invalid YAML syntax', function () {
    $yaml = 'invalid: yaml: [broken';

    $errors = $this->parser->validate($yaml);
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('YAML');
});

it('rejects unsupported version', function () {
    $yaml = <<<'YAML'
version: "2"
applications:
  api:
    build: railpack
YAML;

    expect(fn () => $this->parser->parse($yaml))
        ->toThrow(InvalidArgumentException::class, 'version');
});

it('generates consistent hash', function () {
    $yaml = <<<'YAML'
version: "1"
applications:
  api:
    build: railpack
    ports: "3000"
databases:
  db:
    type: postgresql
YAML;

    $config1 = $this->parser->parse($yaml);
    $config2 = $this->parser->parse($yaml);

    expect($config1->hash())->toBe($config2->hash());
});

it('generates different hash for different content', function () {
    $yaml1 = <<<'YAML'
version: "1"
applications:
  api:
    build: railpack
YAML;

    $yaml2 = <<<'YAML'
version: "1"
applications:
  api:
    build: nixpacks
YAML;

    $config1 = $this->parser->parse($yaml1);
    $config2 = $this->parser->parse($yaml2);

    expect($config1->hash())->not->toBe($config2->hash());
});

it('accepts valid yaml with no errors', function () {
    $yaml = <<<'YAML'
version: "1"
applications:
  api:
    build: railpack
    ports: "3000"
    depends_on: [db]
databases:
  db:
    type: postgresql
    version: "15"
    backups:
      schedule: "0 3 * * *"
cron:
  migrate:
    command: "php artisan migrate"
    schedule: "0 0 * * *"
    container: api
YAML;

    $errors = $this->parser->validate($yaml);
    expect($errors)->toBeEmpty();
});

it('parses empty sections gracefully', function () {
    $yaml = <<<'YAML'
version: "1"
YAML;

    $config = $this->parser->parse($yaml);

    expect($config->applications)->toBeEmpty();
    expect($config->databases)->toBeEmpty();
    expect($config->cron)->toBeEmpty();
    expect($config->sharedVariables)->toBeEmpty();
});

it('defaults to railpack build pack', function () {
    $yaml = <<<'YAML'
version: "1"
applications:
  api: {}
YAML;

    $config = $this->parser->parse($yaml);
    expect($config->applications['api']->build)->toBe('railpack');
});

it('validates invalid application type', function () {
    $yaml = <<<'YAML'
version: "1"
applications:
  api:
    application_type: invalid
YAML;

    $errors = $this->parser->validate($yaml);
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('application_type');
});
