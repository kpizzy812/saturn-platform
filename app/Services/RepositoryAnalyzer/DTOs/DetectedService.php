<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents a detected external service dependency
 */
class DetectedService
{
    public function __construct(
        public string $type,
        public string $description,
        public array $requiredEnvVars,
        public array $consumers = [],
    ) {}
}
