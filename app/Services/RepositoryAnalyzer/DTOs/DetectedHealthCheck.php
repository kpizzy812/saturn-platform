<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents a detected health check endpoint
 */
readonly class DetectedHealthCheck
{
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public int $intervalSeconds = 10,
        public int $timeoutSeconds = 5,
        public int $retries = 10,
        public int $startPeriodSeconds = 15,
        public ?string $detectedVia = null,
    ) {}
}
