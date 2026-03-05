<?php

namespace App\Services\SaturnYaml\DTOs;

class SaturnYamlApplication
{
    /**
     * @param  array<int, string>  $domains
     * @param  array<int, string>  $watchPaths
     * @param  array<string, string>  $environment
     * @param  array<int, string>  $dependsOn
     * @param  array{pre_deploy?: string, post_deploy?: string}  $hooks
     * @param  array{path?: string, interval?: int, timeout?: int, retries?: int}  $healthcheck
     */
    public function __construct(
        public string $name,
        public string $build = 'railpack',
        public ?string $gitBranch = null,
        public ?string $baseDirectory = null,
        public ?string $publishDirectory = null,
        public ?string $installCommand = null,
        public ?string $buildCommand = null,
        public ?string $startCommand = null,
        public ?string $dockerfile = null,
        public ?string $dockerfileLocation = null,
        public ?string $applicationType = 'web',
        public array $domains = [],
        public ?string $ports = null,
        public array $watchPaths = [],
        public array $environment = [],
        public array $dependsOn = [],
        public array $hooks = [],
        public array $healthcheck = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'build' => $this->build,
            'git_branch' => $this->gitBranch,
            'base_directory' => $this->baseDirectory,
            'publish_directory' => $this->publishDirectory,
            'install_command' => $this->installCommand,
            'build_command' => $this->buildCommand,
            'start_command' => $this->startCommand,
            'dockerfile' => $this->dockerfile,
            'dockerfile_location' => $this->dockerfileLocation,
            'application_type' => $this->applicationType,
            'domains' => $this->domains,
            'ports' => $this->ports,
            'watch_paths' => $this->watchPaths,
            'environment' => $this->environment,
            'depends_on' => $this->dependsOn,
            'hooks' => $this->hooks,
            'healthcheck' => $this->healthcheck,
        ];
    }
}
