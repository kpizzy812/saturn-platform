<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents dependencies between apps in a monorepo
 */
readonly class AppDependency
{
    public function __construct(
        public string $appName,
        public array $dependsOn = [],      // App names this app depends on
        public array $internalUrls = [],   // URLs to inject (e.g., ['API_URL' => 'api'])
        public int $deployOrder = 0,       // Lower = deploy first
    ) {}

    /**
     * Check if this app has dependencies
     */
    public function hasDependencies(): bool
    {
        return ! empty($this->dependsOn);
    }
}
