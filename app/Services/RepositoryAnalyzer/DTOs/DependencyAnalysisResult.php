<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Result of dependency analysis for a single application
 */
class DependencyAnalysisResult
{
    /**
     * @param  DetectedDatabase[]  $databases
     * @param  DetectedService[]  $services
     * @param  DetectedEnvVariable[]  $envVariables
     * @param  DetectedPersistentVolume[]  $persistentVolumes
     */
    public function __construct(
        public array $databases,
        public array $services,
        public array $envVariables,
        public array $persistentVolumes = [],
    ) {}
}
