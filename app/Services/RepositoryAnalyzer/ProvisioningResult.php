<?php

namespace App\Services\RepositoryAnalyzer;

/**
 * Result of infrastructure provisioning
 */
class ProvisioningResult
{
    public function __construct(
        public array $applications,
        public array $databases,
        public ?string $monorepoGroupId,
    ) {}
}
