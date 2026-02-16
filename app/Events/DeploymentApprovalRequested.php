<?php

namespace App\Events;

use App\Models\DeploymentApproval;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a deployment approval is requested.
 * Broadcasts to team channel so admins/owners can see pending approvals.
 */
class DeploymentApprovalRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $approvalId,
        public string $approvalUuid,
        public int $deploymentId,
        public string $deploymentUuid,
        public int $applicationId,
        public string $applicationName,
        public string $environmentName,
        public string $projectName,
        public string $requestedByEmail,
        public ?int $teamId = null
    ) {}

    /**
     * Create event from DeploymentApproval model.
     */
    public static function fromApproval(DeploymentApproval $approval): self
    {
        $deployment = $approval->deployment;
        $application = $deployment->application;
        $environment = $application?->environment;
        $project = $environment?->project;
        $team = $project?->team;

        return new self(
            approvalId: $approval->id,
            approvalUuid: $approval->uuid,
            deploymentId: $deployment->id ?? 0,
            deploymentUuid: $deployment->deployment_uuid ?? '',
            applicationId: $application?->id ?? 0,
            applicationName: $application?->name ?? 'Unknown',
            environmentName: $environment?->name ?? 'Unknown',
            projectName: $project?->name ?? 'Unknown',
            requestedByEmail: $approval->requestedBy->email ?? 'Unknown',
            teamId: $team?->id
        );
    }

    public function broadcastWith(): array
    {
        return [
            'approvalId' => $this->approvalId,
            'approvalUuid' => $this->approvalUuid,
            'deploymentId' => $this->deploymentId,
            'deploymentUuid' => $this->deploymentUuid,
            'applicationId' => $this->applicationId,
            'applicationName' => $this->applicationName,
            'environmentName' => $this->environmentName,
            'projectName' => $this->projectName,
            'requestedByEmail' => $this->requestedByEmail,
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

    public function broadcastAs(): string
    {
        return 'deployment.approval.requested';
    }
}
