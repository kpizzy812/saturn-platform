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
        public ?string $publishDirectory = null,
        public string $type = 'backend',  // backend, frontend, fullstack, unknown
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'framework' => $this->framework,
            'build_pack' => $this->buildPack,
            'default_port' => $this->defaultPort,
            'build_command' => $this->buildCommand,
            'publish_directory' => $this->publishDirectory,
            'type' => $this->type,
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
