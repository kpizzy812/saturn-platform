<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents a detected application in a repository
 */
readonly class DetectedApp
{
    public function __construct(
        public string $name,
        public string $path,
        public string $framework,
        public string $buildPack,
        public int $defaultPort,
        public ?string $buildCommand = null,
        public ?string $installCommand = null,
        public ?string $startCommand = null,
        public ?string $publishDirectory = null,
        public string $type = 'backend',  // backend, frontend, fullstack, unknown
        public ?DetectedHealthCheck $healthCheck = null,
        public ?string $nodeVersion = null,
        public ?string $pythonVersion = null,
        public ?DockerfileInfo $dockerfileInfo = null,
        public string $applicationMode = 'web',  // web, worker, both
    ) {}

    /**
     * Create a new instance with additional properties
     */
    public function withHealthCheck(DetectedHealthCheck $healthCheck): self
    {
        return new self(
            name: $this->name,
            path: $this->path,
            framework: $this->framework,
            buildPack: $this->buildPack,
            defaultPort: $this->defaultPort,
            buildCommand: $this->buildCommand,
            installCommand: $this->installCommand,
            startCommand: $this->startCommand,
            publishDirectory: $this->publishDirectory,
            type: $this->type,
            healthCheck: $healthCheck,
            nodeVersion: $this->nodeVersion,
            pythonVersion: $this->pythonVersion,
            dockerfileInfo: $this->dockerfileInfo,
            applicationMode: $this->applicationMode,
        );
    }

    /**
     * Create a new instance with CI config
     */
    public function withCIConfig(CIConfig $ci): self
    {
        return new self(
            name: $this->name,
            path: $this->path,
            framework: $this->framework,
            buildPack: $this->buildPack,
            defaultPort: $this->defaultPort,
            buildCommand: $ci->buildCommand ?? $this->buildCommand,
            installCommand: $ci->installCommand ?? $this->installCommand,
            startCommand: $ci->startCommand ?? $this->startCommand,
            publishDirectory: $this->publishDirectory,
            type: $this->type,
            healthCheck: $this->healthCheck,
            nodeVersion: $ci->nodeVersion ?? $this->nodeVersion,
            pythonVersion: $ci->pythonVersion ?? $this->pythonVersion,
            dockerfileInfo: $this->dockerfileInfo,
            applicationMode: $this->applicationMode,
        );
    }

    /**
     * Create a new instance with custom port
     */
    public function withPort(int $port): self
    {
        return new self(
            name: $this->name,
            path: $this->path,
            framework: $this->framework,
            buildPack: $this->buildPack,
            defaultPort: $port,
            buildCommand: $this->buildCommand,
            installCommand: $this->installCommand,
            startCommand: $this->startCommand,
            publishDirectory: $this->publishDirectory,
            type: $this->type,
            healthCheck: $this->healthCheck,
            nodeVersion: $this->nodeVersion,
            pythonVersion: $this->pythonVersion,
            dockerfileInfo: $this->dockerfileInfo,
            applicationMode: $this->applicationMode,
        );
    }

    /**
     * Create a new instance with Dockerfile info
     */
    public function withDockerfileInfo(DockerfileInfo $dockerfileInfo): self
    {
        return new self(
            name: $this->name,
            path: $this->path,
            framework: $this->framework,
            buildPack: $this->buildPack,
            defaultPort: $this->defaultPort,
            buildCommand: $this->buildCommand,
            installCommand: $this->installCommand,
            startCommand: $this->startCommand,
            publishDirectory: $this->publishDirectory,
            type: $this->type,
            healthCheck: $this->healthCheck,
            nodeVersion: $dockerfileInfo->getNodeVersion() ?? $this->nodeVersion,
            pythonVersion: $dockerfileInfo->getPythonVersion() ?? $this->pythonVersion,
            dockerfileInfo: $dockerfileInfo,
            applicationMode: $this->applicationMode,
        );
    }

    /**
     * Create a new instance with application mode
     */
    public function withApplicationMode(string $mode): self
    {
        return new self(
            name: $this->name,
            path: $this->path,
            framework: $this->framework,
            buildPack: $this->buildPack,
            defaultPort: $this->defaultPort,
            buildCommand: $this->buildCommand,
            installCommand: $this->installCommand,
            startCommand: $this->startCommand,
            publishDirectory: $this->publishDirectory,
            type: $this->type,
            healthCheck: $this->healthCheck,
            nodeVersion: $this->nodeVersion,
            pythonVersion: $this->pythonVersion,
            dockerfileInfo: $this->dockerfileInfo,
            applicationMode: $mode,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'framework' => $this->framework,
            'build_pack' => $this->buildPack,
            'default_port' => $this->defaultPort,
            'build_command' => $this->buildCommand,
            'install_command' => $this->installCommand,
            'start_command' => $this->startCommand,
            'publish_directory' => $this->publishDirectory,
            'type' => $this->type,
            'health_check' => $this->healthCheck ? [
                'path' => $this->healthCheck->path,
                'method' => $this->healthCheck->method,
                'interval' => $this->healthCheck->intervalSeconds,
                'timeout' => $this->healthCheck->timeoutSeconds,
            ] : null,
            'node_version' => $this->nodeVersion,
            'python_version' => $this->pythonVersion,
            'dockerfile_info' => $this->dockerfileInfo?->toArray(),
            'application_mode' => $this->applicationMode,
        ];
    }

    /**
     * Check if this is a static site (frontend-only)
     */
    public function isStatic(): bool
    {
        return $this->buildPack === 'static';
    }

    /**
     * Check if this app can handle backend requests
     */
    public function hasBackend(): bool
    {
        return in_array($this->type, ['backend', 'fullstack'], true);
    }
}
