<?php

namespace App\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\MigrationHistory;
use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Runs pre-migration checks before creating a migration record.
 * Returns pass/fail with errors and warnings.
 */
class PreMigrationCheckAction
{
    use AsAction;
    use ResourceConfigFields;

    /**
     * Run all pre-migration checks.
     *
     * @return array{pass: bool, errors: array<string>, warnings: array<string>, checks: array<string, bool>}
     */
    public function handle(
        Model $resource,
        Environment $targetEnvironment,
        Server $targetServer,
        array $options = []
    ): array {
        $errors = [];
        $warnings = [];
        $checks = [];

        // 1. Source health check
        $sourceHealthy = $this->checkSourceHealth($resource);
        $checks['source_health'] = $sourceHealthy;
        if (! $sourceHealthy) {
            $errors[] = "Source resource '{$resource->getAttribute('name')}' is in a degraded or exited state. Fix it before migrating.";
        }

        // 2. Active migration guard
        $noActiveMigration = $this->checkNoActiveMigration($resource);
        $checks['no_active_migration'] = $noActiveMigration;
        if (! $noActiveMigration) {
            $errors[] = 'An active migration already exists for this resource. Wait for it to complete or cancel it first.';
        }

        // 3. Target server checks
        $serverUsable = $this->checkTargetServer($targetServer);
        $checks['target_server'] = $serverUsable;
        if (! $serverUsable) {
            $errors[] = "Target server '{$targetServer->name}' is not usable or unreachable.";
        }

        // 4. Target existence check (for update_existing mode)
        $updateExisting = $options[EnvironmentMigration::OPTION_UPDATE_EXISTING] ?? false;
        if ($updateExisting) {
            $targetExists = $this->checkTargetExists($resource, $targetEnvironment);
            $checks['target_exists'] = $targetExists;
            if (! $targetExists) {
                $errors[] = "Resource '{$resource->getAttribute('name')}' not found in target environment '{$targetEnvironment->name}'. Cannot use update_existing mode.";
            }
        }

        // 5. Env var completeness warning
        $envVarWarnings = $this->checkEnvVarCompleteness($resource, $targetEnvironment);
        if (! empty($envVarWarnings)) {
            $checks['env_var_completeness'] = false;
            $warnings = array_merge($warnings, $envVarWarnings);
        } else {
            $checks['env_var_completeness'] = true;
        }

        // 6. Config drift warning
        $configChanged = $this->checkConfigDrift($resource);
        $checks['config_changed'] = $configChanged;
        if (! $configChanged) {
            $warnings[] = 'Resource configuration has not changed since the last migration. This migration may be redundant.';
        }

        // 7. Disk space check on target server
        $diskSpaceResult = $this->checkDiskSpace($targetServer);
        $checks['disk_space'] = $diskSpaceResult['pass'];
        if (! $diskSpaceResult['pass'] && $diskSpaceResult['critical']) {
            $errors[] = $diskSpaceResult['message'];
        } elseif (! $diskSpaceResult['pass']) {
            $warnings[] = $diskSpaceResult['message'];
        }

        // 8. Port conflict detection
        $portConflicts = $this->checkPortConflicts($resource, $targetServer);
        $checks['port_conflicts'] = empty($portConflicts);
        if (! empty($portConflicts)) {
            $warnings[] = 'Potential port conflicts on target server: '.implode(', ', $portConflicts).'. Consider changing port mappings.';
        }

        return [
            'pass' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'checks' => $checks,
        ];
    }

