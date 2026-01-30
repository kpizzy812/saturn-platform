<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Information extracted from a Dockerfile
 */
readonly class DockerfileInfo
{
    /**
     * @param  array<string, string|null>  $envVariables
     * @param  int[]  $exposedPorts
     * @param  array<string, string|null>  $buildArgs
     * @param  array<string, string>  $labels
     */
    public function __construct(
        public ?string $baseImage = null,
        public array $envVariables = [],
        public array $exposedPorts = [],
        public array $buildArgs = [],
        public ?string $workdir = null,
        public ?string $healthcheck = null,
        public ?string $entrypoint = null,
        public ?string $cmd = null,
        public array $labels = [],
    ) {}

    /**
     * Get the primary port (first exposed port)
     */
    public function getPrimaryPort(): ?int
    {
        return $this->exposedPorts[0] ?? null;
    }

    /**
     * Check if Dockerfile uses multi-stage builds
     */
    public function isMultiStage(): bool
    {
        // Multi-stage is detected by presence of AS in base image
        return $this->baseImage !== null && str_contains($this->baseImage, ' AS ');
    }

    /**
     * Get Node.js version from base image
     */
    public function getNodeVersion(): ?string
    {
        if ($this->baseImage === null) {
            return null;
        }

        // node:18, node:18-alpine, node:lts-alpine
        if (preg_match('/node:(\d+(?:\.\d+)?(?:\.\d+)?)/i', $this->baseImage, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get Python version from base image
     */
    public function getPythonVersion(): ?string
    {
        if ($this->baseImage === null) {
            return null;
        }

        // python:3.11, python:3.11-slim, python:3.11-alpine
        if (preg_match('/python:(\d+(?:\.\d+)?(?:\.\d+)?)/i', $this->baseImage, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get Go version from base image
     */
    public function getGoVersion(): ?string
    {
        if ($this->baseImage === null) {
            return null;
        }

        // golang:1.21, golang:1.21-alpine
        if (preg_match('/golang:(\d+(?:\.\d+)?(?:\.\d+)?)/i', $this->baseImage, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'base_image' => $this->baseImage,
            'env_variables' => $this->envVariables,
            'exposed_ports' => $this->exposedPorts,
            'build_args' => $this->buildArgs,
            'workdir' => $this->workdir,
            'healthcheck' => $this->healthcheck,
            'entrypoint' => $this->entrypoint,
            'cmd' => $this->cmd,
            'labels' => $this->labels,
            'node_version' => $this->getNodeVersion(),
            'python_version' => $this->getPythonVersion(),
            'go_version' => $this->getGoVersion(),
        ];
    }
}
