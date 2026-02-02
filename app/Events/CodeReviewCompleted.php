<?php

namespace App\Events;

use App\Models\ApplicationDeploymentQueue;
use App\Models\CodeReview;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when code review is completed.
 *
 * Used to update the frontend in real-time when code review finishes.
 */
class CodeReviewCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ApplicationDeploymentQueue $deployment,
        public CodeReview $review,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('deployment.'.$this->deployment->deployment_uuid.'.code-review'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'code-review.completed';
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
            'code_review' => [
                'id' => $this->review->id,
                'status' => $this->review->status,
                'status_label' => $this->review->status_label,
                'status_color' => $this->review->status_color,
                'violations_count' => $this->review->violations_count,
                'critical_count' => $this->review->critical_count,
                'files_analyzed_count' => count($this->review->files_analyzed ?? []),
                'duration_ms' => $this->review->duration_ms,
                'commit_sha' => $this->review->commit_sha,
                'has_violations' => $this->review->hasViolations(),
                'has_critical' => $this->review->hasCriticalViolations(),
                'llm_provider' => $this->review->llm_provider,
                'created_at' => $this->review->created_at->toISOString(),
            ],
        ];
    }
}
