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

// is_public_repository Tests
test('is_public_repository returns true when source is public', function () {
    $app = new Application;
    $app->source = (object) ['is_public' => true];

    expect($app->is_public_repository())->toBeTrue();
});

test('is_public_repository returns false when source is not public', function () {
    $app = new Application;
    $app->source = (object) ['is_public' => false];

    expect($app->is_public_repository())->toBeFalse();
});

test('is_public_repository returns false when no source', function () {
    $app = new Application;
    $app->source = null;

    expect($app->is_public_repository())->toBeFalse();
});

// is_github_based Tests
test('is_github_based returns true when source exists', function () {
    $app = new Application;
    $app->source = (object) ['id' => 1];

    expect($app->is_github_based())->toBeTrue();
});

test('is_github_based returns false when no source', function () {
    $app = new Application;
    $app->source = null;

    expect($app->is_github_based())->toBeFalse();
});

// isForceHttpsEnabled / isStripprefixEnabled / isGzipEnabled Tests
test('isForceHttpsEnabled returns setting value', function () {
    $app = new Application;
    $app->setRelation('settings', (object) ['is_force_https_enabled' => true]);

    expect($app->isForceHttpsEnabled())->toBeTrue();
});

test('isStripprefixEnabled returns setting value', function () {
    $app = new Application;
    $app->setRelation('settings', (object) ['is_stripprefix_enabled' => false]);

    expect($app->isStripprefixEnabled())->toBeFalse();
});

test('isGzipEnabled returns setting value', function () {
    $app = new Application;
    $app->setRelation('settings', (object) ['is_gzip_enabled' => true]);

    expect($app->isGzipEnabled())->toBeTrue();
});

// isLogDrainEnabled Tests
test('isLogDrainEnabled returns true when enabled in settings', function () {
    $app = new Application;
    $app->setRelation('settings', (object) ['is_log_drain_enabled' => true]);

    expect($app->isLogDrainEnabled())->toBeTrue();
});

test('isLogDrainEnabled returns false when disabled in settings', function () {
    $app = new Application;
    $app->setRelation('settings', (object) ['is_log_drain_enabled' => false]);

    expect($app->isLogDrainEnabled())->toBeFalse();
});

// isDeployable / isPRDeployable Tests
test('isDeployable returns true when auto deploy enabled', function () {
    $app = new Application;
    $app->setRelation('settings', (object) ['is_auto_deploy_enabled' => true]);

    expect($app->isDeployable())->toBeTrue();
});

test('isDeployable returns false when auto deploy disabled', function () {
    $app = new Application;
    $app->setRelation('settings', (object) ['is_auto_deploy_enabled' => false]);

    expect($app->isDeployable())->toBeFalse();
});

test('isPRDeployable returns true when preview deployments enabled', function () {
    $app = new Application;
    $app->setRelation('settings', (object) ['is_preview_deployments_enabled' => true]);

    expect($app->isPRDeployable())->toBeTrue();
});

test('isPRDeployable returns false when preview deployments disabled', function () {
    $app = new Application;
    $app->setRelation('settings', (object) ['is_preview_deployments_enabled' => false]);

    expect($app->isPRDeployable())->toBeFalse();
});

// workdir / generateBaseDir / dirOnServer Tests
test('workdir returns path with uuid', function () {
    $app = new Application;
    $app->uuid = 'app-uuid-123';

    expect($app->workdir())->toContain('app-uuid-123');
});

test('generateBaseDir returns artifacts path', function () {
    $app = new Application;

    expect($app->generateBaseDir('deploy-uuid'))->toBe('/artifacts/deploy-uuid');
});

test('dirOnServer returns path with uuid', function () {
    $app = new Application;
    $app->uuid = 'app-uuid-456';

    expect($app->dirOnServer())->toContain('app-uuid-456');
});

// getLimits Tests
test('getLimits returns all limit fields', function () {
    $app = new Application;
    $app->limits_memory = '512m';
    $app->limits_memory_swap = '1024m';
    $app->limits_memory_swappiness = 60;
    $app->limits_memory_reservation = '256m';
    $app->limits_cpus = '2';
    $app->limits_cpuset = '0-1';
    $app->limits_cpu_shares = 1024;

    $limits = $app->getLimits();

    expect($limits)
        ->toHaveKey('limits_memory', '512m')
        ->toHaveKey('limits_memory_swap', '1024m')
        ->toHaveKey('limits_memory_swappiness', 60)
        ->toHaveKey('limits_memory_reservation', '256m')
        ->toHaveKey('limits_cpus', '2')
        ->toHaveKey('limits_cpuset', '0-1')
        ->toHaveKey('limits_cpu_shares', 1024);
});

