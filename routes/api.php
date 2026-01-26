<?php

use App\Http\Controllers\Api\ApplicationActionsController;
use App\Http\Controllers\Api\ApplicationCreateController;
use App\Http\Controllers\Api\ApplicationDeploymentsController;
use App\Http\Controllers\Api\ApplicationEnvsController;
use App\Http\Controllers\Api\ApplicationsController;
use App\Http\Controllers\Api\CloudProviderTokensController;
use App\Http\Controllers\Api\DatabaseActionsController;
use App\Http\Controllers\Api\DatabaseBackupsController;
use App\Http\Controllers\Api\DatabaseCreateController;
use App\Http\Controllers\Api\DatabasesController;
use App\Http\Controllers\Api\DeployController;
use App\Http\Controllers\Api\GithubController;
use App\Http\Controllers\Api\HetznerController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\OtherController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ResourceLinkController;
use App\Http\Controllers\Api\ResourcesController;
use App\Http\Controllers\Api\SecurityController;
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
    'middleware' => ['auth:sanctum', ApiAllowed::class, 'api.sensitive'],
    'prefix' => 'v1',
], function () {

    Route::get('/version', [OtherController::class, 'version'])->middleware(['api.ability:read']);

    Route::get('/teams', [TeamController::class, 'teams'])->middleware(['api.ability:read']);
    Route::get('/teams/current', [TeamController::class, 'current_team'])->middleware(['api.ability:read']);
    Route::get('/teams/current/members', [TeamController::class, 'current_team_members'])->middleware(['api.ability:read']);
    Route::get('/teams/current/activities', [TeamController::class, 'current_team_activities'])->middleware(['api.ability:read']);
    Route::get('/teams/{id}', [TeamController::class, 'team_by_id'])->middleware(['api.ability:read']);
    Route::get('/teams/{id}/members', [TeamController::class, 'members_by_id'])->middleware(['api.ability:read']);

    // Notifications
    Route::get('/notifications', [NotificationsController::class, 'index'])->middleware(['api.ability:read']);
    Route::get('/notifications/unread-count', [NotificationsController::class, 'unreadCount'])->middleware(['api.ability:read']);
    Route::get('/notifications/preferences', [NotificationsController::class, 'preferences'])->middleware(['api.ability:read']);
    Route::put('/notifications/preferences', [NotificationsController::class, 'updatePreferences'])->middleware(['api.ability:write']);
    Route::post('/notifications/read-all', [NotificationsController::class, 'markAllAsRead'])->middleware(['api.ability:write']);
    Route::get('/notifications/{id}', [NotificationsController::class, 'show'])->middleware(['api.ability:read']);
    Route::post('/notifications/{id}/read', [NotificationsController::class, 'markAsRead'])->middleware(['api.ability:write']);
    Route::delete('/notifications/{id}', [NotificationsController::class, 'destroy'])->middleware(['api.ability:write']);

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

    // Resource Links (App <-> Database connections)
    Route::get('/environments/{environment_uuid}/links', [ResourceLinkController::class, 'index'])->middleware(['api.ability:read']);
    Route::post('/environments/{environment_uuid}/links', [ResourceLinkController::class, 'store'])->middleware(['api.ability:write']);
    Route::patch('/environments/{environment_uuid}/links/{link_id}', [ResourceLinkController::class, 'update'])->middleware(['api.ability:write']);
    Route::delete('/environments/{environment_uuid}/links/{link_id}', [ResourceLinkController::class, 'destroy'])->middleware(['api.ability:write']);

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

    Route::match(['get', 'post'], '/deploy', [DeployController::class, 'deploy'])->middleware(['api.ability:deploy']);
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

    Route::get('/servers', [ServersController::class, 'servers'])->middleware(['api.ability:read']);
    Route::get('/servers/{uuid}', [ServersController::class, 'server_by_uuid'])->middleware(['api.ability:read']);
    Route::get('/servers/{uuid}/domains', [ServersController::class, 'domains_by_server'])->middleware(['api.ability:read']);
    Route::get('/servers/{uuid}/resources', [ServersController::class, 'resources_by_server'])->middleware(['api.ability:read']);

    Route::get('/servers/{uuid}/validate', [ServersController::class, 'validate_server'])->middleware(['api.ability:read']);

    Route::post('/servers', [ServersController::class, 'create_server'])->middleware(['api.ability:read']);
    Route::patch('/servers/{uuid}', [ServersController::class, 'update_server'])->middleware(['api.ability:write']);
    Route::delete('/servers/{uuid}', [ServersController::class, 'delete_server'])->middleware(['api.ability:write']);
    Route::post('/servers/{uuid}/reboot', [ServersController::class, 'reboot_server'])->middleware(['api.ability:write']);

    Route::get('/hetzner/locations', [HetznerController::class, 'locations'])->middleware(['api.ability:read']);
    Route::get('/hetzner/server-types', [HetznerController::class, 'serverTypes'])->middleware(['api.ability:read']);
    Route::get('/hetzner/images', [HetznerController::class, 'images'])->middleware(['api.ability:read']);
    Route::get('/hetzner/ssh-keys', [HetznerController::class, 'sshKeys'])->middleware(['api.ability:read']);
    Route::post('/servers/hetzner', [HetznerController::class, 'createServer'])->middleware(['api.ability:write']);

    Route::get('/resources', [ResourcesController::class, 'resources'])->middleware(['api.ability:read']);

    Route::get('/applications', [ApplicationsController::class, 'applications'])->middleware(['api.ability:read']);
    Route::post('/applications/public', [ApplicationCreateController::class, 'create_public_application'])->middleware(['api.ability:write']);
    Route::post('/applications/private-github-app', [ApplicationCreateController::class, 'create_private_gh_app_application'])->middleware(['api.ability:write']);
    Route::post('/applications/private-deploy-key', [ApplicationCreateController::class, 'create_private_deploy_key_application'])->middleware(['api.ability:write']);
    Route::post('/applications/dockerfile', [ApplicationCreateController::class, 'create_dockerfile_application'])->middleware(['api.ability:write']);
    Route::post('/applications/dockerimage', [ApplicationCreateController::class, 'create_dockerimage_application'])->middleware(['api.ability:write']);
    Route::post('/applications/dockercompose', [ApplicationCreateController::class, 'create_dockercompose_application'])->middleware(['api.ability:write']);

    Route::get('/applications/{uuid}', [ApplicationsController::class, 'application_by_uuid'])->middleware(['api.ability:read']);
    Route::patch('/applications/{uuid}', [ApplicationsController::class, 'update_by_uuid'])->middleware(['api.ability:write']);
    Route::delete('/applications/{uuid}', [ApplicationsController::class, 'delete_by_uuid'])->middleware(['api.ability:write']);

    Route::get('/applications/{uuid}/envs', [ApplicationEnvsController::class, 'envs'])->middleware(['api.ability:read']);
    Route::post('/applications/{uuid}/envs', [ApplicationEnvsController::class, 'create_env'])->middleware(['api.ability:write']);
    Route::patch('/applications/{uuid}/envs/bulk', [ApplicationEnvsController::class, 'create_bulk_envs'])->middleware(['api.ability:write']);
    Route::patch('/applications/{uuid}/envs', [ApplicationEnvsController::class, 'update_env_by_uuid'])->middleware(['api.ability:write']);
    Route::delete('/applications/{uuid}/envs/{env_uuid}', [ApplicationEnvsController::class, 'delete_env_by_uuid'])->middleware(['api.ability:write']);
    Route::get('/applications/{uuid}/logs', [ApplicationsController::class, 'logs_by_uuid'])->middleware(['api.ability:read']);

    Route::match(['get', 'post'], '/applications/{uuid}/start', [ApplicationActionsController::class, 'action_deploy'])->middleware(['api.ability:write']);
    Route::match(['get', 'post'], '/applications/{uuid}/restart', [ApplicationActionsController::class, 'action_restart'])->middleware(['api.ability:write']);
    Route::match(['get', 'post'], '/applications/{uuid}/stop', [ApplicationActionsController::class, 'action_stop'])->middleware(['api.ability:write']);

    // Application Rollback Routes
    Route::get('/applications/{uuid}/deployments', [ApplicationDeploymentsController::class, 'get_deployments'])->middleware(['api.ability:read']);
    Route::get('/applications/{uuid}/rollback-events', [ApplicationDeploymentsController::class, 'get_rollback_events'])->middleware(['api.ability:read']);
    Route::post('/applications/{uuid}/rollback/{deploymentUuid}', [ApplicationDeploymentsController::class, 'execute_rollback'])->middleware(['api.ability:deploy']);

    // Application Preview Deployment Routes
    Route::get('/applications/{uuid}/previews', function (string $uuid) {
        // TODO: Implement preview deployments listing
        return response()->json([]);
    })->middleware(['api.ability:read']);
    Route::post('/applications/{uuid}/previews', function (string $uuid) {
        // TODO: Implement create preview deployment
        return response()->json(['message' => 'Preview deployment creation not yet implemented'], 501);
    })->middleware(['api.ability:deploy']);
    Route::get('/applications/{uuid}/preview-settings', function (string $uuid) {
        // TODO: Implement get preview settings
        return response()->json([
            'enabled' => false,
            'auto_deploy_on_pr' => false,
            'url_template' => 'pr-{pr_number}-{app_name}.preview.saturn.example',
            'auto_delete_days' => 7,
            'resource_limits' => [
                'cpu' => '1',
                'memory' => '512M',
            ],
        ]);
    })->middleware(['api.ability:read']);
    Route::patch('/applications/{uuid}/preview-settings', function (string $uuid) {
        // TODO: Implement update preview settings
        return response()->json(['message' => 'Preview settings update not yet implemented'], 501);
    })->middleware(['api.ability:write']);

    // Preview Deployment Individual Routes
    Route::get('/previews/{uuid}', function (string $uuid) {
        // TODO: Implement get preview deployment by UUID
        return response()->json(['message' => 'Preview deployment not found'], 404);
    })->middleware(['api.ability:read']);
    Route::delete('/previews/{uuid}', function (string $uuid) {
        // TODO: Implement delete preview deployment
        return response()->json(['message' => 'Preview deployment deletion not yet implemented'], 501);
    })->middleware(['api.ability:write']);
    Route::post('/previews/{uuid}/redeploy', function (string $uuid) {
        // TODO: Implement redeploy preview deployment
        return response()->json(['message' => 'Preview deployment redeploy not yet implemented'], 501);
    })->middleware(['api.ability:deploy']);

    Route::get('/github-apps', [GithubController::class, 'list_github_apps'])->middleware(['api.ability:read']);
    Route::post('/github-apps', [GithubController::class, 'create_github_app'])->middleware(['api.ability:write']);
    Route::patch('/github-apps/{github_app_id}', [GithubController::class, 'update_github_app'])->middleware(['api.ability:write']);
    Route::delete('/github-apps/{github_app_id}', [GithubController::class, 'delete_github_app'])->middleware(['api.ability:write']);
    Route::get('/github-apps/{github_app_id}/repositories', [GithubController::class, 'load_repositories'])->middleware(['api.ability:read']);
    Route::get('/github-apps/{github_app_id}/repositories/{owner}/{repo}/branches', [GithubController::class, 'load_branches'])->middleware(['api.ability:read']);

    // Database CRUD routes
    Route::get('/databases', [DatabasesController::class, 'index'])->middleware(['api.ability:read']);
    Route::get('/databases/{uuid}', [DatabasesController::class, 'show'])->middleware(['api.ability:read']);
    Route::patch('/databases/{uuid}', [DatabasesController::class, 'update'])->middleware(['api.ability:write']);
    Route::delete('/databases/{uuid}', [DatabasesController::class, 'destroy'])->middleware(['api.ability:write']);

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

        // Check if database is running
        $containerName = $database->uuid;
        $status = getContainerStatus($server, $containerName);

        if ($status !== 'running') {
            return response()->json(['message' => 'Database is not running.'], 400);
        }

        $lines = $request->query('lines', 100) ?: 100;
        $logs = getContainerLogs($server, $containerName, (int) $lines);

        return response()->json([
            'database_uuid' => $database->uuid,
            'container_name' => $containerName,
            'status' => $status,
            'logs' => $logs,
        ]);
    })->middleware(['api.ability:read', 'throttle:60,1']);

    // Database action routes
    Route::match(['get', 'post'], '/databases/{uuid}/start', [DatabaseActionsController::class, 'start'])->middleware(['api.ability:write']);
    Route::match(['get', 'post'], '/databases/{uuid}/restart', [DatabaseActionsController::class, 'restart'])->middleware(['api.ability:write']);
    Route::match(['get', 'post'], '/databases/{uuid}/stop', [DatabaseActionsController::class, 'stop'])->middleware(['api.ability:write']);

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

        $lines = $request->query('lines', 100) ?: 100;
        $containerName = $request->query('container');
        $logs = [];

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

        return response()->json([
            'service_uuid' => $service->uuid,
            'containers' => $logs,
        ]);
    })->middleware(['api.ability:read', 'throttle:60,1']);

    Route::match(['get', 'post'], '/services/{uuid}/start', [ServiceActionsController::class, 'start'])->middleware(['api.ability:write']);
    Route::match(['get', 'post'], '/services/{uuid}/restart', [ServiceActionsController::class, 'restart'])->middleware(['api.ability:write']);
    Route::match(['get', 'post'], '/services/{uuid}/stop', [ServiceActionsController::class, 'stop'])->middleware(['api.ability:write']);

    // Healthcheck endpoints
    Route::get('/services/{uuid}/healthcheck', [ServiceHealthcheckController::class, 'show'])->middleware(['api.ability:read']);
    Route::patch('/services/{uuid}/healthcheck', [ServiceHealthcheckController::class, 'update'])->middleware(['api.ability:write']);

    // Billing Routes
    Route::get('/billing/info', function () {
        // TODO: Implement actual billing info fetching
        return response()->json([
            'currentPlan' => [
                'id' => 'pro',
                'name' => 'Pro',
                'price' => 20,
                'billingCycle' => 'monthly',
                'features' => ['Unlimited projects', '1000 deployments/mo', '2500 build minutes'],
                'status' => 'active',
            ],
            'nextBillingDate' => '2024-04-01',
            'estimatedCost' => 102.00,
            'usage' => [
                ['label' => 'CPU Hours', 'current' => 342, 'limit' => 500, 'unit' => 'hours/mo'],
                ['label' => 'Memory', 'current' => 45.2, 'limit' => 100, 'unit' => 'GB/mo'],
                ['label' => 'Network', 'current' => 1240, 'limit' => 2500, 'unit' => 'GB/mo'],
                ['label' => 'Storage', 'current' => 25.8, 'limit' => 50, 'unit' => 'GB'],
            ],
        ]);
    })->middleware(['api.ability:read']);

    Route::get('/billing/payment-methods', function () {
        // TODO: Implement actual payment methods fetching from Stripe
        return response()->json([
            [
                'id' => 'pm_123',
                'type' => 'card',
                'card' => [
                    'brand' => 'Visa',
                    'last4' => '4242',
                    'exp_month' => 12,
                    'exp_year' => 2025,
                ],
                'billing_details' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
                'is_default' => true,
            ],
        ]);
    })->middleware(['api.ability:read']);

    Route::post('/billing/payment-methods', function () {
        // TODO: Implement actual payment method creation with Stripe
        return response()->json(['message' => 'Payment method added successfully']);
    })->middleware(['api.ability:write']);

    Route::delete('/billing/payment-methods/{paymentMethodId}', function ($paymentMethodId) {
        // TODO: Implement actual payment method deletion
        return response()->json(['message' => 'Payment method removed successfully']);
    })->middleware(['api.ability:write']);

    Route::post('/billing/payment-methods/{paymentMethodId}/default', function ($paymentMethodId) {
        // TODO: Implement setting default payment method
        return response()->json(['message' => 'Default payment method updated successfully']);
    })->middleware(['api.ability:write']);

    Route::get('/billing/invoices', function () {
        // TODO: Implement actual invoice fetching from Stripe
        return response()->json([
            [
                'id' => 'in_123',
                'invoice_number' => 'INV-2024-03-001',
                'created' => 1709251200,
                'due_date' => 1710460800,
                'amount_due' => 10200,
                'amount_paid' => 10200,
                'status' => 'paid',
                'description' => 'Pro Plan + Usage',
                'invoice_pdf' => 'https://invoice.pdf',
                'hosted_invoice_url' => 'https://invoice.url',
            ],
        ]);
    })->middleware(['api.ability:read']);

    Route::get('/billing/invoices/{invoiceId}/download', function ($invoiceId) {
        // TODO: Implement actual invoice PDF download from Stripe
        return response()->json(['message' => 'Invoice download endpoint ready']);
    })->middleware(['api.ability:read']);

    Route::get('/billing/usage', function () {
        // TODO: Implement actual usage details fetching
        return response()->json([
            'period_start' => '2024-03-01',
            'period_end' => '2024-04-01',
            'services' => [
                [
                    'id' => 1,
                    'name' => 'Production API',
                    'cpu_hours' => 142,
                    'memory_gb' => 18.5,
                    'network_gb' => 520,
                    'storage_gb' => 12.4,
                    'cost' => 45.20,
                ],
            ],
            'totals' => [
                'cpu_hours' => 342,
                'memory_gb' => 45.2,
                'network_gb' => 1240,
                'storage_gb' => 25.8,
                'total_cost' => 102.00,
            ],
        ]);
    })->middleware(['api.ability:read']);

    Route::patch('/billing/subscription', function () {
        // TODO: Implement actual subscription update with Stripe
        return response()->json(['message' => 'Subscription updated successfully']);
    })->middleware(['api.ability:write']);

    Route::post('/billing/subscription/cancel', function () {
        // TODO: Implement actual subscription cancellation
        return response()->json(['message' => 'Subscription cancelled successfully']);
    })->middleware(['api.ability:write']);

    Route::post('/billing/subscription/resume', function () {
        // TODO: Implement actual subscription resumption
        return response()->json(['message' => 'Subscription resumed successfully']);
    })->middleware(['api.ability:write']);
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
