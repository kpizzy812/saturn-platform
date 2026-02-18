<?php

use App\Http\Controllers\Api\AlertsController;
use App\Http\Controllers\Api\ApplicationActionsController;
use App\Http\Controllers\Api\ApplicationCreateController;
use App\Http\Controllers\Api\ApplicationDeploymentsController;
use App\Http\Controllers\Api\ApplicationEnvsController;
use App\Http\Controllers\Api\ApplicationsController;
use App\Http\Controllers\Api\CloudProviderTokensController;
use App\Http\Controllers\Api\CodeReviewController;
use App\Http\Controllers\Api\DatabaseActionsController;
use App\Http\Controllers\Api\DatabaseBackupsController;
use App\Http\Controllers\Api\DatabaseCreateController;
use App\Http\Controllers\Api\DatabasesController;
use App\Http\Controllers\Api\DeployController;
use App\Http\Controllers\Api\DeploymentAnalysisController;
use App\Http\Controllers\Api\DeploymentApprovalController;
use App\Http\Controllers\Api\GitAnalyzerController;
use App\Http\Controllers\Api\GitController;
use App\Http\Controllers\Api\GithubController;
use App\Http\Controllers\Api\HetznerController;
use App\Http\Controllers\Api\NotificationChannelsController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\OtherController;
use App\Http\Controllers\Api\PermissionSetController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ResourceLinkController;
use App\Http\Controllers\Api\ResourcesController;
use App\Http\Controllers\Api\ResourceTransferController;
use App\Http\Controllers\Api\SecurityController;
use App\Http\Controllers\Api\SentinelMetricsController;
use App\Http\Controllers\Api\ServersController;
use App\Http\Controllers\Api\ServiceActionsController;
use App\Http\Controllers\Api\ServiceEnvsController;
use App\Http\Controllers\Api\ServiceHealthcheckController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamWebhooksController;
use App\Http\Middleware\ApiAllowed;
use App\Jobs\PushServerUpdateJob;
use App\Models\Server;
use Illuminate\Support\Facades\Route;

Route::get('/health', [OtherController::class, 'healthcheck']);
Route::group([
    'prefix' => 'v1',
], function () {
    Route::get('/health', [OtherController::class, 'healthcheck']);
});

Route::post('/feedback', [OtherController::class, 'feedback']);

