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
    public function __construct(
        public string $type,          // postgresql, mysql, mongodb, redis, clickhouse
        public string $name,
        public string $envVarName,    // DATABASE_URL, REDIS_URL, etc.
        public array $consumers = [], // App names that use this DB
        public ?string $detectedVia = null,
    ) {}

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
        ];
    }
}
