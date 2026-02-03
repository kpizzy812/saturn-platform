<?php

namespace App\Events;

use App\Models\ResourceTransfer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResourceTransferStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $transferId;

    public string $uuid;

    public string $status;

    public int $progress;

    public ?string $currentStep;

    public ?int $transferredBytes;

    public ?int $totalBytes;

    public ?string $errorMessage;

    public ?int $teamId = null;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int|ResourceTransfer|array $data,
        ?string $status = null,
        ?int $progress = null,
        ?string $currentStep = null,
        ?int $teamId = null
    ) {
        if ($data instanceof ResourceTransfer) {
            $this->transferId = $data->id;
            $this->uuid = $data->uuid;
            $this->status = $data->status;
            $this->progress = $data->progress;
            $this->currentStep = $data->current_step;
            $this->transferredBytes = $data->transferred_bytes;
            $this->totalBytes = $data->total_bytes;
            $this->errorMessage = $data->error_message;
            $this->teamId = $data->team_id;
        } elseif (is_array($data)) {
            $this->transferId = $data['transferId'];
            $this->uuid = $data['uuid'] ?? '';
            $this->status = $data['status'] ?? 'unknown';
            $this->progress = $data['progress'] ?? 0;
            $this->currentStep = $data['currentStep'] ?? null;
            $this->transferredBytes = $data['transferredBytes'] ?? null;
            $this->totalBytes = $data['totalBytes'] ?? null;
            $this->errorMessage = $data['errorMessage'] ?? null;
            $this->teamId = $data['teamId'] ?? null;
        } else {
            $this->transferId = $data;
            $this->uuid = '';
            $this->status = $status ?? 'unknown';
            $this->progress = $progress ?? 0;
            $this->currentStep = $currentStep;
            $this->transferredBytes = null;
            $this->totalBytes = null;
            $this->errorMessage = null;
            $this->teamId = $teamId;
        }

        // Fallback to current team if not provided
        if (is_null($this->teamId) && auth()->check() && auth()->user()->currentTeam()) {
            $this->teamId = auth()->user()->currentTeam()->id;
        }
    }

    /**
     * Create event from ResourceTransfer model.
     */
    public static function fromTransfer(ResourceTransfer $transfer): self
    {
        return new self($transfer);
    }

    public function broadcastWith(): array
    {
        return [
            'transferId' => $this->transferId,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'progress' => $this->progress,
            'currentStep' => $this->currentStep,
            'transferredBytes' => $this->transferredBytes,
            'totalBytes' => $this->totalBytes,
            'errorMessage' => $this->errorMessage,
        ];
    }

    public function broadcastOn(): array
    {
        if (is_null($this->teamId)) {
            return [];
        }

        return [
            new PrivateChannel("team.{$this->teamId}"),
        ];
    }
}
