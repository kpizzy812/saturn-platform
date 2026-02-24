<?php

namespace App\Actions\EnvDiff;

use App\Models\Environment;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Compare environment variables across two environments within a project.
 * Returns key-only diffs (never exposes values) for security.
 */
class CompareEnvironmentsAction
{
    use AsAction;

    /**
     * Resource type → environment relation method mapping.
     */
    private const RESOURCE_RELATIONS = [
        'application' => 'applications',
        'service' => 'services',
        'postgresql' => 'postgresqls',
        'mysql' => 'mysqls',
        'mariadb' => 'mariadbs',
        'mongodb' => 'mongodbs',
        'redis' => 'redis',
        'keydb' => 'keydbs',
        'dragonfly' => 'dragonflies',
        'clickhouse' => 'clickhouses',
    ];

    /**
     * @param  string|null  $resourceTypeFilter  Filter by type: application, service, database, or null for all
     * @return array{resources: array, summary: array}
     */
    public function handle(
        Environment $source,
        Environment $target,
        ?string $resourceTypeFilter = null
    ): array {
        $results = [];

        $relationsToCompare = $this->getRelationsForFilter($resourceTypeFilter);

        foreach ($relationsToCompare as $type => $relation) {
            $sourceResources = $source->$relation()->with('environment_variables')->get();
            $targetResources = $target->$relation()->with('environment_variables')->get();

            $targetByName = $targetResources->keyBy('name');
            $sourceByName = $sourceResources->keyBy('name');

            // Match by name
            foreach ($sourceResources as $sourceResource) {
                $name = $sourceResource->name;
                $targetResource = $targetByName->get($name);

                if ($targetResource) {
                    $diff = $this->diffEnvVars($sourceResource, $targetResource);
                    $results[] = [
                        'name' => $name,
                        'type' => $this->normalizeType($type),
                        'source_env' => $source->name,
                        'target_env' => $target->name,
                        'matched' => true,
                        'diff' => $diff,
                    ];
                } else {
                    // Only in source
                    $keys = $this->getEnvKeys($sourceResource);
                    $results[] = [
                        'name' => $name,
                        'type' => $this->normalizeType($type),
                        'source_env' => $source->name,
                        'target_env' => $target->name,
                        'matched' => false,
                        'only_in' => 'source',
                        'diff' => [
                            'added' => $keys,
                            'removed' => [],
                            'changed' => [],
                            'unchanged' => [],
                        ],
                    ];
                }
            }

            // Resources only in target
            foreach ($targetResources as $targetResource) {
                $name = $targetResource->name;
                if (! $sourceByName->has($name)) {
                    $keys = $this->getEnvKeys($targetResource);
                    $results[] = [
                        'name' => $name,
                        'type' => $this->normalizeType($type),
                        'source_env' => $source->name,
                        'target_env' => $target->name,
                        'matched' => false,
                        'only_in' => 'target',
                        'diff' => [
                            'added' => [],
                            'removed' => $keys,
                            'changed' => [],
                            'unchanged' => [],
                        ],
                    ];
                }
            }
        }

        // Calculate summary
        $totalAdded = 0;
        $totalRemoved = 0;
        $totalChanged = 0;
        $totalUnchanged = 0;
        $totalUnmatched = 0;

        foreach ($results as $r) {
            $totalAdded += count($r['diff']['added']);
            $totalRemoved += count($r['diff']['removed']);
            $totalChanged += count($r['diff']['changed']);
            $totalUnchanged += count($r['diff']['unchanged']);
            if (! $r['matched']) {
                $totalUnmatched++;
            }
        }

        return [
            'resources' => $results,
            'summary' => [
                'total_resources' => count($results),
                'matched_resources' => count($results) - $totalUnmatched,
                'unmatched_resources' => $totalUnmatched,
                'total_added' => $totalAdded,
                'total_removed' => $totalRemoved,
                'total_changed' => $totalChanged,
                'total_unchanged' => $totalUnchanged,
            ],
        ];
    }

    /**
     * Diff env var keys between two resources.
     * Returns keys only, NEVER values.
     */
    private function diffEnvVars(Model $source, Model $target): array
    {
        /** @var \Illuminate\Support\Collection|null $sourceEnvVars */
        $sourceEnvVars = $source->getAttribute('environment_variables');
        /** @var \Illuminate\Support\Collection|null $targetEnvVars */
        $targetEnvVars = $target->getAttribute('environment_variables');

        if (! $sourceEnvVars || ! $targetEnvVars) {
            return ['added' => [], 'removed' => [], 'changed' => [], 'unchanged' => []];
        }

        $sourceVars = $sourceEnvVars->keyBy('key');
        $targetVars = $targetEnvVars->keyBy('key');

        $added = [];
        $removed = [];
        $changed = [];
        $unchanged = [];

        foreach ($sourceVars as $key => $sourceVar) {
            if (! $targetVars->has($key)) {
                $added[] = $key;
            } elseif ($sourceVar->value !== $targetVars[$key]->value) {
                $changed[] = $key;
            } else {
                $unchanged[] = $key;
            }
        }

        foreach ($targetVars as $key => $targetVar) {
            if (! $sourceVars->has($key)) {
                $removed[] = $key;
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'unchanged' => $unchanged,
        ];
    }

    /**
     * Get env var keys for a resource.
     */
    private function getEnvKeys(Model $resource): array
    {
        /** @var \Illuminate\Support\Collection|null $envVars */
        $envVars = $resource->getAttribute('environment_variables');

        if (! $envVars) {
            return [];
        }

        return $envVars->pluck('key')->toArray();
    }

    /**
     * Get relation methods to compare based on filter.
     */
    private function getRelationsForFilter(?string $filter): array
    {
        if ($filter === null) {
            return self::RESOURCE_RELATIONS;
        }

        if ($filter === 'database') {
            return array_filter(self::RESOURCE_RELATIONS, fn ($type) => ! in_array($type, ['application', 'service']), ARRAY_FILTER_USE_KEY);
        }

        return array_filter(self::RESOURCE_RELATIONS, fn ($type) => $type === $filter, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Normalize database subtypes to 'database'.
     */
    private function normalizeType(string $type): string
    {
        if (in_array($type, ['postgresql', 'mysql', 'mariadb', 'mongodb', 'redis', 'keydb', 'dragonfly', 'clickhouse'])) {
            return 'database';
        }

        return $type;
    }
}
