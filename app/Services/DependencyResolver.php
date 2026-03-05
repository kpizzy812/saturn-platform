<?php

namespace App\Services;

use App\Models\Environment;
use InvalidArgumentException;

/**
 * Resolves resource startup order within an environment using topological sort.
 *
 * Databases without depends_on always start first (tier 0).
 * Returns ordered tiers: each tier can start in parallel, but must wait for previous tiers.
 */
class DependencyResolver
{
    /**
     * Resolve dependency order for all resources in an environment.
     *
     * @return array<int, array<int, array{uuid: string, type: string, name: string}>> Ordered tiers
     *
     * @throws InvalidArgumentException If circular dependency detected
     */
    public function resolve(Environment $environment): array
    {
        $resources = $this->collectResources($environment);

        if (empty($resources)) {
            return [];
        }

        return $this->topologicalSort($resources);
    }

    /**
     * Get a flat ordered list of resource UUIDs (respecting dependency order).
     *
     * @return array<int, string>
     */
    public function resolveFlat(Environment $environment): array
    {
        $tiers = $this->resolve($environment);
        $result = [];

        foreach ($tiers as $tier) {
            foreach ($tier as $resource) {
                $result[] = $resource['uuid'];
            }
        }

        return $result;
    }

    /**
     * Validate that depends_on references are valid (no missing, no self-reference).
     *
     * @return array<int, string> List of validation errors
     */
    public function validate(Environment $environment): array
    {
        $resources = $this->collectResources($environment);
        $errors = [];
        $validUuids = array_keys($resources);

        foreach ($resources as $uuid => $resource) {
            foreach ($resource['depends_on'] as $depUuid) {
                if ($depUuid === $uuid) {
                    $errors[] = "Resource '{$resource['name']}' depends on itself.";
                } elseif (! in_array($depUuid, $validUuids, true)) {
                    $errors[] = "Resource '{$resource['name']}' depends on unknown UUID '{$depUuid}'.";
                }
            }
        }

        // Check for circular dependencies
        try {
            $this->topologicalSort($resources);
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Collect all resources from environment into a normalized structure.
     *
     * @return array<string, array{uuid: string, type: string, name: string, depends_on: array<int, string>}>
     */
    private function collectResources(Environment $environment): array
    {
        $resources = [];

        foreach ($environment->applications as $app) {
            $resources[$app->uuid] = [
                'uuid' => $app->uuid,
                'type' => 'application',
                'name' => $app->name,
                'depends_on' => $app->depends_on ?? [],
            ];
        }

        // Databases: no depends_on field, always tier 0
        $databases = $environment->databases();
        foreach ($databases as $db) {
            $resources[$db->uuid] = [
                'uuid' => $db->uuid,
                'type' => 'database',
                'name' => $db->name,
                'depends_on' => [],
            ];
        }

        foreach ($environment->services as $service) {
            $resources[$service->uuid] = [
                'uuid' => $service->uuid,
                'type' => 'service',
                'name' => $service->name,
                'depends_on' => $service->depends_on ?? [],
            ];
        }

        return $resources;
    }

    /**
     * Kahn's algorithm for topological sort, returning resources grouped by tier.
     *
     * @param  array<string, array{uuid: string, type: string, name: string, depends_on: array<int, string>}>  $resources
     * @return array<int, array<int, array{uuid: string, type: string, name: string}>>
     *
     * @throws InvalidArgumentException
     */
    private function topologicalSort(array $resources): array
    {
        // Build adjacency and in-degree maps
        $inDegree = [];
        $dependents = []; // uuid => [uuids that depend on it]
        $validUuids = array_keys($resources);

        foreach ($resources as $uuid => $resource) {
            $inDegree[$uuid] = 0;
            $dependents[$uuid] = [];
        }

        foreach ($resources as $uuid => $resource) {
            foreach ($resource['depends_on'] as $depUuid) {
                // Skip invalid references (already caught by validate())
                if (! isset($resources[$depUuid])) {
                    continue;
                }
                $inDegree[$uuid]++;
                $dependents[$depUuid][] = $uuid;
            }
        }

        // Start with nodes that have no dependencies
        $tiers = [];
        $queue = [];
        $processed = 0;

        foreach ($inDegree as $uuid => $degree) {
            if ($degree === 0) {
                $queue[] = $uuid;
            }
        }

        while (! empty($queue)) {
            $tier = [];
            $nextQueue = [];

            foreach ($queue as $uuid) {
                $r = $resources[$uuid];
                $tier[] = [
                    'uuid' => $r['uuid'],
                    'type' => $r['type'],
                    'name' => $r['name'],
                ];
                $processed++;

                // Reduce in-degree for dependents
                foreach ($dependents[$uuid] as $depUuid) {
                    $inDegree[$depUuid]--;
                    if ($inDegree[$depUuid] === 0) {
                        $nextQueue[] = $depUuid;
                    }
                }
            }

            if (! empty($tier)) {
                // Sort within tier: databases first, then services, then applications
                usort($tier, function ($a, $b) {
                    $order = ['database' => 0, 'service' => 1, 'application' => 2];

                    return ($order[$a['type']] ?? 3) <=> ($order[$b['type']] ?? 3);
                });
                $tiers[] = $tier;
            }

            $queue = $nextQueue;
        }

        if ($processed !== count($resources)) {
            // Find resources involved in cycles
            $cycled = [];
            foreach ($inDegree as $uuid => $degree) {
                if ($degree > 0) {
                    $cycled[] = $resources[$uuid]['name'];
                }
            }
            throw new InvalidArgumentException(
                'Circular dependency detected between: '.implode(', ', $cycled)
            );
        }

        return $tiers;
    }
}
