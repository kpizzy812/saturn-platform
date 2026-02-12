<?php

namespace App\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Models\Environment;
use App\Models\ResourceLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Clone ResourceLinks from source environment to target environment
 * after a resource has been cloned.
 *
 * For each ResourceLink where the source resource is involved (as source or target),
 * creates the equivalent link in the target environment if both sides exist.
 */
class CloneResourceLinksAction
{
    use AsAction;
    use ResourceConfigFields;

    /**
     * Clone resource links for a cloned resource.
     *
     * @param  Model  $source  The original resource in source environment
     * @param  Model  $target  The cloned resource in target environment
     * @param  Environment  $sourceEnv  The source environment
     * @param  Environment  $targetEnv  The target environment
     * @return array<int, array{source: string, target: string, inject_as: string|null}> Created links
     */
    public function handle(Model $source, Model $target, Environment $sourceEnv, Environment $targetEnv): array
    {
        $createdLinks = [];
        $processedPairs = [];

        // Get outgoing links (source = original resource)
        $outgoingLinks = ResourceLink::where('source_type', get_class($source))
            ->where('source_id', $source->getKey())
            ->where('environment_id', $sourceEnv->getKey())
            ->get();

        foreach ($outgoingLinks as $link) {
            $targetSideResource = $this->findCorrespondingResource(
                $link->target_type,
                (int) $link->target_id,
                $targetEnv
            );

            if (! $targetSideResource) {
                continue;
            }

            $pairKey = get_class($target).':'.$target->getKey().'->'.$link->target_type.':'.$targetSideResource->getKey();
            if (isset($processedPairs[$pairKey])) {
                continue;
            }

            $created = $this->createLink(
                $target,
                $targetSideResource,
                $targetEnv,
                $link->inject_as,
                (bool) $link->auto_inject,
                (bool) $link->use_external_url,
            );

            if ($created) {
                $processedPairs[$pairKey] = true;
                $createdLinks[] = [
                    'source' => $target->getAttribute('name') ?? class_basename($target),
                    'target' => $targetSideResource->getAttribute('name') ?? class_basename($targetSideResource),
                    'inject_as' => $link->inject_as,
                ];
            }
        }

        // Get incoming links (target = original resource)
        $incomingLinks = ResourceLink::where('target_type', get_class($source))
            ->where('target_id', $source->getKey())
            ->where('environment_id', $sourceEnv->getKey())
            ->get();

        foreach ($incomingLinks as $link) {
            $sourceSideResource = $this->findCorrespondingResource(
                $link->source_type,
                (int) $link->source_id,
                $targetEnv
            );

            if (! $sourceSideResource) {
                continue;
            }

            $pairKey = $link->source_type.':'.$sourceSideResource->getKey().'->'.get_class($target).':'.$target->getKey();
            if (isset($processedPairs[$pairKey])) {
                continue;
            }

            $created = $this->createLink(
                $sourceSideResource,
                $target,
                $targetEnv,
                $link->inject_as,
                (bool) $link->auto_inject,
                (bool) $link->use_external_url,
            );

            if ($created) {
                $processedPairs[$pairKey] = true;
                $createdLinks[] = [
                    'source' => $sourceSideResource->getAttribute('name') ?? class_basename($sourceSideResource),
                    'target' => $target->getAttribute('name') ?? class_basename($target),
                    'inject_as' => $link->inject_as,
                ];
            }
        }

        return $createdLinks;
    }

    /**
     * Find the corresponding resource in the target environment by name.
     * Looks up the original resource by ID, then finds by name in target env.
     */
    protected function findCorrespondingResource(string $type, int $id, Environment $targetEnv): ?Model
    {
        // Load the original resource to get its name
        /** @var Model|null $original */
        $original = $type::find($id);
        if (! $original || ! $original->getAttribute('name')) {
            return null;
        }

        $name = $original->getAttribute('name');

        // Check if it's an Application
        if ($type === \App\Models\Application::class) {
            return $targetEnv->applications()->where('name', $name)->first();
        }

        // Check if it's a Service
        if ($type === \App\Models\Service::class) {
            return $targetEnv->services()->where('name', $name)->first();
        }

        // Check databases
        $relationMethod = $this->getDatabaseRelationMethod($type);
        if ($relationMethod && method_exists($targetEnv, $relationMethod)) {
            return $targetEnv->$relationMethod()->where('name', $name)->first();
        }

        return null;
    }

    /**
     * Create a ResourceLink in the target environment, avoiding duplicates.
     */
    protected function createLink(
        Model $source,
        Model $target,
        Environment $targetEnv,
        ?string $injectAs,
        bool $autoInject,
        bool $useExternalUrl,
    ): ?ResourceLink {
        // Check for existing link to avoid unique constraint violations
        $existing = ResourceLink::where('source_type', get_class($source))
            ->where('source_id', $source->getKey())
            ->where('target_type', get_class($target))
            ->where('target_id', $target->getKey())
            ->where('environment_id', $targetEnv->getKey())
            ->first();

        if ($existing) {
            return null;
        }

        try {
            return ResourceLink::create([
                'source_type' => get_class($source),
                'source_id' => $source->getKey(),
                'target_type' => get_class($target),
                'target_id' => $target->getKey(),
                'environment_id' => $targetEnv->getKey(),
                'inject_as' => $injectAs,
                'auto_inject' => $autoInject,
                'use_external_url' => $useExternalUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to clone resource link', [
                'source' => get_class($source).':'.$source->getKey(),
                'target' => get_class($target).':'.$target->getKey(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
