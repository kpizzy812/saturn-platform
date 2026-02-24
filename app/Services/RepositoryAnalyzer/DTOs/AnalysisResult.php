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
     * @param  AppDependency[]  $appDependencies
     * @param  DockerComposeService[]  $dockerComposeServices
     * @param  DetectedPersistentVolume[]  $persistentVolumes
     */
    public function __construct(
        public MonorepoInfo $monorepo,
        public array $applications,
        public array $databases,
        public array $services,
        public array $envVariables,
        public array $appDependencies = [],
        public array $dockerComposeServices = [],
        public ?CIConfig $ciConfig = null,
        public array $persistentVolumes = [],
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
                'comment' => $e->comment,
            ], $this->envVariables),
            'app_dependencies' => array_map(fn ($d) => [
                'app_name' => $d->appName,
                'depends_on' => $d->dependsOn,
                'internal_urls' => $d->internalUrls,
                'deploy_order' => $d->deployOrder,
            ], $this->appDependencies),
            'docker_compose_services' => array_map(fn ($s) => [
                'name' => $s->name,
                'image' => $s->image,
                'ports' => $s->ports,
                'is_database' => $s->isDatabase(),
            ], $this->dockerComposeServices),
            'ci_config' => $this->ciConfig ? [
                'install_command' => $this->ciConfig->installCommand,
                'build_command' => $this->ciConfig->buildCommand,
                'test_command' => $this->ciConfig->testCommand,
                'start_command' => $this->ciConfig->startCommand,
                'node_version' => $this->ciConfig->nodeVersion,
                'detected_from' => $this->ciConfig->detectedFrom,
            ] : null,
            'persistent_volumes' => array_map(fn ($v) => $v->toArray(), $this->persistentVolumes),
        ];
    }
}
