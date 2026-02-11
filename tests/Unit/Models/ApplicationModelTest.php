<?php

use App\Models\Application;

// type() Tests
test('type returns application', function () {
    $app = new Application;
    expect($app->type())->toBe('application');
});

// isRunning Tests
test('isRunning returns true when status starts with running', function () {
    $app = new Application;
    $app->status = 'running';
    expect($app->isRunning())->toBeTrue();
});

test('isRunning returns true when status is running:healthy', function () {
    $app = new Application;
    $app->status = 'running:healthy';
    expect($app->isRunning())->toBeTrue();
});

test('isRunning returns false when status is exited', function () {
    $app = new Application;
    $app->status = 'exited';
    expect($app->isRunning())->toBeFalse();
});

test('isRunning returns false when status is stopped', function () {
    $app = new Application;
    $app->status = 'stopped';
    expect($app->isRunning())->toBeFalse();
});

// isExited Tests
test('isExited returns true when status starts with exited', function () {
    $app = new Application;
    $app->status = 'exited';
    expect($app->isExited())->toBeTrue();
});

test('isExited returns true when status is exited:0', function () {
    $app = new Application;
    $app->status = 'exited:0';
    expect($app->isExited())->toBeTrue();
});

test('isExited returns false when status is running', function () {
    $app = new Application;
    $app->status = 'running';
    expect($app->isExited())->toBeFalse();
});

// could_set_build_commands Tests
test('could_set_build_commands returns true for nixpacks', function () {
    $app = new Application;
    $app->build_pack = 'nixpacks';
    expect($app->could_set_build_commands())->toBeTrue();
});

test('could_set_build_commands returns false for dockerfile', function () {
    $app = new Application;
    $app->build_pack = 'dockerfile';
    expect($app->could_set_build_commands())->toBeFalse();
});

test('could_set_build_commands returns false for dockercompose', function () {
    $app = new Application;
    $app->build_pack = 'dockercompose';
    expect($app->could_set_build_commands())->toBeFalse();
});

test('could_set_build_commands returns false for static', function () {
    $app = new Application;
    $app->build_pack = 'static';
    expect($app->could_set_build_commands())->toBeFalse();
});

// git_based Tests
test('git_based returns true when no dockerfile and not dockerimage', function () {
    $app = new Application;
    $app->dockerfile = null;
    $app->build_pack = 'nixpacks';
    expect($app->git_based())->toBeTrue();
});

test('git_based returns false when dockerfile is set', function () {
    $app = new Application;
    $app->dockerfile = 'FROM node:18';
    $app->build_pack = 'dockerfile';
    expect($app->git_based())->toBeFalse();
});

test('git_based returns false when build_pack is dockerimage', function () {
    $app = new Application;
    $app->dockerfile = null;
    $app->build_pack = 'dockerimage';
    expect($app->git_based())->toBeFalse();
});

// isHealthcheckDisabled Tests
test('isHealthcheckDisabled returns true when health_check_enabled is false', function () {
    $app = new Application;
    $app->health_check_enabled = false;
    expect($app->isHealthcheckDisabled())->toBeTrue();
});

test('isHealthcheckDisabled returns false when health_check_enabled is true', function () {
    $app = new Application;
    $app->health_check_enabled = true;
    expect($app->isHealthcheckDisabled())->toBeFalse();
});

// isPartOfMonorepo Tests
test('isPartOfMonorepo returns true when monorepo_group_id is set', function () {
    $app = new Application;
    $app->monorepo_group_id = 'abc-123';
    expect($app->isPartOfMonorepo())->toBeTrue();
});

test('isPartOfMonorepo returns false when monorepo_group_id is null', function () {
    $app = new Application;
    $app->monorepo_group_id = null;
    expect($app->isPartOfMonorepo())->toBeFalse();
});

// deploymentType Tests
test('deploymentType returns deploy_key when private_key_id is set', function () {
    $app = new Application;
    $app->private_key_id = 5;
    $app->source = null;
    expect($app->deploymentType())->toBe('deploy_key');
});

test('deploymentType returns source when source is set and no private key', function () {
    $app = new Application;
    $app->private_key_id = null;
    $app->source = (object) ['id' => 1];
    expect($app->deploymentType())->toBe('source');
});

test('deploymentType returns other when no private key and no source', function () {
    $app = new Application;
    $app->private_key_id = null;
    $app->source = null;
    expect($app->deploymentType())->toBe('other');
});

// globMatch Tests (static)
test('globMatch matches simple wildcard pattern', function () {
    expect(Application::globMatch('src/*.js', 'src/index.js'))->toBeTrue();
    expect(Application::globMatch('src/*.js', 'src/app.js'))->toBeTrue();
    expect(Application::globMatch('src/*.js', 'lib/index.js'))->toBeFalse();
});

test('globMatch matches double wildcard pattern', function () {
    expect(Application::globMatch('src/**/*.ts', 'src/components/App.ts'))->toBeTrue();
    expect(Application::globMatch('src/**/*.ts', 'src/deep/nested/file.ts'))->toBeTrue();
    expect(Application::globMatch('**/*.ts', 'any/path/file.ts'))->toBeTrue();
});

