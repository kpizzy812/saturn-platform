<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $applicationId;

    public string $status;

    public ?int $teamId = null;

    /**
     * Create a new event instance.
     *
     * @param  int|array  $data  Either applicationId (int) or array with applicationId, status, teamId
     * @param  string|null  $status  Status string (optional if $data is array)
     * @param  int|null  $teamId  Team ID (optional)
     */
    public function __construct(int|array $data, ?string $status = null, ?int $teamId = null)
    {
        // Support both array and separate arguments for flexibility
        if (is_array($data)) {
            $this->applicationId = $data['applicationId'];
            $this->status = $data['status'] ?? 'unknown';
            $this->teamId = $data['teamId'] ?? null;
        } else {
            $this->applicationId = $data;
            $this->status = $status ?? 'unknown';
            $this->teamId = $teamId;
        }

        // Fallback to current team if not provided
        if (is_null($this->teamId) && auth()->check() && auth()->user()->currentTeam()) {
            $this->teamId = auth()->user()->currentTeam()->id;
        }
    }

    public function broadcastWith(): array
    {
        return [
            'applicationId' => $this->applicationId,
            'status' => $this->status,
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
