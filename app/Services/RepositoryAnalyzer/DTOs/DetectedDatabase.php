<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents a detected database dependency
 *
 * This DTO is immutable - use withMergedConsumers() to create
 * a new instance with additional consumers.
 */
readonly class DetectedDatabase
{
    public string $envVarName;

    public function __construct(
        public string $type,          // postgresql, mysql, mongodb, redis, clickhouse
        public string $name,
        ?string $envVarName = null,   // DATABASE_URL, REDIS_URL, etc.
        public array $consumers = [], // App names that use this DB
        public ?string $detectedVia = null,
        public ?int $port = null,
    ) {
        $this->envVarName = $envVarName ?? $this->getDefaultEnvVarName();
    }

    /**
     * Get default environment variable name based on database type
     */
    private function getDefaultEnvVarName(): string
    {
        return match ($this->type) {
            'postgresql' => 'DATABASE_URL',
            'mysql', 'mariadb' => 'DATABASE_URL',
            'mongodb' => 'MONGODB_URL',
            'redis' => 'REDIS_URL',
            'clickhouse' => 'CLICKHOUSE_URL',
            default => strtoupper($this->type).'_URL',
        };
    }

    /**
     * Create new instance with merged consumers (immutable pattern)
     *
     * @param  string[]  $additionalConsumers
     */
    public function withMergedConsumers(array $additionalConsumers): self
    {
        return new self(
            type: $this->type,
            name: $this->name,
            envVarName: $this->envVarName,
            consumers: array_unique(array_merge($this->consumers, $additionalConsumers)),
            detectedVia: $this->detectedVia,
            port: $this->port,
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'env_var_name' => $this->envVarName,
            'consumers' => $this->consumers,
            'detected_via' => $this->detectedVia,
            'port' => $this->port,
        ];
    }
}