test('globMatch matches exact file', function () {
    expect(Application::globMatch('package.json', 'package.json'))->toBeTrue();
    expect(Application::globMatch('package.json', 'other.json'))->toBeFalse();
});

test('globMatch matches question mark wildcard', function () {
    expect(Application::globMatch('file?.txt', 'file1.txt'))->toBeTrue();
    expect(Application::globMatch('file?.txt', 'fileA.txt'))->toBeTrue();
    expect(Application::globMatch('file?.txt', 'file12.txt'))->toBeFalse();
});

test('globMatch matches directory pattern', function () {
    expect(Application::globMatch('src/**', 'src/anything'))->toBeTrue();
    expect(Application::globMatch('src/**', 'src/deep/nested/path'))->toBeTrue();
    expect(Application::globMatch('docs/**', 'src/file.txt'))->toBeFalse();
});

// globToRegex Tests (static)
test('globToRegex converts simple wildcard', function () {
    $regex = Application::globToRegex('*.js');
    expect(preg_match($regex, 'index.js'))->toBe(1);
    expect(preg_match($regex, 'test/index.js'))->toBe(0);
});

test('globToRegex converts double wildcard', function () {
    $regex = Application::globToRegex('**/*.js');
    expect(preg_match($regex, 'src/index.js'))->toBe(1);
    expect(preg_match($regex, 'deep/nested/file.js'))->toBe(1);
});

test('globToRegex escapes special regex characters', function () {
    $regex = Application::globToRegex('file.txt');
    expect(preg_match($regex, 'file.txt'))->toBe(1);
    expect(preg_match($regex, 'fileatxt'))->toBe(0);
});

// matchPaths Tests (static)
test('matchPaths returns matching files for inclusion patterns', function () {
    $files = collect(['src/app.ts', 'src/index.ts', 'README.md', 'package.json']);
    $patterns = collect(['src/**']);

    $result = Application::matchPaths($files, $patterns);

    expect($result)->toContain('src/app.ts')
        ->toContain('src/index.ts')
        ->not->toContain('README.md')
        ->not->toContain('package.json');
});

test('matchPaths returns empty for null watch paths', function () {
    $files = collect(['src/app.ts']);
    $result = Application::matchPaths($files, null);
    expect($result)->toBeEmpty();
});

test('matchPaths returns empty for empty watch paths', function () {
    $files = collect(['src/app.ts']);
    $result = Application::matchPaths($files, collect([]));
    expect($result)->toBeEmpty();
});

test('matchPaths supports negation patterns', function () {
    $files = collect(['src/app.ts', 'src/test.ts', 'src/index.ts']);
    $patterns = collect(['src/**', '!src/test.ts']);

    $result = Application::matchPaths($files, $patterns);

    expect($result)->toContain('src/app.ts')
        ->toContain('src/index.ts')
        ->not->toContain('src/test.ts');
});

test('matchPaths with only exclusion patterns includes non-matching files', function () {
    $files = collect(['src/app.ts', 'src/test.ts', 'docs/readme.md']);
    $patterns = collect(['!src/test.ts']);

    $result = Application::matchPaths($files, $patterns);

    expect($result)->toContain('src/app.ts')
        ->toContain('docs/readme.md')
        ->not->toContain('src/test.ts');
});

test('matchPaths last matching pattern wins', function () {
    $files = collect(['src/app.ts', 'src/test.ts']);
    $patterns = collect(['src/**', '!src/**', 'src/app.ts']);

    $result = Application::matchPaths($files, $patterns);

    expect($result)->toContain('src/app.ts')
        ->not->toContain('src/test.ts');
});

// detectPortFromEnvironment Tests
test('detectPortFromEnvironment returns port from env vars', function () {
    $app = new Application;

    $envVar = (object) ['key' => 'PORT', 'real_value' => '3000'];
    $app->environment_variables = collect([$envVar]);

    expect($app->detectPortFromEnvironment())->toBe(3000);
});

test('detectPortFromEnvironment returns null when PORT not set', function () {
    $app = new Application;

    $envVar = (object) ['key' => 'APP_NAME', 'real_value' => 'test'];
    $app->environment_variables = collect([$envVar]);

    expect($app->detectPortFromEnvironment())->toBeNull();
});

test('detectPortFromEnvironment returns null for non-numeric port', function () {
    $app = new Application;

    $envVar = (object) ['key' => 'PORT', 'real_value' => 'invalid'];
    $app->environment_variables = collect([$envVar]);

    expect($app->detectPortFromEnvironment())->toBeNull();
});

test('detectPortFromEnvironment uses preview vars when isPreview is true', function () {
    $app = new Application;

    $app->environment_variables = collect([]);
    $envVarPreview = (object) ['key' => 'PORT', 'real_value' => '4000'];
    $app->environment_variables_preview = collect([$envVarPreview]);

    expect($app->detectPortFromEnvironment(true))->toBe(4000);
});

test('detectPortFromEnvironment returns null for empty env vars', function () {
    $app = new Application;
    $app->environment_variables = collect([]);

    expect($app->detectPortFromEnvironment())->toBeNull();
});
