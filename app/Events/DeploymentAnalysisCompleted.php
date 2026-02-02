<?php

namespace App\Events;

use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentLogAnalysis;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentAnalysisCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ApplicationDeploymentQueue $deployment,
        public DeploymentLogAnalysis $analysis,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('deployment.'.$this->deployment->deployment_uuid.'.analysis'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'analysis.completed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'deployment_id' => $this->deployment->id,
            'deployment_uuid' => $this->deployment->deployment_uuid,
            'analysis' => [
                'id' => $this->analysis->id,
                'root_cause' => $this->analysis->root_cause,
                'root_cause_details' => $this->analysis->root_cause_details,
                'solution' => $this->analysis->solution,
                'prevention' => $this->analysis->prevention,
                'error_category' => $this->analysis->error_category,
                'severity' => $this->analysis->severity,
                'confidence' => $this->analysis->confidence,
                'provider' => $this->analysis->provider,
                'model' => $this->analysis->model,
                'status' => $this->analysis->status,
                'created_at' => $this->analysis->created_at->toISOString(),
            ],
        ];
    }
}
