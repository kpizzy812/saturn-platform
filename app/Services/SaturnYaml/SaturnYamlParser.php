<?php

namespace App\Services\SaturnYaml;

use App\Services\SaturnYaml\DTOs\SaturnYamlApplication;
use App\Services\SaturnYaml\DTOs\SaturnYamlConfig;
use App\Services\SaturnYaml\DTOs\SaturnYamlCronJob;
use App\Services\SaturnYaml\DTOs\SaturnYamlDatabase;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

class SaturnYamlParser
{
    private const VALID_BUILD_PACKS = ['railpack', 'nixpacks', 'dockerfile', 'static', 'dockercompose'];

    private const VALID_DB_TYPES = ['postgresql', 'mysql', 'mariadb', 'mongodb', 'redis', 'keydb', 'dragonfly', 'clickhouse'];

    private const VALID_APP_TYPES = ['web', 'worker', 'both'];

    /**
     * Parse a saturn.yaml string into a typed configuration object.
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $content): SaturnYamlConfig
    {
        $data = Yaml::parse($content);

        if (! is_array($data)) {
            throw new InvalidArgumentException('saturn.yaml must be a valid YAML document.');
        }

        $this->validateVersion($data);

        return new SaturnYamlConfig(
            version: (string) ($data['version'] ?? '1'),
            applications: $this->parseApplications($data['applications'] ?? []),
            databases: $this->parseDatabases($data['databases'] ?? []),
            cron: $this->parseCronJobs($data['cron'] ?? []),
            sharedVariables: $this->parseSharedVariables($data['shared_variables'] ?? []),
        );
    }

    /**
     * Validate the saturn.yaml content and return errors.
     *
     * @return array<int, string>
     */
    public function validate(string $content): array
    {
        $errors = [];

        try {
            $data = Yaml::parse($content);
        } catch (\Exception $e) {
            return ["Invalid YAML syntax: {$e->getMessage()}"];
        }

        if (! is_array($data)) {
            return ['saturn.yaml must be a valid YAML document.'];
        }

        // Validate version
        $version = $data['version'] ?? null;
        if ($version !== null && (string) $version !== '1') {
            $errors[] = "Unsupported version: {$version}. Only version '1' is supported.";
        }

        // Validate applications
        foreach ($data['applications'] ?? [] as $name => $app) {
            $prefix = "applications.{$name}";

            if (! is_string($name) || empty(trim($name))) {
                $errors[] = "{$prefix}: name must be a non-empty string.";
            }

            if (isset($app['build']) && ! in_array($app['build'], self::VALID_BUILD_PACKS, true)) {
                $errors[] = "{$prefix}.build: invalid value '{$app['build']}'. Valid: ".implode(', ', self::VALID_BUILD_PACKS);
            }

            if (isset($app['application_type']) && ! in_array($app['application_type'], self::VALID_APP_TYPES, true)) {
                $errors[] = "{$prefix}.application_type: invalid value '{$app['application_type']}'. Valid: ".implode(', ', self::VALID_APP_TYPES);
            }

            if (isset($app['ports']) && ! preg_match('/^\d+([,;]\d+)*$/', (string) $app['ports'])) {
                $errors[] = "{$prefix}.ports: must be a comma-separated list of port numbers.";
            }

            // Validate depends_on references
            $allNames = array_merge(
                array_keys($data['applications'] ?? []),
                array_keys($data['databases'] ?? []),
            );
            foreach ($app['depends_on'] ?? [] as $dep) {
                if (! in_array($dep, $allNames, true)) {
                    $errors[] = "{$prefix}.depends_on: unknown dependency '{$dep}'.";
                }
                if ($dep === $name) {
                    $errors[] = "{$prefix}.depends_on: resource cannot depend on itself.";
                }
            }
        }

        // Validate databases
        foreach ($data['databases'] ?? [] as $name => $db) {
            $prefix = "databases.{$name}";

            if (! is_string($name) || empty(trim($name))) {
                $errors[] = "{$prefix}: name must be a non-empty string.";
            }

            $type = $db['type'] ?? 'postgresql';
            if (! in_array($type, self::VALID_DB_TYPES, true)) {
                $errors[] = "{$prefix}.type: invalid value '{$type}'. Valid: ".implode(', ', self::VALID_DB_TYPES);
            }

            if (isset($db['backups']['schedule'])) {
                if (! $this->isValidCron($db['backups']['schedule'])) {
                    $errors[] = "{$prefix}.backups.schedule: invalid cron expression.";
                }
            }
        }

        // Validate cron jobs
        foreach ($data['cron'] ?? [] as $name => $cron) {
            $prefix = "cron.{$name}";

            if (empty($cron['command'] ?? '')) {
                $errors[] = "{$prefix}.command: is required.";
            }

            if (empty($cron['schedule'] ?? '')) {
                $errors[] = "{$prefix}.schedule: is required.";
            } elseif (! $this->isValidCron($cron['schedule'])) {
                $errors[] = "{$prefix}.schedule: invalid cron expression.";
            }
        }

        return $errors;
    }

