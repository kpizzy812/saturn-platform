<?php

/**
 * Deployment Approvals routes for Saturn Platform
 *
 * These routes handle viewing and managing pending deployment approvals.
 * All routes require authentication and email verification.
 */

use App\Actions\Deployment\ApproveDeploymentAction;
use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Approvals list page
Route::get('/approvals', function () {
    return Inertia::render('Approvals/Index');
})->name('approvals.index');

// JSON API endpoints for web frontend (session auth)

// Get pending approvals for current user
Route::get('/approvals/pending/json', function () {
    /** @var \App\Models\User $user */
    $user = auth()->user();

    $approvals = DeploymentApproval::pendingForApprover($user)->get();

    return response()->json($approvals->map(function (DeploymentApproval $approval) {
        return [
            'uuid' => $approval->uuid,
            'status' => $approval->status,
            'deployment_uuid' => $approval->deployment?->deployment_uuid,
            'application_name' => $approval->deployment?->application?->name,
            'environment_name' => $approval->deployment?->application?->environment?->name,
            'project_name' => $approval->deployment?->application?->environment?->project?->name,
            'requested_by' => $approval->requestedBy?->email,
            'requested_at' => $approval->created_at?->toIso8601String(),
        ];
    }));
})->name('approvals.pending.json');

// Get pending approvals for a project
Route::get('/projects/{uuid}/approvals/pending/json', function (string $uuid) {
    $project = \App\Models\Project::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $approvals = DeploymentApproval::pendingForProject($project)->get();

    return response()->json($approvals->map(function (DeploymentApproval $approval) {
        return [
            'uuid' => $approval->uuid,
            'status' => $approval->status,
            'deployment_uuid' => $approval->deployment?->deployment_uuid,
            'application_name' => $approval->deployment?->application?->name,
            'environment_name' => $approval->deployment?->application?->environment?->name,
            'requested_by' => $approval->requestedBy?->email,
            'requested_at' => $approval->created_at?->toIso8601String(),
        ];
    }));
})->name('projects.approvals.pending.json');

// Approve a deployment
Route::post('/deployments/{uuid}/approve/json', function (Request $request, string $uuid, ApproveDeploymentAction $approveAction) {
    $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->firstOrFail();

    // Check if deployment belongs to user's team
    $application = $deployment->application;
    if (! $application || $application->team()?->id !== currentTeam()->id) {
        return response()->json(['message' => 'Deployment not found.'], 404);
    }

    $approval = DeploymentApproval::where('application_deployment_queue_id', $deployment->id)
        ->where('status', 'pending')
        ->firstOrFail();

    /** @var \App\Models\User $user */
    $user = auth()->user();

    try {
        $approveAction->approve($approval, $user, $request->input('comment'));

        return response()->json([
            'message' => 'Deployment approved successfully.',
            'deployment_uuid' => $deployment->deployment_uuid,
            'status' => 'approved',
        ]);
    } catch (\Exception $e) {
        $statusCode = str_contains($e->getMessage(), 'permission') ? 403 : 400;

        return response()->json(['message' => $e->getMessage()], $statusCode);
    }
})->name('deployments.approve.json');

// Reject a deployment
Route::post('/deployments/{uuid}/reject/json', function (Request $request, string $uuid, ApproveDeploymentAction $approveAction) {
    $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->firstOrFail();

    // Check if deployment belongs to user's team
    $application = $deployment->application;
    if (! $application || $application->team()?->id !== currentTeam()->id) {
        return response()->json(['message' => 'Deployment not found.'], 404);
    }

    $approval = DeploymentApproval::where('application_deployment_queue_id', $deployment->id)
        ->where('status', 'pending')
        ->firstOrFail();

    /** @var \App\Models\User $user */
    $user = auth()->user();

    try {
        $approveAction->reject($approval, $user, $request->input('reason'));

        return response()->json([
            'message' => 'Deployment rejected successfully.',
            'deployment_uuid' => $deployment->deployment_uuid,
            'status' => 'rejected',
        ]);
    } catch (\Exception $e) {
        $statusCode = str_contains($e->getMessage(), 'permission') ? 403 : 400;

        return response()->json(['message' => $e->getMessage()], $statusCode);
    }
})->name('deployments.reject.json');

// Check if deployment requires approval (for application)
Route::get('/applications/{uuid}/check-approval/json', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->with('environment.project')
        ->firstOrFail();

    /** @var \App\Models\User $user */
    $user = auth()->user();
    $environment = $application->environment;

    $requiresApproval = $user->requiresApprovalForEnvironment($environment);
    $canDeploy = $user->canDeployToEnvironment($environment);
    $userRole = $user->roleInProject($environment->project);

    return response()->json([
        'requires_approval' => $requiresApproval,
        'can_deploy' => $canDeploy,
        'user_role' => $userRole,
        'environment' => [
            'uuid' => $environment->uuid,
            'name' => $environment->name,
            'type' => $environment->type ?? 'development',
            'requires_approval' => $environment->requires_approval,
        ],
    ]);
})->name('applications.check-approval.json');

