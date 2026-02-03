<?php

namespace App\Actions\Transfer;

use App\Events\ResourceTransferStatusChanged;
use App\Models\ResourceTransfer;
use App\Models\Server;
use App\Services\Transfer\TransferStrategyFactory;
use Illuminate\Support\Facades\Log;

/**
 * Action to transfer database data between servers.
 *
 * Handles the full transfer workflow:
 * 1. Create dump on source server
 * 2. Transfer dump file to target server
 * 3. Restore dump on target server
 * 4. Clean up temporary files
 */
class TransferDatabaseDataAction
{
    /**
     * Execute the database transfer.
     *
     * @param  ResourceTransfer  $transfer  The transfer record
     * @param  mixed  $sourceDatabase  Source database model
     * @param  mixed  $targetDatabase  Target database model (for data_only mode)
     * @return bool Success status
     */
    public function execute(
        ResourceTransfer $transfer,
        mixed $sourceDatabase,
        mixed $targetDatabase = null
    ): bool {
        $strategy = TransferStrategyFactory::getStrategy($sourceDatabase);
        if (! $strategy) {
            $transfer->markAsFailed('Unsupported database type');

            return false;
        }

        $sourceServer = $this->getServer($sourceDatabase);
        $targetServer = $transfer->targetServer;
        $targetDb = $targetDatabase ?? $sourceDatabase;

        // Generate dump path
        $timestamp = now()->timestamp;
        $dumpFilename = "transfer-{$transfer->uuid}-{$timestamp}".$strategy->getDumpExtension();
        $sourceDumpPath = "/tmp/saturn-transfers/{$dumpFilename}";
        $targetDumpPath = "/tmp/saturn-transfers/{$dumpFilename}";

        try {
            // Step 1: Validate transfer
            $this->updateProgress($transfer, 5, 'Validating transfer...');

            $validation = $strategy->validateTransfer($sourceDatabase, $targetDb, $transfer);
            if (! $validation['valid']) {
                $transfer->markAsFailed(
                    'Validation failed: '.implode(', ', $validation['errors']),
                    ['validation_errors' => $validation['errors']]
                );

                return false;
            }

            // Step 2: Estimate size
            $this->updateProgress($transfer, 10, 'Estimating data size...');

            $estimatedSize = $strategy->estimateSize(
                $sourceDatabase,
                $sourceServer,
                $transfer->transfer_options
            );
            $transfer->update(['total_bytes' => $estimatedSize]);

            // Step 3: Create dump on source server
            $transfer->markAsTransferring('Creating dump on source server...');
            $this->updateProgress($transfer, 20, 'Creating database dump...');

            $dumpResult = $strategy->createDump(
                $sourceDatabase,
                $sourceServer,
                $sourceDumpPath,
                $transfer->transfer_options
            );

            if (! $dumpResult['success']) {
                $transfer->markAsFailed('Failed to create dump: '.$dumpResult['error']);
                $this->cleanup($strategy, $sourceServer, $sourceDumpPath);

                return false;
            }

            $transfer->update(['transferred_bytes' => $dumpResult['size']]);
            $transfer->appendLog("Dump created: {$dumpResult['size']} bytes");

            // Step 4: Transfer file between servers (if different)
            if ($sourceServer->id !== $targetServer->id) {
                $this->updateProgress($transfer, 50, 'Transferring dump to target server...');

                $transferSuccess = $this->transferFile(
                    $sourceServer,
                    $targetServer,
                    $sourceDumpPath,
                    $targetDumpPath,
                    $transfer
                );

                if (! $transferSuccess) {
                    $transfer->markAsFailed('Failed to transfer dump file between servers');
                    $this->cleanup($strategy, $sourceServer, $sourceDumpPath);

                    return false;
                }

                $transfer->appendLog('Dump transferred to target server');
            } else {
                // Same server, just use the same path
                $targetDumpPath = $sourceDumpPath;
            }

            // Step 5: Restore dump on target server
            $transfer->markAsRestoring('Restoring data on target server...');
            $this->updateProgress($transfer, 80, 'Restoring database...');

            $restoreResult = $strategy->restoreDump(
                $targetDb,
                $targetServer,
                $targetDumpPath,
                $transfer->transfer_options
            );

            if (! $restoreResult['success']) {
                $transfer->markAsFailed('Failed to restore dump: '.$restoreResult['error']);
                $this->cleanup($strategy, $sourceServer, $sourceDumpPath);
                $this->cleanup($strategy, $targetServer, $targetDumpPath);

                return false;
            }

            $transfer->appendLog('Data restored successfully');

            // Step 6: Cleanup
            $this->updateProgress($transfer, 95, 'Cleaning up...');
            $this->cleanup($strategy, $sourceServer, $sourceDumpPath);
            if ($sourceServer->id !== $targetServer->id) {
                $this->cleanup($strategy, $targetServer, $targetDumpPath);
            }

            // Step 7: Mark as completed
            $transfer->markAsCompleted();
            $this->broadcastStatus($transfer);

            return true;
        } catch (\Throwable $e) {
            Log::error('Database transfer failed', [
                'transfer_uuid' => $transfer->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $transfer->markAsFailed($e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Attempt cleanup
            try {
                $this->cleanup($strategy, $sourceServer, $sourceDumpPath);
                if ($sourceServer->id !== $targetServer->id) {
                    $this->cleanup($strategy, $targetServer, $targetDumpPath);
                }
            } catch (\Throwable $cleanupError) {
                Log::warning('Cleanup failed', ['error' => $cleanupError->getMessage()]);
            }

            return false;
        }
    }

    /**
     * Transfer file between servers using SCP.
     */
    private function transferFile(
        Server $sourceServer,
        Server $targetServer,
        string $sourcePath,
        string $targetPath,
        ResourceTransfer $transfer
    ): bool {
        try {
            // Ensure target directory exists
            instant_remote_process(
                ['mkdir -p '.dirname($targetPath)],
                $targetServer,
                false
            );

            // Get source server's private key for SCP
            $sourceKey = $sourceServer->privateKey;
            $targetKey = $targetServer->privateKey;

            // Use SCP to transfer file
            // We'll execute SCP from source server to target server
            $targetIp = $targetServer->ip;
            $targetUser = $targetServer->user;
            $targetPort = $targetServer->port ?? 22;

            // Create a temporary script to handle the transfer
            // Using rsync for better reliability and progress
            $rsyncCommand = "rsync -avz --progress -e 'ssh -p {$targetPort} -o StrictHostKeyChecking=no' {$sourcePath} {$targetUser}@{$targetIp}:{$targetPath}";

            instant_remote_process([$rsyncCommand], $sourceServer, true, false, 3600, disableMultiplexing: true);

            return true;
        } catch (\Throwable $e) {
            Log::error('File transfer failed', [
                'source' => $sourceServer->name,
                'target' => $targetServer->name,
                'error' => $e->getMessage(),
            ]);
            $transfer->appendLog('File transfer error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Clean up temporary files.
     */
    private function cleanup($strategy, Server $server, string $path): void
    {
        try {
            $strategy->cleanup($server, $path);
        } catch (\Throwable $e) {
            Log::warning('Cleanup warning', [
                'server' => $server->name,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get server from database model.
     */
    private function getServer(mixed $database): Server
    {
        // Try to get server from destination relationship
        if (method_exists($database, 'destination') && $database->destination) {
            return $database->destination->server;
        }

        throw new \RuntimeException('Could not determine source server');
    }

    /**
     * Update transfer progress and broadcast.
     */
    private function updateProgress(ResourceTransfer $transfer, int $progress, string $step): void
    {
        $transfer->updateProgress($progress, $step);
        $this->broadcastStatus($transfer);
    }

    /**
     * Broadcast transfer status via WebSocket.
     */
    private function broadcastStatus(ResourceTransfer $transfer): void
    {
        event(ResourceTransferStatusChanged::fromTransfer($transfer->fresh()));
    }
}
