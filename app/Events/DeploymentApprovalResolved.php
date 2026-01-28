<?php

namespace App\Events;

use App\Models\DeploymentApproval;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a deployment approval is resolved (approved or rejected).
 * Broadcasts to team channel and notifies the requester.
 */
class DeploymentApprovalResolved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $approvalId,
        public string $approvalUuid,
        public string $status,
        public int $deploymentId,
        public string $deploymentUuid,
        public int $applicationId,
        public string $applicationName,
        public string $environmentName,
        public string $projectName,
        public string $resolvedByEmail,
        public ?string $comment,
        public int $requestedById,
        public ?int $teamId = null
    ) {}

    /**
     * Create event from DeploymentApproval model.
     */
    public static function fromApproval(DeploymentApproval $approval): self
    {
        $deployment = $approval->deployment;
        $application = $deployment?->application;
        $environment = $application?->environment;
        $project = $environment?->project;
        $team = $project?->team;

        return new self(
            approvalId: $approval->id,
            approvalUuid: $approval->uuid,
            status: $approval->status,
            deploymentId: $deployment?->id ?? 0,
            deploymentUuid: $deployment?->deployment_uuid ?? '',
            applicationId: $application?->id ?? 0,
            applicationName: $application?->name ?? 'Unknown',
            environmentName: $environment?->name ?? 'Unknown',
            projectName: $project?->name ?? 'Unknown',
            resolvedByEmail: $approval->approvedBy?->email ?? 'Unknown',
            comment: $approval->comment,
            requestedById: $approval->requested_by,
            teamId: $team?->id
        );
    }

    public function broadcastWith(): array
    {
        return [
            'approvalId' => $this->approvalId,
            'approvalUuid' => $this->approvalUuid,
            'status' => $this->status,
            'deploymentId' => $this->deploymentId,
            'deploymentUuid' => $this->deploymentUuid,
            'applicationId' => $this->applicationId,
            'applicationName' => $this->applicationName,
            'environmentName' => $this->environmentName,
            'projectName' => $this->projectName,
            'resolvedByEmail' => $this->resolvedByEmail,
            'comment' => $this->comment,
        ];
    }

    public function broadcastOn(): array
    {
        if (is_null($this->teamId)) {
            return [];
        }

        return [
            new PrivateChannel("team.{$this->teamId}"),
            // Also broadcast to requester's private channel
            new PrivateChannel("user.{$this->requestedById}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'deployment.approval.resolved';
    }
}
