<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents a detected environment variable from .env.example
 */
class DetectedEnvVariable
{
    public function __construct(
        public string $key,
        public ?string $defaultValue,
        public bool $isRequired,
        public string $category,
        public string $forApp,
    ) {}
}
