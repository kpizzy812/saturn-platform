<?php

namespace App\Actions\Transfer;

use App\Jobs\Transfer\ResourceTransferJob;
use App\Models\Environment;
use App\Models\ResourceTransfer;
use App\Models\Server;
use App\Models\User;
use App\Services\Transfer\TransferStrategyFactory;
use Illuminate\Support\Facades\Gate;

/**
 * Action to create a new resource transfer.
 *
 * Validates input, creates the transfer record, and dispatches the transfer job.
 */
class CreateTransferAction
{
    /**
     * Create a new database transfer.
     *
     * @param  mixed  $sourceDatabase  The source database model
     * @param  Environment  $targetEnvironment  The target environment
     * @param  Server  $targetServer  The target server
     * @param  string  $transferMode  Transfer mode (clone, data_only, partial)
     * @param  array|null  $transferOptions  Options for partial transfer
     * @param  string|null  $existingTargetUuid  UUID of existing target DB (for data_only mode)
     * @param  User|null  $user  The user initiating the transfer
     * @return array Result with success status and transfer or error
     */
    public function execute(
        mixed $sourceDatabase,
        Environment $targetEnvironment,
        Server $targetServer,
        string $transferMode = ResourceTransfer::MODE_CLONE,
        ?array $transferOptions = null,
        ?string $existingTargetUuid = null,
        ?User $user = null
    ): array {
        // Validate database type support
        if (! TransferStrategyFactory::supportsTransfer($sourceDatabase)) {
            return [
                'success' => false,
                'error' => 'This database type does not support transfers',
            ];
        }

        // Validate transfer mode
        if (! in_array($transferMode, [
            ResourceTransfer::MODE_CLONE,
            ResourceTransfer::MODE_DATA_ONLY,
            ResourceTransfer::MODE_PARTIAL,
        ])) {
            return [
                'success' => false,
                'error' => 'Invalid transfer mode',
            ];
        }

        // Validate user authorization
        $user = $user ?? auth()->user();
        if (! $user) {
            return [
                'success' => false,
                'error' => 'User authentication required',
            ];
        }

        // Check if user has access to source database
        if (Gate::forUser($user)->denies('view', $sourceDatabase)) {
            return [
                'success' => false,
                'error' => 'You do not have permission to access this database',
            ];
        }

        // Check if user has access to target environment
        if (Gate::forUser($user)->denies('view', $targetEnvironment)) {
            return [
                'success' => false,
                'error' => 'You do not have permission to access the target environment',
            ];
        }

        // Get team ID
        $team = $sourceDatabase->team() ?? $user->currentTeam();
        if (! $team) {
            return [
                'success' => false,
                'error' => 'Could not determine team',
            ];
        }

        // Validate data_only mode requirements
        $targetDatabase = null;
        if ($transferMode === ResourceTransfer::MODE_DATA_ONLY) {
            if (! $existingTargetUuid) {
                return [
                    'success' => false,
                    'error' => 'Target database UUID is required for data_only mode',
                ];
            }

            $targetDatabase = $this->findDatabaseByUuid($existingTargetUuid);
            if (! $targetDatabase) {
                return [
                    'success' => false,
                    'error' => 'Target database not found',
                ];
            }

            // Verify target database type matches source
            if (get_class($targetDatabase) !== get_class($sourceDatabase)) {
                return [
                    'success' => false,
                    'error' => 'Target database type must match source database type',
                ];
            }

            // Verify target is in the specified environment
            if ($targetDatabase->environment_id !== $targetEnvironment->id) {
                return [
                    'success' => false,
                    'error' => 'Target database must be in the specified environment',
                ];
            }
        }

        // Validate target server is functional
        if (! $targetServer->isFunctional()) {
            return [
                'success' => false,
                'error' => 'Target server is not functional',
            ];
        }

        // Validate partial mode requirements
        if ($transferMode === ResourceTransfer::MODE_PARTIAL) {
            if (empty($transferOptions)) {
                return [
                    'success' => false,
                    'error' => 'Transfer options are required for partial mode',
                ];
            }

            // Check that at least one option is specified
            $hasOptions = ! empty($transferOptions['tables'])
                || ! empty($transferOptions['collections'])
                || ! empty($transferOptions['key_patterns']);

            if (! $hasOptions) {
                return [
                    'success' => false,
                    'error' => 'At least one table, collection, or key pattern must be specified',
                ];
            }
        }

        // Check for existing in-progress transfers for same source
        $existingTransfer = ResourceTransfer::where('source_type', get_class($sourceDatabase))
            ->where('source_id', $sourceDatabase->id)
            ->inProgress()
            ->first();

        if ($existingTransfer) {
            return [
                'success' => false,
                'error' => 'A transfer is already in progress for this database',
                'existing_transfer' => $existingTransfer,
            ];
        }

        // Create transfer record
        $transfer = ResourceTransfer::create([
            'source_type' => get_class($sourceDatabase),
            'source_id' => $sourceDatabase->id,
            'target_environment_id' => $targetEnvironment->id,
            'target_server_id' => $targetServer->id,
            'transfer_mode' => $transferMode,
            'transfer_options' => $transferOptions,
            'existing_target_uuid' => $existingTargetUuid,
            'status' => ResourceTransfer::STATUS_PENDING,
            'progress' => 0,
            'current_step' => 'Queued for processing',
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        // Dispatch the transfer job
        dispatch(new ResourceTransferJob($transfer->id, $targetDatabase?->id));

        return [
            'success' => true,
            'transfer' => $transfer,
        ];
    }

    /**
     * Find a database by UUID across all database types.
     */
    private function findDatabaseByUuid(string $uuid): mixed
    {
        return queryDatabaseByUuidWithinTeam($uuid, currentTeam()->id);
    }
}