    /**
     * @return array<string, SaturnYamlApplication>
     */
    private function parseApplications(array $data): array
    {
        $apps = [];

        foreach ($data as $name => $app) {
            if (! is_array($app)) {
                $app = [];
            }

            $apps[$name] = new SaturnYamlApplication(
                name: $name,
                build: $app['build'] ?? 'railpack',
                gitBranch: $app['git_branch'] ?? null,
                baseDirectory: $app['base_directory'] ?? null,
                publishDirectory: $app['publish_directory'] ?? null,
                installCommand: $app['install_command'] ?? null,
                buildCommand: $app['build_command'] ?? null,
                startCommand: $app['start_command'] ?? null,
                dockerfile: $app['dockerfile'] ?? null,
                dockerfileLocation: $app['dockerfile_location'] ?? null,
                applicationType: $app['application_type'] ?? 'web',
                domains: (array) ($app['domains'] ?? []),
                ports: isset($app['ports']) ? (string) $app['ports'] : null,
                watchPaths: (array) ($app['watch_paths'] ?? []),
                environment: (array) ($app['environment'] ?? []),
                dependsOn: (array) ($app['depends_on'] ?? []),
                hooks: (array) ($app['hooks'] ?? []),
                healthcheck: (array) ($app['healthcheck'] ?? []),
            );
        }

        return $apps;
    }

    /**
     * @return array<string, SaturnYamlDatabase>
     */
    private function parseDatabases(array $data): array
    {
        $dbs = [];

        foreach ($data as $name => $db) {
            if (! is_array($db)) {
                $db = [];
            }

            $dbs[$name] = new SaturnYamlDatabase(
                name: $name,
                type: $db['type'] ?? 'postgresql',
                version: $db['version'] ?? null,
                image: $db['image'] ?? null,
                isPublic: (bool) ($db['is_public'] ?? false),
                backups: (array) ($db['backups'] ?? []),
            );
        }

        return $dbs;
    }

    /**
     * @return array<string, SaturnYamlCronJob>
     */
    private function parseCronJobs(array $data): array
    {
        $jobs = [];

        foreach ($data as $name => $cron) {
            if (! is_array($cron)) {
                continue;
            }

            $jobs[$name] = new SaturnYamlCronJob(
                name: $name,
                command: $cron['command'] ?? '',
                schedule: $cron['schedule'] ?? '* * * * *',
                container: $cron['container'] ?? null,
                timeout: (int) ($cron['timeout'] ?? 3600),
            );
        }

        return $jobs;
    }

    /**
     * @return array<string, string>
     */
    private function parseSharedVariables(array $data): array
    {
        $vars = [];
        foreach ($data as $key => $value) {
            $vars[(string) $key] = (string) $value;
        }

        return $vars;
    }

    private function validateVersion(array $data): void
    {
        $version = $data['version'] ?? '1';
        if ((string) $version !== '1') {
            throw new InvalidArgumentException("Unsupported saturn.yaml version: {$version}. Only version '1' is supported.");
        }
    }

    private function isValidCron(string $expression): bool
    {
        // Basic cron validation: 5 space-separated fields
        $parts = preg_split('/\s+/', trim($expression));

        return is_array($parts) && count($parts) === 5;
    }
}
