<?php

namespace App\Actions\Migration;

use App\Models\Environment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Batch migration action: migrates multiple resources in dependency order.
 * Order: databases → services → applications (dependencies first).
 * Within each group, migrations run in parallel (separate jobs).
 */
class BatchMigrateAction
{
    use AsAction;

    /**
     * Migrate multiple resources in a single batch.
     *
     * @param  array<array{type: string, resource: Model}>  $resources  Ordered list of resources
     * @return array{success: bool, migrations: array, errors: array}
     */
    public function handle(
        array $resources,
        Environment $targetEnvironment,
        Server $targetServer,
        User $requestedBy,
        array $options = []
    ): array {
        $migrations = [];
        $errors = [];

        // Group by type for dependency ordering
        $grouped = ['database' => [], 'service' => [], 'application' => []];

        foreach ($resources as $item) {
            $type = $item['type'];
            if (isset($grouped[$type])) {
                $grouped[$type][] = $item['resource'];
            }
        }

        // Process in dependency order: databases → services → applications
        foreach (['database', 'service', 'application'] as $type) {
            foreach ($grouped[$type] as $resource) {
                $result = MigrateResourceAction::run(
                    $resource,
                    $targetEnvironment,
                    $targetServer,
                    $requestedBy,
                    $options
                );

                if ($result['success']) {
                    $migrations[] = [
                        'type' => $type,
                        'name' => $resource->name ?? 'unnamed',
                        'migration' => $result['migration'],
                        'requires_approval' => $result['requires_approval'] ?? false,
                    ];
                } else {
                    $errors[] = [
                        'type' => $type,
                        'name' => $resource->name ?? 'unnamed',
                        'error' => $result['error'] ?? 'Unknown error',
                    ];
                }
            }
        }

        return [
            'success' => empty($errors),
            'migrations' => $migrations,
            'errors' => $errors,
        ];
    }
}
