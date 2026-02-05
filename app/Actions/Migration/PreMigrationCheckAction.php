<?php

namespace App\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\MigrationHistory;
use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
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
            $errors[] = "Source resource '{$resource->name}' is in a degraded or exited state. Fix it before migrating.";
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
                $errors[] = "Resource '{$resource->name}' not found in target environment '{$targetEnvironment->name}'. Cannot use update_existing mode.";
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
            ->where('source_id', $resource->id)
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
        $name = $resource->name ?? null;
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

        $emptyVars = $resource->environment_variables
            ->filter(fn ($var) => empty($var->value) || $var->value === '')
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
            'id' => $resource->id,
            'attributes' => $resource->toArray(),
        ];

        return MigrationHistory::hasConfigChanged($resource, $currentConfig);
    }
}
