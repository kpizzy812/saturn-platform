<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Complete repository analysis result
 */
class AnalysisResult
{
    /**
     * @param  DetectedApp[]  $applications
     * @param  DetectedDatabase[]  $databases
     * @param  DetectedService[]  $services
     * @param  DetectedEnvVariable[]  $envVariables
     */
    public function __construct(
        public MonorepoInfo $monorepo,
        public array $applications,
        public array $databases,
        public array $services,
        public array $envVariables,
    ) {}

    public function toArray(): array
    {
        return [
            'is_monorepo' => $this->monorepo->isMonorepo,
            'monorepo_type' => $this->monorepo->type,
            'applications' => array_map(fn ($a) => $a->toArray(), $this->applications),
            'databases' => array_map(fn ($d) => $d->toArray(), $this->databases),
            'services' => array_map(fn ($s) => [
                'type' => $s->type,
                'description' => $s->description,
                'required_env_vars' => $s->requiredEnvVars,
            ], $this->services),
            'env_variables' => array_map(fn ($e) => [
                'key' => $e->key,
                'default_value' => $e->defaultValue,
                'is_required' => $e->isRequired,
                'category' => $e->category,
                'for_app' => $e->forApp,
            ], $this->envVariables),
        ];
    }
}
