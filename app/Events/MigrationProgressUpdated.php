<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast migration progress updates via WebSocket.
 * Sent on both team channel (for list views) and migration-specific channel (for detail view).
 */
class MigrationProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $migrationUuid,
        public string $status,
        public int $progress,
        public ?string $currentStep = null,
        public ?string $logEntry = null,
        public ?string $errorMessage = null,
        public ?int $teamId = null
    ) {}

    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->migrationUuid,
            'status' => $this->status,
            'progress' => $this->progress,
            'current_step' => $this->currentStep,
            'log_entry' => $this->logEntry,
            'error_message' => $this->errorMessage,
        ];
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("migration.{$this->migrationUuid}"),
        ];

        if ($this->teamId) {
            $channels[] = new PrivateChannel("team.{$this->teamId}");
        }

        return $channels;
    }
}
