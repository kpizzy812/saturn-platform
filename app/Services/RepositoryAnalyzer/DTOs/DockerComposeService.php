<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents a service detected from docker-compose.yml
 */
readonly class DockerComposeService
{
    public function __construct(
        public string $name,
        public string $image,
        public array $ports = [],
        public array $environment = [],
        public ?string $healthcheck = null,
        public array $dependsOn = [],
        public array $volumes = [],
    ) {}

    /**
     * Check if this is a database service
     */
    public function isDatabase(): bool
    {
        $dbImages = ['postgres', 'mysql', 'mariadb', 'mongo', 'redis', 'clickhouse'];

        foreach ($dbImages as $db) {
            if (str_contains(strtolower($this->image), $db)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get database type from image name
     */
    public function getDatabaseType(): ?string
    {
        $mappings = [
            'postgres' => 'postgresql',
            'mysql' => 'mysql',
            'mariadb' => 'mysql',
            'mongo' => 'mongodb',
            'redis' => 'redis',
            'clickhouse' => 'clickhouse',
        ];

        foreach ($mappings as $pattern => $type) {
            if (str_contains(strtolower($this->image), $pattern)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Get the first exposed port
     */
    public function getDefaultPort(): ?int
    {
        if (empty($this->ports)) {
            return null;
        }

        // Parse "3000:3000" or "3000" format
        $port = $this->ports[0];
        if (str_contains($port, ':')) {
            $parts = explode(':', $port);

            return (int) $parts[1]; // Container port
        }

        return (int) $port;
    }
}
