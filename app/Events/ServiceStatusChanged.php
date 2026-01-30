<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a service's status changes.
 * Used for real-time status updates on the frontend.
 *
 * Supports two modes:
 * 1. Legacy mode: dispatch(teamId) - just signals that something changed
 * 2. New mode: dispatch(serviceId, status, teamId) - specific service status update
 */
class ServiceStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?int $serviceId = null;

    public ?string $status = null;

    public ?int $teamId = null;

    /**
     * Create a new event instance.
     *
     * @param  int|array  $data  Either teamId (legacy) or serviceId, or array with serviceId, status, teamId
     * @param  string|null  $status  Status string (optional)
     * @param  int|null  $teamId  Team ID (optional)
     */
    public function __construct(int|array $data, ?string $status = null, ?int $teamId = null)
    {
        if (is_array($data)) {
            // Array mode: { serviceId, status, teamId }
            $this->serviceId = $data['serviceId'] ?? null;
            $this->status = $data['status'] ?? null;
            $this->teamId = $data['teamId'] ?? null;
        } elseif ($status === null && $teamId === null) {
            // Legacy mode: dispatch(teamId) - just the team ID
            $this->teamId = $data;
        } else {
            // New mode: dispatch(serviceId, status, teamId)
            $this->serviceId = $data;
            $this->status = $status;
            $this->teamId = $teamId;
        }

        // Fallback to current team if not provided
        if (is_null($this->teamId) && auth()->check() && auth()->user()->currentTeam()) {
            $this->teamId = auth()->user()->currentTeam()->id;
        }
    }

    public function broadcastWith(): array
    {
        $data = ['timestamp' => now()->toIso8601String()];

        // Include service-specific data if available
        if ($this->serviceId !== null) {
            $data['serviceId'] = $this->serviceId;
        }
        if ($this->status !== null) {
            $data['status'] = $this->status;
        }

        return $data;
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
