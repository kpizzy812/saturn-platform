<?php

namespace App\Services\AI\Chat\DTOs;

/**
 * Result of command execution.
 */
readonly class CommandResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public ?array $data = null,
        public ?string $error = null,
    ) {}

    public static function success(string $message, ?array $data = null): self
    {
        return new self(
            success: true,
            message: $message,
            data: $data,
        );
    }

    public static function failed(string $message, ?string $error = null): self
    {
        return new self(
            success: false,
            message: $message,
            error: $error,
        );
    }

    public static function unauthorized(string $message = 'You are not authorized to perform this action.'): self
    {
        return new self(
            success: false,
            message: $message,
            error: 'unauthorized',
        );
    }

    public static function notFound(string $resourceType): self
    {
        return new self(
            success: false,
            message: "The {$resourceType} was not found or you don't have access to it.",
            error: 'not_found',
        );
    }

    public static function needsResource(string $action, string $resourceType, array $availableResources = []): self
    {
        $message = "Which {$resourceType} would you like to {$action}?";

        if (! empty($availableResources)) {
            // Group by project and environment
            $grouped = [];
            foreach ($availableResources as $resource) {
                $project = $resource['project'] ?? 'Unknown Project';
                $env = $resource['environment'] ?? 'default';
                $grouped[$project][$env][] = $resource;
            }

            foreach ($grouped as $projectName => $environments) {
                $message .= "\n\n📁 **{$projectName}**";
                foreach ($environments as $envName => $resources) {
                    $message .= "\n  📂 *{$envName}*:";
                    foreach (array_slice($resources, 0, 5) as $resource) {
                        $status = $resource['status'] ?? '';
                        $statusEmoji = match ($status) {
                            'running' => '🟢',
                            'stopped' => '🔴',
                            default => '⚪',
                        };
                        $message .= "\n    - {$statusEmoji} **{$resource['name']}**";
                    }
                }
            }

            // Get first resource for example
            $firstProject = array_key_first($grouped);
            $firstEnv = array_key_first($grouped[$firstProject]);
            $firstName = $grouped[$firstProject][$firstEnv][0]['name'] ?? 'app-name';

            $message .= "\n\nPlease specify project and environment:\n";
            $message .= "*\"{$action} {$firstName} in {$firstProject}/{$firstEnv}\"*\n";
            $message .= "or just: *\"{$action} {$firstName}\"* if the name is unique";
        } else {
            $message .= "\n\nNo {$resourceType}s found in your team.";
        }

        return new self(
            success: false,
            message: $message,
            error: 'needs_resource',
            data: ['available' => $availableResources],
        );
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'error' => $this->error,
        ];
    }
}
