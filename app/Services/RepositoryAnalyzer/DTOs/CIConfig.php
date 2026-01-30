<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents CI/CD configuration detected from workflow files
 */
readonly class CIConfig
{
    public function __construct(
        public ?string $installCommand = null,
        public ?string $buildCommand = null,
        public ?string $testCommand = null,
        public ?string $startCommand = null,
        public ?string $nodeVersion = null,
        public ?string $pythonVersion = null,
        public ?string $goVersion = null,
        public ?string $detectedFrom = null,
    ) {}

    public function hasAnyCommand(): bool
    {
        return $this->installCommand !== null
            || $this->buildCommand !== null
            || $this->testCommand !== null
            || $this->startCommand !== null;
    }
}