// portsMappingsArray Tests
test('portsMappingsArray returns empty when null', function () {
    $app = new Application;
    $app->ports_mappings = null;

    expect($app->ports_mappings_array)->toBe([]);
});

test('portsMappingsArray splits comma-separated values', function () {
    $app = new Application;
    $app->ports_mappings = '8080:80,8443:443';

    expect($app->ports_mappings_array)->toBe(['8080:80', '8443:443']);
});

// portsExposesArray Tests
test('portsExposesArray returns empty when null', function () {
    $app = new Application;
    $app->ports_exposes = null;

    expect($app->ports_exposes_array)->toBe([]);
});

test('portsExposesArray splits comma-separated values', function () {
    $app = new Application;
    $app->ports_exposes = '80,443,8080';

    expect($app->ports_exposes_array)->toBe(['80', '443', '8080']);
});

// project() and team() Tests
test('project returns environment project', function () {
    $app = new Application;
    $project = (object) ['id' => 1, 'name' => 'Test'];
    $app->environment = (object) ['project' => $project];

    expect($app->project())->toBe($project);
});

test('project returns null when no environment', function () {
    $app = new Application;
    $app->environment = null;

    expect($app->project())->toBeNull();
});

test('team returns environment project team', function () {
    $app = new Application;
    $team = (object) ['id' => 1, 'name' => 'Team'];
    $app->environment = (object) ['project' => (object) ['team' => $team]];

    expect($app->team())->toBe($team);
});

// Attribute setters Tests
test('publishDirectory setter adds leading slash', function () {
    $app = new Application;
    $app->publish_directory = 'dist';

    expect($app->publish_directory)->toBe('/dist');
});

test('publishDirectory setter returns null for null', function () {
    $app = new Application;
    $app->publish_directory = null;

    expect($app->publish_directory)->toBeNull();
});

test('baseDirectory setter adds leading slash', function () {
    $app = new Application;
    $app->base_directory = 'app';

    expect($app->base_directory)->toBe('/app');
});

test('baseDirectory setter preserves leading slash', function () {
    $app = new Application;
    $app->base_directory = '/app';

    expect($app->base_directory)->toBe('/app');
});

test('dockerfileLocation setter defaults to /Dockerfile', function () {
    $app = new Application;
    $app->dockerfile_location = null;

    expect($app->dockerfile_location)->toBe('/Dockerfile');
});

test('dockerfileLocation setter adds leading slash', function () {
    $app = new Application;
    $app->dockerfile_location = 'docker/Dockerfile';

    expect($app->dockerfile_location)->toBe('/docker/Dockerfile');
});

test('dockerComposeLocation setter defaults to /docker-compose.yaml', function () {
    $app = new Application;
    $app->docker_compose_location = null;

    expect($app->docker_compose_location)->toBe('/docker-compose.yaml');
});

test('dockerComposeLocation setter adds leading slash', function () {
    $app = new Application;
    $app->docker_compose_location = 'compose.yml';

    expect($app->docker_compose_location)->toBe('/compose.yml');
});

test('portsMappings setter converts empty to null', function () {
    $app = new Application;
    $app->ports_mappings = '';

    expect($app->ports_mappings)->toBeNull();
});

// gitCommitLink Tests
test('gitCommitLink returns commit URL with source', function () {
    $app = new Application;
    $app->source = (object) ['html_url' => 'https://github.com'];
    $app->git_repository = 'user/repo';
    $app->git_branch = 'main';

    expect($app->gitCommitLink('abc123'))->toBe('https://github.com/user/repo/commit/abc123');
});

test('gitCommitLink returns bitbucket URL for bitbucket source', function () {
    $app = new Application;
    $app->source = (object) ['html_url' => 'https://bitbucket.org'];
    $app->git_repository = 'user/repo';
    $app->git_branch = 'main';

    expect($app->gitCommitLink('abc123'))->toBe('https://bitbucket.org/user/repo/commits/abc123');
});

// customNginxConfiguration Tests
test('customNginxConfiguration encodes on set and decodes on get', function () {
    $app = new Application;
    $config = 'server { listen 80; }';
    $app->custom_nginx_configuration = $config;

    expect($app->custom_nginx_configuration)->toBe($config);
});

// main_port Tests
test('main_port returns 80 for static app', function () {
    $app = new Application;
    $app->ports_exposes = '3000';
    $app->setRelation('settings', (object) ['is_static' => true]);

    expect($app->main_port())->toBe([80]);
});

test('main_port returns ports_exposes_array for non-static app', function () {
    $app = new Application;
    $app->ports_exposes = '3000,4000';
    $app->setRelation('settings', (object) ['is_static' => false]);

    expect($app->main_port())->toBe(['3000', '4000']);
});