Route::group([
    'middleware' => ['auth:sanctum', 'api.ability:write'],
    'prefix' => 'v1',
], function () {
    Route::get('/enable', [OtherController::class, 'enable_api']);
    Route::get('/disable', [OtherController::class, 'disable_api']);
});
Route::group([
    'middleware' => ['auth:sanctum', ApiAllowed::class, 'api.sensitive', 'throttle:120,1'],
    'prefix' => 'v1',
], function () {

    Route::get('/version', [OtherController::class, 'version'])->middleware(['api.ability:read']);

    Route::get('/teams', [TeamController::class, 'teams'])->middleware(['api.ability:read']);
    Route::get('/teams/current', [TeamController::class, 'current_team'])->middleware(['api.ability:read']);
    Route::get('/teams/current/members', [TeamController::class, 'current_team_members'])->middleware(['api.ability:read']);
    Route::get('/teams/current/activities', [TeamController::class, 'current_team_activities'])->middleware(['api.ability:read']);
    Route::get('/teams/current/activities/export', [TeamController::class, 'export_team_activities'])->middleware(['api.ability:read']);
    Route::get('/teams/{id}', [TeamController::class, 'team_by_id'])->middleware(['api.ability:read']);
    Route::get('/teams/{id}/members', [TeamController::class, 'members_by_id'])->middleware(['api.ability:read']);

    // Permission Sets
    Route::get('/permission-sets', [PermissionSetController::class, 'index'])->middleware(['api.ability:read']);
    Route::get('/permission-sets/permissions', [PermissionSetController::class, 'permissions'])->middleware(['api.ability:read']);
    Route::get('/permission-sets/my-permissions', [PermissionSetController::class, 'myPermissions'])->middleware(['api.ability:read']);
    Route::get('/permission-sets/{id}', [PermissionSetController::class, 'show'])->middleware(['api.ability:read']);
    Route::post('/permission-sets', [PermissionSetController::class, 'store'])->middleware(['api.ability:write']);
    Route::put('/permission-sets/{id}', [PermissionSetController::class, 'update'])->middleware(['api.ability:write']);
    Route::delete('/permission-sets/{id}', [PermissionSetController::class, 'destroy'])->middleware(['api.ability:write']);
    Route::post('/permission-sets/{id}/permissions', [PermissionSetController::class, 'syncPermissions'])->middleware(['api.ability:write']);
    Route::post('/permission-sets/{id}/users', [PermissionSetController::class, 'assignUser'])->middleware(['api.ability:write']);
    Route::delete('/permission-sets/{id}/users/{userId}', [PermissionSetController::class, 'removeUser'])->middleware(['api.ability:write']);

    // Notifications
    Route::get('/notifications', [NotificationsController::class, 'index'])->middleware(['api.ability:read']);
    Route::get('/notifications/unread-count', [NotificationsController::class, 'unreadCount'])->middleware(['api.ability:read']);
    Route::get('/notifications/preferences', [NotificationsController::class, 'preferences'])->middleware(['api.ability:read']);
    Route::put('/notifications/preferences', [NotificationsController::class, 'updatePreferences'])->middleware(['api.ability:write']);
    Route::post('/notifications/read-all', [NotificationsController::class, 'markAllAsRead'])->middleware(['api.ability:write']);
    Route::get('/notifications/{id}', [NotificationsController::class, 'show'])->middleware(['api.ability:read']);
    Route::post('/notifications/{id}/read', [NotificationsController::class, 'markAsRead'])->middleware(['api.ability:write']);
    Route::delete('/notifications/{id}', [NotificationsController::class, 'destroy'])->middleware(['api.ability:write']);

    // Notification Channels
    Route::get('/notification-channels', [NotificationChannelsController::class, 'index'])->middleware(['api.ability:read']);
    Route::put('/notification-channels/email', [NotificationChannelsController::class, 'updateEmail'])->middleware(['api.ability:write']);
    Route::put('/notification-channels/slack', [NotificationChannelsController::class, 'updateSlack'])->middleware(['api.ability:write']);
    Route::put('/notification-channels/discord', [NotificationChannelsController::class, 'updateDiscord'])->middleware(['api.ability:write']);
    Route::put('/notification-channels/telegram', [NotificationChannelsController::class, 'updateTelegram'])->middleware(['api.ability:write']);
    Route::put('/notification-channels/webhook', [NotificationChannelsController::class, 'updateWebhook'])->middleware(['api.ability:write']);
    Route::put('/notification-channels/pushover', [NotificationChannelsController::class, 'updatePushover'])->middleware(['api.ability:write']);

    // Alerts
    Route::get('/alerts', [AlertsController::class, 'index'])->middleware(['api.ability:read']);
    Route::post('/alerts', [AlertsController::class, 'store'])->middleware(['api.ability:write']);
    Route::get('/alerts/{uuid}', [AlertsController::class, 'show'])->middleware(['api.ability:read']);
    Route::put('/alerts/{uuid}', [AlertsController::class, 'update'])->middleware(['api.ability:write']);
    Route::delete('/alerts/{uuid}', [AlertsController::class, 'destroy'])->middleware(['api.ability:write']);

    // Deployment Approvals
    Route::get('/deployment-approvals', [\App\Http\Controllers\Api\DeploymentApprovalsController::class, 'index'])->middleware(['api.ability:read']);
    Route::post('/deployment-approvals/{deploymentUuid}/approve', [\App\Http\Controllers\Api\DeploymentApprovalsController::class, 'approve'])->middleware(['api.ability:deploy']);
    Route::post('/deployment-approvals/{deploymentUuid}/reject', [\App\Http\Controllers\Api\DeploymentApprovalsController::class, 'reject'])->middleware(['api.ability:deploy']);

    // Webhooks
    Route::get('/webhooks', [TeamWebhooksController::class, 'index'])->middleware(['api.ability:read']);
    Route::post('/webhooks', [TeamWebhooksController::class, 'store'])->middleware(['api.ability:write']);
    Route::get('/webhooks/{uuid}', [TeamWebhooksController::class, 'show'])->middleware(['api.ability:read']);
    Route::put('/webhooks/{uuid}', [TeamWebhooksController::class, 'update'])->middleware(['api.ability:write']);
    Route::delete('/webhooks/{uuid}', [TeamWebhooksController::class, 'destroy'])->middleware(['api.ability:write']);
    Route::post('/webhooks/{uuid}/toggle', [TeamWebhooksController::class, 'toggle'])->middleware(['api.ability:write']);
    Route::post('/webhooks/{uuid}/test', [TeamWebhooksController::class, 'test'])->middleware(['api.ability:write']);
    Route::get('/webhooks/{uuid}/deliveries', [TeamWebhooksController::class, 'deliveries'])->middleware(['api.ability:read']);
    Route::post('/webhooks/{uuid}/deliveries/{deliveryUuid}/retry', [TeamWebhooksController::class, 'retryDelivery'])->middleware(['api.ability:write']);

    Route::get('/projects', [ProjectController::class, 'projects'])->middleware(['api.ability:read']);
    Route::get('/projects/{uuid}', [ProjectController::class, 'project_by_uuid'])->middleware(['api.ability:read']);
    Route::get('/projects/{uuid}/environments', [ProjectController::class, 'get_environments'])->middleware(['api.ability:read']);
    Route::get('/projects/{uuid}/{environment_name_or_uuid}', [ProjectController::class, 'environment_details'])->middleware(['api.ability:read']);
    Route::post('/projects/{uuid}/environments', [ProjectController::class, 'create_environment'])->middleware(['api.ability:write']);
    Route::delete('/projects/{uuid}/environments/{environment_name_or_uuid}', [ProjectController::class, 'delete_environment'])->middleware(['api.ability:write']);

    Route::post('/projects', [ProjectController::class, 'create_project'])->middleware(['api.ability:read']);
    Route::patch('/projects/{uuid}', [ProjectController::class, 'update_project'])->middleware(['api.ability:write']);
    Route::delete('/projects/{uuid}', [ProjectController::class, 'delete_project'])->middleware(['api.ability:write']);
    Route::get('/projects/{uuid}/pending-approvals', [DeploymentApprovalController::class, 'pendingForProject'])->middleware(['api.ability:read']);

    // Resource Links (App <-> Database connections)
    Route::get('/environments/{environment_uuid}/links', [ResourceLinkController::class, 'index'])->middleware(['api.ability:read']);
    Route::post('/environments/{environment_uuid}/links', [ResourceLinkController::class, 'store'])->middleware(['api.ability:write']);
    Route::patch('/environments/{environment_uuid}/links/{link_id}', [ResourceLinkController::class, 'update'])->middleware(['api.ability:write']);
    Route::delete('/environments/{environment_uuid}/links/{link_id}', [ResourceLinkController::class, 'destroy'])->middleware(['api.ability:write']);

    // Environment Settings (type and approval)
    Route::patch('/environments/{environment_uuid}/settings', function (\Illuminate\Http\Request $request, string $environment_uuid) {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $environment = \App\Models\Environment::where('uuid', $environment_uuid)
            ->whereHas('project', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->firstOrFail();

        // Check if user can manage environment settings (requires project admin/owner role)
        $user = auth()->user();
        $userRole = $user->roleInProject($environment->project);
        if (! in_array($userRole, ['owner', 'admin'])) {
            return response()->json(['message' => 'You do not have permission to manage environment settings'], 403);
        }

        $validated = $request->validate([
            'type' => 'sometimes|in:development,uat,production',
            'requires_approval' => 'sometimes|boolean',
        ]);

        $environment->update($validated);

        return response()->json([
            'message' => 'Environment settings updated successfully',
            'environment' => [
                'uuid' => $environment->uuid,
                'type' => $environment->type,
                'requires_approval' => $environment->requires_approval,
            ],
        ]);
    })->middleware(['api.ability:write']);

    Route::get('/security/keys', [SecurityController::class, 'keys'])->middleware(['api.ability:read']);
    Route::post('/security/keys', [SecurityController::class, 'create_key'])->middleware(['api.ability:write']);

    Route::get('/security/keys/{uuid}', [SecurityController::class, 'key_by_uuid'])->middleware(['api.ability:read']);
    Route::patch('/security/keys/{uuid}', [SecurityController::class, 'update_key'])->middleware(['api.ability:write']);
    Route::delete('/security/keys/{uuid}', [SecurityController::class, 'delete_key'])->middleware(['api.ability:write']);

    Route::get('/cloud-tokens', [CloudProviderTokensController::class, 'index'])->middleware(['api.ability:read']);
    Route::post('/cloud-tokens', [CloudProviderTokensController::class, 'store'])->middleware(['api.ability:write']);
    Route::get('/cloud-tokens/{uuid}', [CloudProviderTokensController::class, 'show'])->middleware(['api.ability:read']);
    Route::patch('/cloud-tokens/{uuid}', [CloudProviderTokensController::class, 'update'])->middleware(['api.ability:write']);
    Route::delete('/cloud-tokens/{uuid}', [CloudProviderTokensController::class, 'destroy'])->middleware(['api.ability:write']);
    Route::post('/cloud-tokens/{uuid}/validate', [CloudProviderTokensController::class, 'validateToken'])->middleware(['api.ability:read']);

    Route::match(['get', 'post'], '/deploy', [DeployController::class, 'deploy'])->middleware(['api.ability:deploy', 'throttle:deploy']);
    Route::get('/deployments', [DeployController::class, 'deployments'])->middleware(['api.ability:read']);
    Route::get('/deployments/{uuid}', [DeployController::class, 'deployment_by_uuid'])->middleware(['api.ability:read']);
    // Rate limited log streaming endpoint - 60 requests per minute
    Route::get('/deployments/{uuid}/logs', function ($uuid) {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();
        if (! $deployment) {
            return response()->json(['message' => 'Deployment not found.'], 404);
        }

        // Verify the deployment belongs to the team
        $application = $deployment->application;
        if (! $application || $application->team()?->id !== (int) $teamId) {
            return response()->json(['message' => 'Deployment not found.'], 404);
        }

        $logs = $deployment->logs;
        $parsedLogs = [];

        if ($logs) {
            $parsedLogs = json_decode($logs, true) ?: [];
        }

        return response()->json([
            'deployment_uuid' => $deployment->deployment_uuid,
            'status' => $deployment->status,
            'logs' => $parsedLogs,
        ]);
    })->middleware(['api.ability:read', 'throttle:60,1']);
    Route::post('/deployments/{uuid}/cancel', [DeployController::class, 'cancel_deployment'])->middleware(['api.ability:deploy']);
    Route::get('/deployments/applications/{uuid}', [DeployController::class, 'get_application_deployments'])->middleware(['api.ability:read']);

    // Deployment AI Analysis
    Route::get('/deployments/{uuid}/analysis', [DeploymentAnalysisController::class, 'show'])->middleware(['api.ability:read']);
    Route::post('/deployments/{uuid}/analyze', [DeploymentAnalysisController::class, 'analyze'])->middleware(['api.ability:write']);
    Route::get('/ai/status', [DeploymentAnalysisController::class, 'status'])->middleware(['api.ability:read']);

    // Code Review
    Route::get('/deployments/{uuid}/code-review', [CodeReviewController::class, 'show'])->middleware(['api.ability:read']);
    Route::post('/deployments/{uuid}/code-review', [CodeReviewController::class, 'trigger'])->middleware(['api.ability:write']);
    Route::get('/deployments/{uuid}/code-review/violations', [CodeReviewController::class, 'violations'])->middleware(['api.ability:read']);
    Route::get('/code-review/status', [CodeReviewController::class, 'status'])->middleware(['api.ability:read']);

    // Deployment Approval Routes
    Route::post('/deployments/{uuid}/request-approval', [DeploymentApprovalController::class, 'requestApproval'])->middleware(['api.ability:deploy']);
    Route::post('/deployments/{uuid}/approve', [DeploymentApprovalController::class, 'approve'])->middleware(['api.ability:deploy']);
    Route::post('/deployments/{uuid}/reject', [DeploymentApprovalController::class, 'reject'])->middleware(['api.ability:deploy']);
    Route::get('/deployments/{uuid}/approval-status', [DeploymentApprovalController::class, 'approvalStatus'])->middleware(['api.ability:read']);
    Route::get('/approvals/pending', [DeploymentApprovalController::class, 'myPendingApprovals'])->middleware(['api.ability:read']);

    // Check if deployment requires approval before starting
    Route::get('/applications/{uuid}/check-approval', function (\Illuminate\Http\Request $request, string $uuid) {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $application = \App\Models\Application::where('uuid', $uuid)
            ->whereHas('environment.project', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            })
            ->with('environment.project')
            ->first();

        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();
        $environment = $application->environment;

        // Check if user requires approval for this environment
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
    })->middleware(['api.ability:read']);

    Route::get('/servers', [ServersController::class, 'servers'])->middleware(['api.ability:read']);
    Route::get('/servers/{uuid}', [ServersController::class, 'server_by_uuid'])->middleware(['api.ability:read']);
    Route::get('/servers/{uuid}/domains', [ServersController::class, 'domains_by_server'])->middleware(['api.ability:read']);
    Route::get('/servers/{uuid}/resources', [ServersController::class, 'resources_by_server'])->middleware(['api.ability:read']);

    Route::get('/servers/{uuid}/validate', [ServersController::class, 'validate_server'])->middleware(['api.ability:read']);
    Route::get('/servers/{uuid}/sentinel/metrics', [SentinelMetricsController::class, 'metrics'])->middleware(['api.ability:read']);

    Route::post('/servers', [ServersController::class, 'create_server'])->middleware(['api.ability:read', 'throttle:api-write']);
    Route::patch('/servers/{uuid}', [ServersController::class, 'update_server'])->middleware(['api.ability:write', 'throttle:api-write']);
    Route::delete('/servers/{uuid}', [ServersController::class, 'delete_server'])->middleware(['api.ability:write', 'throttle:api-write']);
    Route::post('/servers/{uuid}/reboot', [ServersController::class, 'reboot_server'])->middleware(['api.ability:write', 'throttle:api-write']);

    Route::get('/hetzner/locations', [HetznerController::class, 'locations'])->middleware(['api.ability:read']);
    Route::get('/hetzner/server-types', [HetznerController::class, 'serverTypes'])->middleware(['api.ability:read']);
    Route::get('/hetzner/images', [HetznerController::class, 'images'])->middleware(['api.ability:read']);
    Route::get('/hetzner/ssh-keys', [HetznerController::class, 'sshKeys'])->middleware(['api.ability:read']);
    Route::post('/servers/hetzner', [HetznerController::class, 'createServer'])->middleware(['api.ability:write']);

    Route::get('/resources', [ResourcesController::class, 'resources'])->middleware(['api.ability:read']);

    Route::get('/applications', [ApplicationsController::class, 'applications'])->middleware(['api.ability:read']);
    Route::post('/applications/public', [ApplicationCreateController::class, 'create_public_application'])->middleware(['api.ability:write', 'throttle:api-write']);
    Route::post('/applications/private-github-app', [ApplicationCreateController::class, 'create_private_gh_app_application'])->middleware(['api.ability:write', 'throttle:api-write']);
    Route::post('/applications/private-deploy-key', [ApplicationCreateController::class, 'create_private_deploy_key_application'])->middleware(['api.ability:write', 'throttle:api-write']);
    Route::post('/applications/dockerfile', [ApplicationCreateController::class, 'create_dockerfile_application'])->middleware(['api.ability:write', 'throttle:api-write']);
    Route::post('/applications/dockerimage', [ApplicationCreateController::class, 'create_dockerimage_application'])->middleware(['api.ability:write', 'throttle:api-write']);
    Route::post('/applications/dockercompose', [ApplicationCreateController::class, 'create_dockercompose_application'])->middleware(['api.ability:write', 'throttle:api-write']);

    Route::get('/applications/{uuid}', [ApplicationsController::class, 'application_by_uuid'])->middleware(['api.ability:read']);
    Route::patch('/applications/{uuid}', [ApplicationsController::class, 'update_by_uuid'])->middleware(['api.ability:write', 'throttle:api-write']);
    Route::delete('/applications/{uuid}', [ApplicationsController::class, 'delete_by_uuid'])->middleware(['api.ability:write', 'throttle:api-write']);

    Route::get('/applications/{uuid}/envs', [ApplicationEnvsController::class, 'envs'])->middleware(['api.ability:read']);
    Route::post('/applications/{uuid}/envs', [ApplicationEnvsController::class, 'create_env'])->middleware(['api.ability:write']);
    Route::patch('/applications/{uuid}/envs/bulk', [ApplicationEnvsController::class, 'create_bulk_envs'])->middleware(['api.ability:write']);
    Route::patch('/applications/{uuid}/envs', [ApplicationEnvsController::class, 'update_env_by_uuid'])->middleware(['api.ability:write']);
    Route::delete('/applications/{uuid}/envs/{env_uuid}', [ApplicationEnvsController::class, 'delete_env_by_uuid'])->middleware(['api.ability:write']);
    Route::get('/applications/{uuid}/logs', [ApplicationsController::class, 'logs_by_uuid'])->middleware(['api.ability:read']);

    Route::match(['get', 'post'], '/applications/{uuid}/start', [ApplicationActionsController::class, 'action_deploy'])->middleware(['api.ability:write', 'throttle:deploy']);
    Route::match(['get', 'post'], '/applications/{uuid}/restart', [ApplicationActionsController::class, 'action_restart'])->middleware(['api.ability:write', 'throttle:deploy']);
    Route::match(['get', 'post'], '/applications/{uuid}/stop', [ApplicationActionsController::class, 'action_stop'])->middleware(['api.ability:write', 'throttle:deploy']);

    // Application Rollback Routes
    Route::get('/applications/{uuid}/deployments', [ApplicationDeploymentsController::class, 'get_deployments'])->middleware(['api.ability:read']);
    Route::get('/applications/{uuid}/rollback-events', [ApplicationDeploymentsController::class, 'get_rollback_events'])->middleware(['api.ability:read']);
    Route::post('/applications/{uuid}/rollback/{deploymentUuid}', [ApplicationDeploymentsController::class, 'execute_rollback'])->middleware(['api.ability:deploy', 'throttle:5,1']);

    // Application Preview Deployment Routes — disabled until implemented
    // TODO: Implement preview deployment API endpoints

    Route::get('/github-apps', [GithubController::class, 'list_github_apps'])->middleware(['api.ability:read']);
    Route::post('/github-apps', [GithubController::class, 'create_github_app'])->middleware(['api.ability:write']);
    Route::patch('/github-apps/{github_app_id}', [GithubController::class, 'update_github_app'])->middleware(['api.ability:write']);
    Route::delete('/github-apps/{github_app_id}', [GithubController::class, 'delete_github_app'])->middleware(['api.ability:write']);
    Route::get('/github-apps/{github_app_id}/repositories', [GithubController::class, 'load_repositories'])->middleware(['api.ability:read']);
    Route::get('/github-apps/{github_app_id}/repositories/{owner}/{repo}/branches', [GithubController::class, 'load_branches'])->middleware(['api.ability:read']);

    // Git operations for public repositories
    Route::get('/git/branches', [GitController::class, 'branches'])->middleware(['api.ability:read']);

    // Git Repository Analysis & Auto-Provisioning
    Route::post('/git/analyze', [GitAnalyzerController::class, 'analyze'])->middleware(['api.ability:read']);
    Route::post('/git/provision', [GitAnalyzerController::class, 'provision'])->middleware(['api.ability:write']);

    // Database CRUD routes
    Route::get('/databases', [DatabasesController::class, 'index'])->middleware(['api.ability:read']);
    Route::get('/databases/{uuid}', [DatabasesController::class, 'show'])->middleware(['api.ability:read']);
    Route::patch('/databases/{uuid}', [DatabasesController::class, 'update'])->middleware(['api.ability:write', 'throttle:api-write']);
    Route::delete('/databases/{uuid}', [DatabasesController::class, 'destroy'])->middleware(['api.ability:write', 'throttle:api-write']);

    // Database creation routes
    Route::post('/databases/postgresql', [DatabaseCreateController::class, 'postgresql'])->middleware(['api.ability:write']);
    Route::post('/databases/mysql', [DatabaseCreateController::class, 'mysql'])->middleware(['api.ability:write']);
    Route::post('/databases/mariadb', [DatabaseCreateController::class, 'mariadb'])->middleware(['api.ability:write']);
    Route::post('/databases/mongodb', [DatabaseCreateController::class, 'mongodb'])->middleware(['api.ability:write']);
    Route::post('/databases/redis', [DatabaseCreateController::class, 'redis'])->middleware(['api.ability:write']);
    Route::post('/databases/clickhouse', [DatabaseCreateController::class, 'clickhouse'])->middleware(['api.ability:write']);
    Route::post('/databases/dragonfly', [DatabaseCreateController::class, 'dragonfly'])->middleware(['api.ability:write']);
    Route::post('/databases/keydb', [DatabaseCreateController::class, 'keydb'])->middleware(['api.ability:write']);

    // Database backup routes
    Route::get('/databases/{uuid}/backups', [DatabaseBackupsController::class, 'index'])->middleware(['api.ability:read']);
    Route::post('/databases/{uuid}/backups', [DatabaseBackupsController::class, 'store'])->middleware(['api.ability:write']);
    Route::patch('/databases/{uuid}/backups/{scheduled_backup_uuid}', [DatabaseBackupsController::class, 'update'])->middleware(['api.ability:write']);
    Route::delete('/databases/{uuid}/backups/{scheduled_backup_uuid}', [DatabaseBackupsController::class, 'destroy'])->middleware(['api.ability:write']);
    Route::get('/databases/{uuid}/backups/{scheduled_backup_uuid}/executions', [DatabaseBackupsController::class, 'listExecutions'])->middleware(['api.ability:read']);
    Route::delete('/databases/{uuid}/backups/{scheduled_backup_uuid}/executions/{execution_uuid}', [DatabaseBackupsController::class, 'destroyExecution'])->middleware(['api.ability:write']);
    Route::post('/databases/{uuid}/backups/{backup_uuid}/restore', [DatabaseBackupsController::class, 'restore'])->middleware(['api.ability:write']);

    Route::get('/databases/{uuid}/logs', function ($uuid, \Illuminate\Http\Request $request) {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $database = queryDatabaseByUuidWithinTeam($uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $server = data_get($database, 'destination.server');
        if (! $server) {
            return response()->json(['message' => 'Server not found for database.'], 400);
        }

        try {
            // Check if database is running
            $containerName = $database->uuid;
            $status = getContainerStatus($server, $containerName);

            if ($status !== 'running') {
                return response()->json(['message' => 'Database is not running.'], 400);
            }

            $lines = min((int) ($request->query('lines', 100) ?: 100), 10000);
            $logs = getContainerLogs($server, $containerName, $lines);

            return response()->json([
                'database_uuid' => $database->uuid,
                'container_name' => $containerName,
                'status' => $status,
                'logs' => $logs,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to connect to server: '.$e->getMessage()], 500);
        }
    })->middleware(['api.ability:read', 'throttle:60,1']);

    // Database action routes
    Route::match(['get', 'post'], '/databases/{uuid}/start', [DatabaseActionsController::class, 'start'])->middleware(['api.ability:write', 'throttle:deploy']);
    Route::match(['get', 'post'], '/databases/{uuid}/restart', [DatabaseActionsController::class, 'restart'])->middleware(['api.ability:write', 'throttle:deploy']);
    Route::match(['get', 'post'], '/databases/{uuid}/stop', [DatabaseActionsController::class, 'stop'])->middleware(['api.ability:write', 'throttle:deploy']);

    // Database structure for transfers
    Route::get('/databases/{uuid}/structure', [ResourceTransferController::class, 'structure'])->middleware(['api.ability:read']);

    // Resource Transfers
    Route::get('/transfers', [ResourceTransferController::class, 'index'])->middleware(['api.ability:read']);
    Route::post('/transfers', [ResourceTransferController::class, 'store'])->middleware(['api.ability:write']);
    Route::get('/transfers/{uuid}', [ResourceTransferController::class, 'show'])->middleware(['api.ability:read']);
    Route::post('/transfers/{uuid}/cancel', [ResourceTransferController::class, 'cancel'])->middleware(['api.ability:write']);

    // Environment Migrations
    Route::get('/migrations', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'index'])->middleware(['api.ability:read']);
    Route::get('/migrations/pending', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'pending'])->middleware(['api.ability:read']);
    Route::post('/migrations/check', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'check'])->middleware(['api.ability:read']);
    Route::get('/migrations/targets/{source_type}/{source_uuid}', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'targets'])->middleware(['api.ability:read']);
    Route::get('/migrations/environment-targets/{environment_uuid}', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'environmentTargets'])->middleware(['api.ability:read']);
    Route::post('/migrations/environment-check', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'environmentCheck'])->middleware(['api.ability:read']);
    Route::post('/migrations/batch', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'batchStore'])->middleware(['api.ability:write']);
    Route::post('/migrations', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'store'])->middleware(['api.ability:write']);
    Route::get('/migrations/{uuid}', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'show'])->middleware(['api.ability:read']);
    Route::post('/migrations/{uuid}/approve', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'approve'])->middleware(['api.ability:write']);
    Route::post('/migrations/{uuid}/reject', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'reject'])->middleware(['api.ability:write']);
    Route::post('/migrations/{uuid}/rollback', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'rollback'])->middleware(['api.ability:write']);
    Route::post('/migrations/{uuid}/cancel', [\App\Http\Controllers\Api\EnvironmentMigrationController::class, 'cancel'])->middleware(['api.ability:write']);

    Route::get('/services', [ServicesController::class, 'services'])->middleware(['api.ability:read']);
    Route::post('/services', [ServicesController::class, 'create_service'])->middleware(['api.ability:write']);

    Route::get('/services/{uuid}', [ServicesController::class, 'service_by_uuid'])->middleware(['api.ability:read']);
    Route::patch('/services/{uuid}', [ServicesController::class, 'update_by_uuid'])->middleware(['api.ability:write']);
    Route::delete('/services/{uuid}', [ServicesController::class, 'delete_by_uuid'])->middleware(['api.ability:write']);

    Route::get('/services/{uuid}/envs', [ServiceEnvsController::class, 'index'])->middleware(['api.ability:read']);
    Route::post('/services/{uuid}/envs', [ServiceEnvsController::class, 'store'])->middleware(['api.ability:write']);
    Route::patch('/services/{uuid}/envs/bulk', [ServiceEnvsController::class, 'bulkUpdate'])->middleware(['api.ability:write']);
    Route::patch('/services/{uuid}/envs', [ServiceEnvsController::class, 'update'])->middleware(['api.ability:write']);
    Route::delete('/services/{uuid}/envs/{env_uuid}', [ServiceEnvsController::class, 'destroy'])->middleware(['api.ability:write']);

    Route::get('/services/{uuid}/logs', function ($uuid, \Illuminate\Http\Request $request) {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $service = \App\Models\Service::whereRelation('environment.project.team', 'id', $teamId)
            ->whereUuid($uuid)
            ->first();

        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $server = $service->server;
        if (! $server) {
            return response()->json(['message' => 'Server not found for service.'], 400);
        }

        $lines = min((int) ($request->query('lines', 100) ?: 100), 10000);
        $containerName = $request->query('container');
        $logs = [];

        try {
            // Get all applications and databases in this service
            $applications = $service->applications()->get();
            $databases = $service->databases()->get();

            foreach ($applications as $app) {
                $appContainerName = $app->name.'-'.$service->uuid;

                // If specific container requested, skip others
                if ($containerName && $appContainerName !== $containerName) {
                    continue;
                }

                $status = getContainerStatus($server, $appContainerName);
                if ($status === 'running') {
                    $logs[$appContainerName] = [
                        'type' => 'application',
                        'name' => $app->name,
                        'status' => $status,
                        'logs' => getContainerLogs($server, $appContainerName, (int) $lines),
                    ];
                } else {
                    $logs[$appContainerName] = [
                        'type' => 'application',
                        'name' => $app->name,
                        'status' => $status,
                        'logs' => null,
                    ];
                }
            }

            foreach ($databases as $db) {
                $dbContainerName = $db->name.'-'.$service->uuid;

                // If specific container requested, skip others
                if ($containerName && $dbContainerName !== $containerName) {
                    continue;
                }

                $status = getContainerStatus($server, $dbContainerName);
                if ($status === 'running') {
                    $logs[$dbContainerName] = [
                        'type' => 'database',
                        'name' => $db->name,
                        'status' => $status,
                        'logs' => getContainerLogs($server, $dbContainerName, (int) $lines),
                    ];
                } else {
                    $logs[$dbContainerName] = [
                        'type' => 'database',
                        'name' => $db->name,
                        'status' => $status,
                        'logs' => null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to connect to server: '.$e->getMessage()], 500);
        }

        return response()->json([
            'service_uuid' => $service->uuid,
            'containers' => $logs,
        ]);
    })->middleware(['api.ability:read', 'throttle:60,1']);

    Route::match(['get', 'post'], '/services/{uuid}/start', [ServiceActionsController::class, 'start'])->middleware(['api.ability:write', 'throttle:deploy']);
    Route::match(['get', 'post'], '/services/{uuid}/restart', [ServiceActionsController::class, 'restart'])->middleware(['api.ability:write', 'throttle:deploy']);
    Route::match(['get', 'post'], '/services/{uuid}/stop', [ServiceActionsController::class, 'stop'])->middleware(['api.ability:write', 'throttle:deploy']);

    // Service deployments (activity log based)
    Route::get('/services/{uuid}/deployments', function (string $uuid, \Illuminate\Http\Request $request) {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $service = \App\Models\Service::whereRelation('environment.project.team', 'id', $teamId)
            ->whereUuid($uuid)
            ->first();

        if (! $service) {
            return response()->json(['message' => 'Service not found'], 404);
        }

        $take = $request->get('take', 20);
        $skip = $request->get('skip', 0);

        // Get activity log entries for this service
        $deployments = \Spatie\Activitylog\Models\Activity::where('properties->type_uuid', $service->uuid)
            ->orderByDesc('created_at')
            ->skip($skip)
            ->take($take)
            ->get()
            ->map(function ($activity) {
                $properties = $activity->properties->toArray();

                return [
                    'id' => $activity->id,
                    'status' => $properties['status'] ?? 'unknown',
                    'commit' => $properties['commit'] ?? null,
                    'commit_message' => $activity->description ?? 'Service operation',
                    'created_at' => $activity->created_at?->toIso8601String(),
                    'updated_at' => $activity->updated_at?->toIso8601String(),
                    'duration' => isset($properties['started_at'], $properties['finished_at'])
                        ? \Carbon\Carbon::parse($properties['finished_at'])->diffInSeconds(\Carbon\Carbon::parse($properties['started_at'])).'s'
                        : null,
                    'author' => $properties['causer_name'] ?? 'System',
                ];
            });

        return response()->json($deployments);
    })->middleware(['api.ability:read']);

    // Healthcheck endpoints
    Route::get('/services/{uuid}/healthcheck', [ServiceHealthcheckController::class, 'show'])->middleware(['api.ability:read']);
    Route::patch('/services/{uuid}/healthcheck', [ServiceHealthcheckController::class, 'update'])->middleware(['api.ability:write']);

    // Billing Routes — disabled until Stripe integration is implemented
    // Uncomment when billing feature is ready for production
    // Route::get('/billing/info', ...)->middleware(['api.ability:read']);
    // Route::get('/billing/payment-methods', ...)->middleware(['api.ability:read']);
    // Route::post('/billing/payment-methods', ...)->middleware(['api.ability:write']);
    // Route::delete('/billing/payment-methods/{paymentMethodId}', ...)->middleware(['api.ability:write']);
    // Route::post('/billing/payment-methods/{paymentMethodId}/default', ...)->middleware(['api.ability:write']);
    // Route::get('/billing/invoices', ...)->middleware(['api.ability:read']);
    // Route::get('/billing/invoices/{invoiceId}/download', ...)->middleware(['api.ability:read']);
    // Route::get('/billing/usage', ...)->middleware(['api.ability:read']);
    // Route::patch('/billing/subscription', ...)->middleware(['api.ability:write']);
    // Route::post('/billing/subscription/cancel', ...)->middleware(['api.ability:write']);
    // Route::post('/billing/subscription/resume', ...)->middleware(['api.ability:write']);
});

Route::group([
    'prefix' => 'v1',
], function () {
    Route::post('/sentinel/push', function () {
        $token = request()->header('Authorization');
        if (! $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $naked_token = str_replace('Bearer ', '', $token);
        try {
            $decrypted = decrypt($naked_token);
            $decrypted_token = json_decode($decrypted, true);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        }
        $server_uuid = data_get($decrypted_token, 'server_uuid');
        if (! $server_uuid) {
            return response()->json(['message' => 'Invalid token'], 401);
        }
        $server = Server::where('uuid', $server_uuid)->first();
        if (! $server) {
            return response()->json(['message' => 'Server not found'], 404);
        }

        if (isCloud() && data_get($server->team->subscription, 'stripe_invoice_paid', false) === false && $server->team->id !== 0) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($server->isFunctional() === false) {
            return response()->json(['message' => 'Server is not functional'], 401);
        }

        if ($server->settings->sentinel_token !== $naked_token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $data = request()->all();

        // \App\Jobs\ServerCheckNewJob::dispatch($server, $data);
        PushServerUpdateJob::dispatch($server, $data);

        return response()->json(['message' => 'ok'], 200);
    });
});

Route::any('/{any}', function () {
    return response()->json(['message' => 'Not found.', 'docs' => '#'], 404);
})->where('any', '.*');
