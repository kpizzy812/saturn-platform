<?php

namespace App\Services\AI\Chat\DTOs;

/**
 * Represents a single parsed command from user message.
 */
readonly class ParsedCommand
{
    public function __construct(
        public string $action,              // deploy, restart, stop, start, logs, status, delete
        public ?string $resourceType = null, // application, service, database, server, project
        public ?string $resourceName = null,
        public ?int $resourceId = null,
        public ?string $resourceUuid = null,
        public ?string $projectName = null,
        public ?string $environmentName = null,
    ) {}

    public function isActionable(): bool
    {
        return in_array($this->action, [
            'deploy',
            'restart',
            'stop',
            'start',
            'logs',
            'status',
            'delete',
        ], true);
    }

    public function isDangerous(): bool
    {
        return in_array($this->action, ['deploy', 'stop', 'delete'], true);
    }

    public function hasResource(): bool
    {
        return $this->resourceName !== null || $this->resourceId !== null || $this->resourceUuid !== null;
    }

    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'resource_type' => $this->resourceType,
            'resource_name' => $this->resourceName,
            'resource_id' => $this->resourceId,
            'resource_uuid' => $this->resourceUuid,
            'project_name' => $this->projectName,
            'environment_name' => $this->environmentName,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            action: $data['action'] ?? 'none',
            resourceType: $data['resource_type'] ?? null,
            resourceName: $data['resource_name'] ?? null,
            resourceId: isset($data['resource_id']) ? (int) $data['resource_id'] : null,
            resourceUuid: $data['resource_uuid'] ?? null,
            projectName: $data['project_name'] ?? null,
            environmentName: $data['environment_name'] ?? null,
        );
    }
}