// Deploy application (JSON response for AJAX)
Route::post('/applications/{uuid}/deploy/json', function (\Illuminate\Http\Request $request, string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $deployment_uuid = new \Visus\Cuid2\Cuid2;
    $requires_approval = $request->boolean('requires_approval', false);

    $result = queue_application_deployment(
        application: $application,
        deployment_uuid: $deployment_uuid,
        force_rebuild: false,
        is_api: false,
        user_id: auth()->id(),
        requires_approval: $requires_approval,
    );

    if ($result['status'] === 'skipped') {
        return response()->json(['message' => $result['message']], 400);
    }

    return response()->json([
        'message' => 'Deployment started',
        'deployment_uuid' => (string) $deployment_uuid,
    ]);
})->name('applications.deploy.json');

// Resource Links (web routes for session auth)
// Helper function to format link for JSON response
$formatResourceLink = function (\App\Models\ResourceLink $link): array {
    $targetType = match ($link->target_type) {
        \App\Models\StandalonePostgresql::class => 'postgresql',
        \App\Models\StandaloneMysql::class => 'mysql',
        \App\Models\StandaloneMariadb::class => 'mariadb',
        \App\Models\StandaloneRedis::class => 'redis',
        \App\Models\StandaloneKeydb::class => 'keydb',
        \App\Models\StandaloneDragonfly::class => 'dragonfly',
        \App\Models\StandaloneMongodb::class => 'mongodb',
        \App\Models\StandaloneClickhouse::class => 'clickhouse',
        \App\Models\Application::class => 'application',
        default => 'unknown',
    };

    // Use smart env key for application targets
    $envKey = $link->target_type === \App\Models\Application::class
        ? $link->getSmartAppEnvKey()
        : $link->getEnvKey();

    return [
        'id' => $link->id,
        'environment_id' => $link->environment_id,
        'source_type' => 'application',
        'source_id' => $link->source_id,
        'source_name' => $link->source?->name,
        'target_type' => $targetType,
        'target_id' => $link->target_id,
        'target_name' => $link->target?->name,
        'inject_as' => $link->inject_as,
        'env_key' => $envKey,
        'auto_inject' => $link->auto_inject,
        'use_external_url' => $link->use_external_url ?? false,
        'created_at' => $link->created_at?->toIso8601String(),
        'updated_at' => $link->updated_at?->toIso8601String(),
    ];
};

Route::get('/environments/{uuid}/links/json', function (string $uuid) use ($formatResourceLink) {
    $environment = \App\Models\Environment::where('uuid', $uuid)
        ->whereHas('project', function ($query) {
            $query->whereRelation('team', 'id', currentTeam()->id);
        })
        ->firstOrFail();

    $links = \App\Models\ResourceLink::where('environment_id', $environment->id)
        ->with(['source', 'target'])
        ->get()
        ->map(fn ($link) => $formatResourceLink($link));

    return response()->json($links);
})->name('environments.links.json');

