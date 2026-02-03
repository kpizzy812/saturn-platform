<?php

namespace App\Actions\Migration;

use App\Models\Environment;
use App\Services\Authorization\MigrationAuthorizationService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action to validate that a migration follows the allowed chain.
 * Valid chain: development -> uat -> production (no skipping)
 */
class ValidateMigrationChainAction
{
    use AsAction;

    /**
     * Validate migration chain between environments.
     *
     * @return array{valid: bool, error?: string, source_type: string, target_type: string}
     */
    public function handle(Environment $sourceEnv, Environment $targetEnv): array
    {
        $sourceType = $sourceEnv->type ?? 'development';
        $targetType = $targetEnv->type ?? 'development';

        // Same environment check
        if ($sourceEnv->id === $targetEnv->id) {
            return [
                'valid' => false,
                'error' => 'Cannot migrate to the same environment.',
                'source_type' => $sourceType,
                'target_type' => $targetType,
            ];
        }

        // Must be in the same project
        if ($sourceEnv->project_id !== $targetEnv->project_id) {
            return [
                'valid' => false,
                'error' => 'Source and target environments must be in the same project.',
                'source_type' => $sourceType,
                'target_type' => $targetType,
            ];
        }

        // Validate chain using authorization service
        $authService = app(MigrationAuthorizationService::class);

        if (! $authService->isValidMigrationChain($sourceEnv, $targetEnv)) {
            $nextType = $authService->getNextEnvironmentType($sourceEnv);

            if ($nextType === null) {
                return [
                    'valid' => false,
                    'error' => "Cannot migrate from {$sourceType}: this is the final environment in the chain.",
                    'source_type' => $sourceType,
                    'target_type' => $targetType,
                ];
            }

            return [
                'valid' => false,
                'error' => "Invalid migration chain. From {$sourceType} you can only migrate to {$nextType}, not {$targetType}.",
                'source_type' => $sourceType,
                'target_type' => $targetType,
            ];
        }

        return [
            'valid' => true,
            'source_type' => $sourceType,
            'target_type' => $targetType,
        ];
    }

    /**
     * Get the available target environments for migration from a source.
     *
     * @return \Illuminate\Support\Collection<Environment>
     */
    public function getAvailableTargets(Environment $sourceEnv)
    {
        $authService = app(MigrationAuthorizationService::class);
        $nextType = $authService->getNextEnvironmentType($sourceEnv);

        if ($nextType === null) {
            return collect();
        }

        // Get environments of the next type in the same project
        return $sourceEnv->project
            ->environments()
            ->where('type', $nextType)
            ->get();
    }
}
