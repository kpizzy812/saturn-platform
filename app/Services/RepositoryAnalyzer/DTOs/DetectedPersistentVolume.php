<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents an auto-detected persistent storage need
 *
 * Used when the analyzer detects that an application uses file-based
 * storage (e.g., SQLite) that requires a persistent volume to survive
 * container redeployments.
 */
readonly class DetectedPersistentVolume
{
    public function __construct(
        public string $name,       // Volume name, e.g., "sqlite-data"
        public string $mountPath,  // Container mount path, e.g., "/data"
        public string $reason,     // Human-readable reason, e.g., "SQLite database detected (better-sqlite3)"
        public string $forApp,     // App name that needs this volume
        public ?string $envVarName = null,  // Env var to set for the path, e.g., "DATABASE_PATH"
        public ?string $envVarValue = null, // Env var value, e.g., "/data/db.sqlite"
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'mount_path' => $this->mountPath,
            'reason' => $this->reason,
            'for_app' => $this->forApp,
            'env_var_name' => $this->envVarName,
            'env_var_value' => $this->envVarValue,
        ];
    }
}
