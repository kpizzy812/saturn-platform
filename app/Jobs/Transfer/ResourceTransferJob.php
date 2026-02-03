<?php

namespace App\Jobs\Transfer;

use App\Actions\Transfer\TransferDatabaseDataAction;
use App\Events\ResourceTransferStatusChanged;
use App\Models\ResourceTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to handle resource transfer processing.
 *
 * This job orchestrates the transfer of database data between servers.
 */
class ResourceTransferJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $maxExceptions = 1;

    public $timeout = 7200; // 2 hours max

    /**
     * Create a new job instance.
     *
     * @param  int  $transferId  The ResourceTransfer ID
     * @param  int|null  $targetDatabaseId  The target database ID (for data_only mode)
     */
    public function __construct(
        public int $transferId,
        public ?int $targetDatabaseId = null
    ) {
        $this->onQueue('long');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $transfer = ResourceTransfer::find($this->transferId);

        if (! $transfer) {
            Log::warning('ResourceTransferJob: Transfer not found', ['id' => $this->transferId]);

            return;
        }

        // Check if transfer was cancelled
        if ($transfer->status === ResourceTransfer::STATUS_CANCELLED) {
            Log::info('ResourceTransferJob: Transfer was cancelled', ['uuid' => $transfer->uuid]);

            return;
        }

        // Mark as preparing
        $transfer->markAsPreparing('Loading source database...');
        $this->broadcastStatus($transfer);

        try {
            // Load source database
            $sourceDatabase = $transfer->source;

            if (! $sourceDatabase) {
                $transfer->markAsFailed('Source database not found');
                $this->broadcastStatus($transfer);

                return;
            }

            // Load target database for data_only mode
            $targetDatabase = null;
            if ($transfer->transfer_mode === ResourceTransfer::MODE_DATA_ONLY && $this->targetDatabaseId) {
                $targetDatabase = $this->loadTargetDatabase($transfer, $this->targetDatabaseId);
                if (! $targetDatabase) {
                    $transfer->markAsFailed('Target database not found');
                    $this->broadcastStatus($transfer);

                    return;
                }
            }

            $transfer->appendLog("Starting {$transfer->mode_label} transfer");
            $transfer->appendLog("Source: {$sourceDatabase->name}");
            if ($targetDatabase) {
                $transfer->appendLog("Target: {$targetDatabase->name}");
            }

            // Execute the transfer
            $action = new TransferDatabaseDataAction;
            $success = $action->execute($transfer, $sourceDatabase, $targetDatabase);

            if ($success) {
                Log::info('ResourceTransferJob: Transfer completed successfully', [
                    'uuid' => $transfer->uuid,
                ]);
            } else {
                Log::warning('ResourceTransferJob: Transfer failed', [
                    'uuid' => $transfer->uuid,
                    'error' => $transfer->error_message,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('ResourceTransferJob: Unhandled exception', [
                'uuid' => $transfer->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $transfer->markAsFailed($e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            $this->broadcastStatus($transfer->fresh());
        }
    }

    /**
     * Load target database based on source type.
     */
    private function loadTargetDatabase(ResourceTransfer $transfer, int $targetId): mixed
    {
        $sourceType = $transfer->source_type;

        return $sourceType::find($targetId);
    }

    /**
     * Broadcast transfer status via WebSocket.
     */
    private function broadcastStatus(ResourceTransfer $transfer): void
    {
        event(ResourceTransferStatusChanged::fromTransfer($transfer));
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::channel('scheduled-errors')->error('ResourceTransferJob permanently failed', [
            'job' => 'ResourceTransferJob',
            'transfer_id' => $this->transferId,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);

        $transfer = ResourceTransfer::find($this->transferId);

        if ($transfer) {
            $transfer->markAsFailed(
                'Job permanently failed: '.($exception?->getMessage() ?? 'Unknown error'),
                [
                    'exception' => $exception ? get_class($exception) : null,
                    'trace' => $exception?->getTraceAsString(),
                ]
            );

            $this->broadcastStatus($transfer);
        }
    }
}
