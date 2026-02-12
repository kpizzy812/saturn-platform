<?php

namespace App\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rewire environment variable connections from source environment UUIDs
 * to target environment UUIDs.
 *
 * Used by both clone mode (after cloning) and promote mode (after config update).
 * Works by scanning all env vars of the target resource for source environment
 * resource UUIDs and replacing them with the corresponding target env resource UUIDs.
 */
class RewireConnectionsAction
{
    use AsAction;
    use ResourceConfigFields;

    /**
     * Rewire environment variable connections for a cloned/promoted resource.
     *
     * @param  Model  $target  The target resource whose env vars need rewiring
     * @param  Environment  $sourceEnv  The source environment (contains original UUIDs)
     * @param  Environment  $targetEnv  The target environment (contains replacement UUIDs)
     * @return array<string, array{old: string, new: string}> Rewired connections
     */
    public function handle(Model $target, Environment $sourceEnv, Environment $targetEnv): array
    {
        if (! method_exists($target, 'environment_variables')) {
            return [];
        }

        // Build UUID mapping: source UUID -> target UUID (matched by resource name)
        $uuidMap = $this->buildUuidMap($sourceEnv, $targetEnv);

        if (empty($uuidMap)) {
            return [];
        }

        // Scan and rewire env vars
        $rewired = [];
        $envVars = $target->environment_variables()->get();

        foreach ($envVars as $envVar) {
            $result = $this->tryRewireByUuidMap($envVar, $uuidMap);
            if ($result) {
                $rewired[$envVar->key] = $result;
            }
        }

        return $rewired;
    }

    /**
     * Build a UUID mapping from source environment resources to target environment resources.
     * Maps source resource UUIDs to target resource UUIDs, matched by name.
     *
     * @return array<string, string> sourceUuid => targetUuid
     */
    protected function buildUuidMap(Environment $sourceEnv, Environment $targetEnv): array
    {
        $map = [];

        // Map applications
        $sourceApps = $sourceEnv->applications()->get();
        $targetApps = $targetEnv->applications()->get();
        foreach ($sourceApps as $sourceApp) {
            $targetApp = $targetApps->firstWhere('name', $sourceApp->name);
            if ($targetApp) {
                $map[$sourceApp->uuid] = $targetApp->uuid;
            }
        }

        // Map all database types
        foreach (static::$databaseRelationMap as $modelClass => $relationMethod) {
            if (! method_exists($sourceEnv, $relationMethod)) {
                continue;
            }

            $sourceDbs = $sourceEnv->$relationMethod()->get();
            $targetDbs = $targetEnv->$relationMethod()->get();

            foreach ($sourceDbs as $sourceDb) {
                $targetDb = $targetDbs->firstWhere('name', $sourceDb->name);
                if ($targetDb) {
                    $map[$sourceDb->uuid] = $targetDb->uuid;
                }
            }
        }

        return $map;
    }

    /**
     * Try to rewire a single env var by replacing source UUIDs with target UUIDs.
     *
     * @param  array<string, string>  $uuidMap
     * @return array{old: string, new: string}|null
     */
    protected function tryRewireByUuidMap(EnvironmentVariable $envVar, array $uuidMap): ?array
    {
        $value = $envVar->getAttribute('value');
        if (empty($value)) {
            return null;
        }

        $newValue = $value;
        foreach ($uuidMap as $sourceUuid => $targetUuid) {
            $newValue = str_replace($sourceUuid, $targetUuid, $newValue);
        }

        if ($newValue === $value) {
            return null;
        }

        $oldValue = $value;
        $envVar->update(['value' => $newValue]);

        return [
            'old' => $this->maskSensitiveValue($oldValue),
            'new' => $this->maskSensitiveValue($newValue),
        ];
    }

    /**
     * Mask sensitive values for logging.
     */
    protected function maskSensitiveValue(string $value): string
    {
        return preg_replace('/:([^:@]+)@/', ':****@', $value);
    }
}
