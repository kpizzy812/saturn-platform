<?php

use App\Models\Application;
use App\Models\ApplicationSetting;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\Project;
use App\Models\StandaloneDocker;
use App\Services\ConfigurationGenerator;

describe('ConfigurationGenerator', function () {
    afterEach(function () {
        Mockery::close();
    });

    beforeEach(function () {
        // Mock Project
        $project = Mockery::mock(Project::class)->makePartial();
        $project->uuid = 'project-uuid-123';

        // Mock Environment
        $environment = Mockery::mock(Environment::class)->makePartial();
        $environment->uuid = 'env-uuid-456';

        // Mock Settings
        $settings = Mockery::mock(ApplicationSetting::class)->makePartial();
        $settings->shouldReceive('attributesToArray')->andReturn([
            'id' => 1,
            'application_id' => 1,
            'is_static' => false,
            'is_auto_deploy' => true,
            'is_force_https_enabled' => true,
            'is_dual_cert' => false,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        // Mock Destination
        $destination = Mockery::mock(StandaloneDocker::class)->makePartial();

        // Mock Application
        $this->application = Mockery::mock(Application::class)->makePartial()->shouldIgnoreMissing();
        $this->application->id = 1;
        $this->application->name = 'Test Application';
        $this->application->uuid = 'app-uuid-789';
        $this->application->description = 'Test application description';
        $this->application->destination_type = 'App\Models\StandaloneDocker';
        $this->application->destination_id = 1;
        $this->application->source_type = 'App\Models\GithubApp';
        $this->application->source_id = 1;
        $this->application->private_key_id = null;

        // Build pack settings
        $this->application->build_pack = 'nixpacks';
        $this->application->static_image = null;
        $this->application->base_directory = null;
        $this->application->publish_directory = null;
        $this->application->dockerfile = null;
        $this->application->dockerfile_location = '/Dockerfile';
        $this->application->dockerfile_target_build = null;
        $this->application->custom_docker_options = null;
        $this->application->compose_parsing_version = null;
        $this->application->docker_compose = null;
        $this->application->docker_compose_location = 'docker-compose.yml';
        $this->application->docker_compose_raw = null;
        $this->application->docker_compose_domains = null;
        $this->application->docker_compose_custom_start_command = null;
        $this->application->docker_compose_custom_build_command = null;
        $this->application->install_command = 'npm install';
        $this->application->build_command = 'npm run build';
        $this->application->start_command = 'npm start';
        $this->application->watch_paths = '/app';

        // Source settings
        $this->application->git_repository = 'https://github.com/user/repo';
        $this->application->git_branch = 'main';
        $this->application->git_commit_sha = 'abc123';
        $this->application->repository_project_id = null;

        // Docker registry
        $this->application->docker_registry_image_name = 'myapp';
        $this->application->docker_registry_image_tag = 'latest';

        // Domains
        $this->application->fqdn = 'app.example.com';
        $this->application->ports_exposes = '3000';
        $this->application->ports_mappings = '3000:3000';
        $this->application->redirect = null;
        $this->application->custom_nginx_configuration = null;

        // Preview
        $this->application->preview_url_template = 'pr-{{pr_id}}.example.com';

        // Commands
        $this->application->post_deployment_command = 'echo "deployed"';
        $this->application->post_deployment_command_container = 'app';
        $this->application->pre_deployment_command = 'echo "deploying"';
        $this->application->pre_deployment_command_container = 'app';

        // Health check
        $this->application->health_check_path = '/health';
        $this->application->health_check_port = 3000;
        $this->application->health_check_host = 'localhost';
        $this->application->health_check_method = 'GET';
        $this->application->health_check_return_code = 200;
        $this->application->health_check_scheme = 'http';
        $this->application->health_check_response_text = null;
        $this->application->health_check_interval = 30;
        $this->application->health_check_timeout = 5;
        $this->application->health_check_retries = 3;
        $this->application->health_check_start_period = 30;
        $this->application->health_check_enabled = true;

        // Webhooks
        $this->application->manual_webhook_secret_github = 'github-secret';
        $this->application->manual_webhook_secret_gitlab = 'gitlab-secret';
        $this->application->manual_webhook_secret_bitbucket = 'bitbucket-secret';
        $this->application->manual_webhook_secret_gitea = 'gitea-secret';

        // Swarm
        $this->application->swarm_replicas = 1;
        $this->application->swarm_placement_constraints = null;

        // Mock relationships
        $this->application->environment = $environment;
        $this->application->settings = $settings;
        $this->application->destination = $destination;

        $this->application->shouldReceive('project')->andReturn($project);
        $this->application->shouldReceive('getLimits')->andReturn([
            'cpus' => '1',
            'memory' => '512M',
        ]);

        // Mock environment variables
        $envVar = Mockery::mock(EnvironmentVariable::class)->makePartial();
        $envVar->key = 'NODE_ENV';
        $envVar->value = 'production';
        $envVar->is_preview = false;
        $envVar->is_multiline = false;

        $this->application->environment_variables = collect([$envVar]);
        $this->application->environment_variables_preview = collect([]);
    });

    describe('toArray', function () {
        it('returns config as array', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result)->toBeArray();
        });

        it('includes all expected top-level keys', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result)->toHaveKeys([
                'id',
                'name',
                'uuid',
                'description',
                'saturn_details',
                'build',
                'source',
                'docker_registry_image',
                'domains',
                'environment_variables',
                'settings',
                'preview',
                'limits',
                'health_check',
                'webhooks_secrets',
                'swarm',
                'post_deployment_command',
                'post_deployment_command_container',
                'pre_deployment_command',
                'pre_deployment_command_container',
            ]);
        });

        it('includes correct application basic info', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['id'])->toBe(1)
                ->and($result['name'])->toBe('Test Application')
                ->and($result['uuid'])->toBe('app-uuid-789')
                ->and($result['description'])->toBe('Test application description');
        });

        it('includes saturn_details section', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['saturn_details'])->toBeArray()
                ->and($result['saturn_details'])->toHaveKeys([
                    'project_uuid',
                    'environment_uuid',
                    'destination_type',
                    'destination_id',
                    'source_type',
                    'source_id',
                    'private_key_id',
                ]);
        });

        it('includes build section with correct keys', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['build'])->toBeArray()
                ->and($result['build'])->toHaveKeys([
                    'type',
                    'static_image',
                    'base_directory',
                    'publish_directory',
                    'dockerfile',
                    'dockerfile_location',
                    'dockerfile_target_build',
                    'custom_docker_run_options',
                    'compose_parsing_version',
                    'docker_compose',
                    'docker_compose_location',
                    'docker_compose_raw',
                    'docker_compose_domains',
                    'docker_compose_custom_start_command',
                    'docker_compose_custom_build_command',
                    'install_command',
                    'build_command',
                    'start_command',
                    'watch_paths',
                ]);
        });

        it('includes source section with correct keys', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['source'])->toBeArray()
                ->and($result['source'])->toHaveKeys([
                    'git_repository',
                    'git_branch',
                    'git_commit_sha',
                    'repository_project_id',
                ])
                ->and($result['source']['git_repository'])->toBe('https://github.com/user/repo')
                ->and($result['source']['git_branch'])->toBe('main')
                ->and($result['source']['git_commit_sha'])->toBe('abc123');
        });

        it('includes domains section with correct keys', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['domains'])->toBeArray()
                ->and($result['domains'])->toHaveKeys([
                    'fqdn',
                    'ports_exposes',
                    'ports_mappings',
                    'redirect',
                    'custom_nginx_configuration',
                ])
                ->and($result['domains']['fqdn'])->toBe('app.example.com');
        });

        it('includes environment_variables section', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['environment_variables'])->toBeArray()
                ->and($result['environment_variables'])->toHaveKeys(['production', 'preview'])
                ->and($result['environment_variables']['production'])->toBeArray()
                ->and($result['environment_variables']['preview'])->toBeArray();
        });

        it('includes environment variables with correct structure', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['environment_variables']['production'])->toHaveCount(1);
            $envVar = $result['environment_variables']['production'][0];

            expect($envVar)->toHaveKeys(['key', 'value', 'is_preview', 'is_multiline'])
                ->and($envVar['key'])->toBe('NODE_ENV')
                ->and($envVar['value'])->toBe('production');
        });

        it('includes settings section', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['settings'])->toBeArray();
        });

        it('excludes specific keys from settings', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['settings'])->not->toHaveKey('id')
                ->and($result['settings'])->not->toHaveKey('application_id')
                ->and($result['settings'])->not->toHaveKey('created_at')
                ->and($result['settings'])->not->toHaveKey('updated_at');
        });

        it('includes preview section', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['preview'])->toBeArray()
                ->and($result['preview'])->toHaveKey('preview_url_template')
                ->and($result['preview']['preview_url_template'])->toBe('pr-{{pr_id}}.example.com');
        });

        it('includes limits section', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['limits'])->toBeArray()
                ->and($result['limits']['cpus'])->toBe('1')
                ->and($result['limits']['memory'])->toBe('512M');
        });

        it('includes health_check section with all fields', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['health_check'])->toBeArray()
                ->and($result['health_check'])->toHaveKeys([
                    'health_check_path',
                    'health_check_port',
                    'health_check_host',
                    'health_check_method',
                    'health_check_return_code',
                    'health_check_scheme',
                    'health_check_response_text',
                    'health_check_interval',
                    'health_check_timeout',
                    'health_check_retries',
                    'health_check_start_period',
                    'health_check_enabled',
                ])
                ->and($result['health_check']['health_check_path'])->toBe('/health')
                ->and($result['health_check']['health_check_port'])->toBe(3000)
                ->and($result['health_check']['health_check_enabled'])->toBeTrue();
        });

        it('includes webhooks_secrets section', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['webhooks_secrets'])->toBeArray()
                ->and($result['webhooks_secrets'])->toHaveKeys([
                    'manual_webhook_secret_github',
                    'manual_webhook_secret_gitlab',
                    'manual_webhook_secret_bitbucket',
                    'manual_webhook_secret_gitea',
                ]);
        });

        it('includes swarm section', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['swarm'])->toBeArray()
                ->and($result['swarm'])->toHaveKeys([
                    'swarm_replicas',
                    'swarm_placement_constraints',
                ])
                ->and($result['swarm']['swarm_replicas'])->toBe(1);
        });

        it('includes docker_registry_image section', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['docker_registry_image'])->toBeArray()
                ->and($result['docker_registry_image'])->toHaveKeys(['image', 'tag'])
                ->and($result['docker_registry_image']['image'])->toBe('myapp')
                ->and($result['docker_registry_image']['tag'])->toBe('latest');
        });

        it('includes deployment commands', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toArray();

            expect($result['post_deployment_command'])->toBe('echo "deployed"')
                ->and($result['post_deployment_command_container'])->toBe('app')
                ->and($result['pre_deployment_command'])->toBe('echo "deploying"')
                ->and($result['pre_deployment_command_container'])->toBe('app');
        });
    });

    describe('toJson', function () {
        it('returns valid JSON string', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toJson();

            expect($result)->toBeString();
            expect(json_decode($result))->not->toBeNull();
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
        });

        it('JSON can be decoded back to array', function () {
            $generator = new ConfigurationGenerator($this->application);
            $jsonString = $generator->toJson();
            $decoded = json_decode($jsonString, true);

            expect($decoded)->toBeArray()
                ->and($decoded['name'])->toBe('Test Application')
                ->and($decoded['uuid'])->toBe('app-uuid-789');
        });

        it('JSON is pretty printed', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toJson();

            // Pretty printed JSON has newlines and indentation
            expect($result)->toContain("\n")
                ->and($result)->toContain('    ');
        });
    });

    describe('toYaml', function () {
        it('returns valid YAML string', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toYaml();

            expect($result)->toBeString()
                ->and($result)->toContain('name:')
                ->and($result)->toContain('uuid:');
        });

        it('YAML contains application name', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toYaml();

            expect($result)->toContain('Test Application');
        });

        it('YAML contains nested structures', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toYaml();

            expect($result)->toContain('saturn_details:')
                ->and($result)->toContain('build:')
                ->and($result)->toContain('health_check:');
        });

        it('YAML is properly indented', function () {
            $generator = new ConfigurationGenerator($this->application);
            $result = $generator->toYaml();

            // Should have indentation (2 spaces based on Yaml::dump params)
            $hasProjectUuid = str_contains($result, '  project_uuid:');
            $hasEnvironmentUuid = str_contains($result, '  environment_uuid:');

            expect($hasProjectUuid || $hasEnvironmentUuid)->toBeTrue();
        });
    });
});