Route::post('/environments/{uuid}/links/json', function (\Illuminate\Http\Request $request, string $uuid) use ($formatResourceLink) {
    $environment = \App\Models\Environment::where('uuid', $uuid)
        ->whereHas('project', function ($query) {
            $query->whereRelation('team', 'id', currentTeam()->id);
        })
        ->firstOrFail();

    $validated = $request->validate([
        'source_id' => 'required|integer',
        'target_type' => 'required|string|in:postgresql,mysql,mariadb,redis,keydb,dragonfly,mongodb,clickhouse,application',
        'target_id' => 'required|integer',
        'auto_inject' => 'boolean',
    ]);

    // Map target_type string to class name
    $targetTypeMap = [
        'postgresql' => \App\Models\StandalonePostgresql::class,
        'mysql' => \App\Models\StandaloneMysql::class,
        'mariadb' => \App\Models\StandaloneMariadb::class,
        'redis' => \App\Models\StandaloneRedis::class,
        'keydb' => \App\Models\StandaloneKeydb::class,
        'dragonfly' => \App\Models\StandaloneDragonfly::class,
        'mongodb' => \App\Models\StandaloneMongodb::class,
        'clickhouse' => \App\Models\StandaloneClickhouse::class,
        'application' => \App\Models\Application::class,
    ];

    $targetClass = $targetTypeMap[$validated['target_type']] ?? null;
    if (! $targetClass) {
        return response()->json(['message' => 'Invalid target type.'], 400);
    }

    // Verify source application exists
    $application = \App\Models\Application::where('id', $validated['source_id'])
        ->where('environment_id', $environment->id)
        ->first();

    if (! $application) {
        return response()->json(['message' => 'Application not found.'], 404);
    }

    // Check if link already exists
    $existingLink = \App\Models\ResourceLink::where('source_type', \App\Models\Application::class)
        ->where('source_id', $application->id)
        ->where('target_type', $targetClass)
        ->where('target_id', $validated['target_id'])
        ->first();

    if ($existingLink) {
        $existingLink->load(['source', 'target']);

        return response()->json($formatResourceLink($existingLink));
    }

    // Default use_external_url to true for app-to-app links (browser needs FQDN, not Docker DNS)
    $useExternalUrl = $targetClass === \App\Models\Application::class;

    $link = \App\Models\ResourceLink::create([
        'environment_id' => $environment->id,
        'source_type' => \App\Models\Application::class,
        'source_id' => $validated['source_id'],
        'target_type' => $targetClass,
        'target_id' => $validated['target_id'],
        'auto_inject' => $validated['auto_inject'] ?? true,
        'use_external_url' => $useExternalUrl,
    ]);

    // Auto-inject database URL if enabled
    if ($link->auto_inject) {
        try {
            $application->autoInjectDatabaseUrl();
        } catch (\InvalidArgumentException $e) {
            \Illuminate\Support\Facades\Log::warning('Auto-inject failed for link', [
                'link_id' => $link->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // For app-to-app links, create reverse link
    if ($targetClass === \App\Models\Application::class) {
        $reverseLink = \App\Models\ResourceLink::create([
            'environment_id' => $environment->id,
            'source_type' => \App\Models\Application::class,
            'source_id' => $validated['target_id'],
            'target_type' => \App\Models\Application::class,
            'target_id' => $validated['source_id'],
            'auto_inject' => $validated['auto_inject'] ?? true,
            'use_external_url' => true,
        ]);

        // Auto-inject for target application too
        if ($reverseLink->auto_inject) {
            try {
                $target = \App\Models\Application::find($validated['target_id']);
                $target?->autoInjectDatabaseUrl();
            } catch (\InvalidArgumentException $e) {
                \Illuminate\Support\Facades\Log::warning('Auto-inject failed for reverse link', [
                    'link_id' => $reverseLink->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $link->load(['source', 'target']);
        $reverseLink->load(['source', 'target']);

        return response()->json([
            $formatResourceLink($link),
            $formatResourceLink($reverseLink),
        ]);
    }

    $link->load(['source', 'target']);

    return response()->json($formatResourceLink($link));
})->name('environments.links.store.json');

Route::patch('/environments/{uuid}/links/{linkId}/json', function (\Illuminate\Http\Request $request, string $uuid, int $linkId) use ($formatResourceLink) {
    $environment = \App\Models\Environment::where('uuid', $uuid)
        ->whereHas('project', function ($query) {
            $query->whereRelation('team', 'id', currentTeam()->id);
        })
        ->firstOrFail();

    $link = \App\Models\ResourceLink::where('id', $linkId)
        ->where('environment_id', $environment->id)
        ->firstOrFail();

    $validated = $request->validate([
        'use_external_url' => 'boolean',
        'auto_inject' => 'boolean',
        'inject_as' => 'nullable|string|max:255',
    ]);

    // Determine old env key before update
    $oldEnvKey = $link->target_type === \App\Models\Application::class
        ? $link->getSmartAppEnvKey()
        : $link->getEnvKey();

    $link->update($validated);

    // Determine new env key after update
    $newEnvKey = $link->target_type === \App\Models\Application::class
        ? $link->getSmartAppEnvKey()
        : $link->getEnvKey();

    // Re-inject if auto_inject is enabled (value may have changed due to use_external_url toggle)
    if ($link->auto_inject && $link->source instanceof \App\Models\Application) {
        // Remove old env var if key changed
        if ($oldEnvKey !== $newEnvKey) {
            $link->source->environment_variables()
                ->where('key', $oldEnvKey)
                ->delete();
        }
        $link->source->autoInjectDatabaseUrl();
    }

    $link->load(['source', 'target']);

    return response()->json($formatResourceLink($link));
})->name('environments.links.update.json');

Route::delete('/environments/{uuid}/links/{linkId}/json', function (string $uuid, int $linkId) {
    $environment = \App\Models\Environment::where('uuid', $uuid)
        ->whereHas('project', function ($query) {
            $query->whereRelation('team', 'id', currentTeam()->id);
        })
        ->firstOrFail();

    $link = \App\Models\ResourceLink::where('id', $linkId)
        ->where('environment_id', $environment->id)
        ->firstOrFail();

    $link->delete();

    return response()->json(['message' => 'Link deleted']);
})->name('environments.links.destroy.json');
