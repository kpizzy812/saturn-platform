<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Visus\Cuid2\Cuid2;

/**
 * Model for deployment approval workflow.
 * Tracks approval requests for production deployments.
 */
class DeploymentApproval extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($approval) {
            if (empty($approval->uuid)) {
                $approval->uuid = (string) new Cuid2;
            }
        });
    }

    /**
     * Get the deployment queue entry this approval is for.
     */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(ApplicationDeploymentQueue::class, 'application_deployment_queue_id');
    }

    /**
     * Get the user who requested the deployment.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved/rejected the deployment.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if approval is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if approval was approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if approval was rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Approve the deployment request.
     */
    public function approve(User $approver, ?string $comment = null): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'comment' => $comment,
            'decided_at' => Carbon::now(),
        ]);
    }

    /**
     * Reject the deployment request.
     */
    public function reject(User $approver, ?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'comment' => $reason,
            'decided_at' => Carbon::now(),
        ]);
    }

    /**
     * Get pending approvals for a project.
     */
    public static function pendingForProject(Project $project)
    {
        return static::where('status', 'pending')
            ->whereHas('deployment.application.environment', function ($query) use ($project) {
                $query->where('project_id', $project->id);
            })
            ->with(['deployment.application', 'requestedBy'])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get pending approvals for a user who can approve them.
     */
    public static function pendingForApprover(User $user)
    {
        // Get projects where user is admin or owner
        $projectIds = $user->projectMemberships()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->pluck('projects.id');

        // Also include projects from teams where user is admin/owner
        $teamIds = $user->teams()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->pluck('teams.id');

        $teamProjectIds = Project::whereIn('team_id', $teamIds)->pluck('id');

        $allProjectIds = $projectIds->merge($teamProjectIds)->unique();

        return static::where('status', 'pending')
            ->whereHas('deployment.application.environment', function ($query) use ($allProjectIds) {
                $query->whereIn('project_id', $allProjectIds);
            })
            ->with(['deployment.application.environment.project', 'requestedBy'])
            ->orderBy('created_at', 'desc');
    }
}