    /**
     * Check source resource is not in degraded/exited state.
     */
    protected function checkSourceHealth(Model $resource): bool
    {
        $status = $resource->status ?? null;
        if ($status === null) {
            return true;
        }

        $unhealthyPrefixes = ['exited', 'degraded'];
        foreach ($unhealthyPrefixes as $prefix) {
            if (str_starts_with($status, $prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check no active migration exists for this resource.
     */
    protected function checkNoActiveMigration(Model $resource): bool
    {
        return ! EnvironmentMigration::where('source_type', get_class($resource))
            ->where('source_id', $resource->getKey())
            ->whereIn('status', [
                EnvironmentMigration::STATUS_PENDING,
                EnvironmentMigration::STATUS_APPROVED,
                EnvironmentMigration::STATUS_IN_PROGRESS,
            ])
            ->exists();
    }

    /**
     * Check target server is usable and reachable.
     */
    protected function checkTargetServer(Server $server): bool
    {
        return $server->isFunctional();
    }

    /**
     * Check if target resource exists (for update_existing mode).
     */
    protected function checkTargetExists(Model $resource, Environment $targetEnvironment): bool
    {
        $name = $resource->getAttribute('name');
        if (! $name) {
            return false;
        }

        if ($resource instanceof \App\Models\Application) {
            return $targetEnvironment->applications()->where('name', $name)->exists();
        }

        if ($resource instanceof \App\Models\Service) {
            return $targetEnvironment->services()->where('name', $name)->exists();
        }

        // Check database types via their specific relation methods
        if ($this->isDatabase($resource)) {
            $relationMethod = $this->getDatabaseRelationMethod(get_class($resource));
            if ($relationMethod && method_exists($targetEnvironment, $relationMethod)) {
                return $targetEnvironment->$relationMethod()->where('name', $name)->exists();
            }
        }

        return false;
    }

    /**
     * Check for empty environment variables being copied to production.
     */
    protected function checkEnvVarCompleteness(Model $resource, Environment $targetEnvironment): array
    {
        $warnings = [];

        if (! method_exists($resource, 'environment_variables')) {
            return $warnings;
        }

        if (! $targetEnvironment->isProduction()) {
            return $warnings;
        }

        /** @var \Illuminate\Support\Collection $envVars */
        $envVars = $resource->getAttribute('environment_variables');
        $emptyVars = $envVars
            ->filter(fn ($var) => empty($var->value))
            ->pluck('key')
            ->toArray();

        if (! empty($emptyVars)) {
            $warnings[] = 'The following environment variables have empty values and will be copied to production: '.implode(', ', $emptyVars);
        }

        return $warnings;
    }

    /**
     * Check if config has changed since last migration (returns true if changed or no history).
     */
    protected function checkConfigDrift(Model $resource): bool
    {
        $currentConfig = [
            'type' => get_class($resource),
            'id' => $resource->getKey(),
            'attributes' => $resource->toArray(),
        ];

        return MigrationHistory::hasConfigChanged($resource, $currentConfig);
    }

    /**
     * Check disk space on target server via SSH.
     *
     * @return array{pass: bool, critical: bool, message: string, usage?: int}
     */
    protected function checkDiskSpace(Server $targetServer): array
    {
        try {
            $usage = $targetServer->getDiskUsage();
            if ($usage === null || $usage === '') {
                return ['pass' => true, 'critical' => false, 'message' => ''];
            }

            $usagePercent = (int) $usage;

            if ($usagePercent >= 95) {
                return [
                    'pass' => false,
                    'critical' => true,
                    'message' => "Target server disk is critically full ({$usagePercent}% used). Migration cannot proceed safely.",
                    'usage' => $usagePercent,
                ];
            }

            if ($usagePercent >= 80) {
                return [
                    'pass' => false,
                    'critical' => false,
                    'message' => "Target server disk usage is high ({$usagePercent}% used). Consider freeing space before migrating.",
                    'usage' => $usagePercent,
                ];
            }

            return ['pass' => true, 'critical' => false, 'message' => '', 'usage' => $usagePercent];
        } catch (\Throwable) {
            // SSH failure - don't block migration, just warn
            return [
                'pass' => false,
                'critical' => false,
                'message' => 'Could not check disk space on target server. Verify server connectivity.',
            ];
        }
    }

    /**
     * Check if resource ports conflict with existing services on target server.
     *
     * @return array<string> List of conflicting port descriptions
     */
    protected function checkPortConflicts(Model $resource, Server $targetServer): array
    {
        $ports = $this->extractPorts($resource);
        if (empty($ports)) {
            return [];
        }

        $conflicts = [];

        try {
            foreach ($ports as $port) {
                $output = instant_remote_process(
                    ["ss -Htuln state listening sport = :{$port} 2>/dev/null | head -1"],
                    $targetServer,
                    false
                );

                if ($output !== null && trim($output) !== '') {
                    $conflicts[] = "port {$port}";
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SSH failure during pre-migration port check, skipping', [
                'server_id' => $targetServer->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $conflicts;
    }

    /**
     * Extract exposed/mapped ports from a resource.
     *
     * @return array<string>
     */
    protected function extractPorts(Model $resource): array
    {
        $ports = [];

        // ports_mappings: "8080:80,9090:9090" — host ports are before the colon
        $mappings = $resource->getAttribute('ports_mappings');
        if ($mappings && is_string($mappings)) {
            foreach (explode(',', $mappings) as $mapping) {
                $parts = explode(':', trim($mapping));
                if (count($parts) >= 2 && is_numeric($parts[0])) {
                    $ports[] = trim($parts[0]);
                }
            }
        }

        return array_unique($ports);
    }
}
