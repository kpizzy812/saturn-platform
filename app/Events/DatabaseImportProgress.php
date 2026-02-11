<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DatabaseImportProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $teamId,
        public string $databaseUuid,
        public string $importUuid,
        public string $status,
        public int $progress,
        public string $message = '',
        public ?string $error = null,
    ) {}

    public function broadcastWith(): array
    {
        return [
            'databaseUuid' => $this->databaseUuid,
            'importUuid' => $this->importUuid,
            'status' => $this->status,
            'progress' => $this->progress,
            'message' => $this->message,
            'error' => $this->error,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("team.{$this->teamId}"),
        ];
    }
}
