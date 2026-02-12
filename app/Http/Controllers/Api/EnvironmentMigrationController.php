<?php

namespace App\Http\Controllers\Api;

use App\Actions\Migration\BatchMigrateAction;
use App\Actions\Migration\MigrateResourceAction;
use App\Actions\Migration\MigrationDiffAction;
use App\Actions\Migration\PreMigrationCheckAction;
use App\Actions\Migration\RollbackMigrationAction;
use App\Actions\Migration\ValidateMigrationChainAction;
use App\Http\Controllers\Controller;
use App\Jobs\ExecuteMigrationJob;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\Server;
use App\Models\Service;
use App\Notifications\Migration\MigrationApproved;
use App\Notifications\Migration\MigrationRejected;
use App\Services\Authorization\MigrationAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class EnvironmentMigrationController extends Controller
{
    public function __construct(
        protected MigrationAuthorizationService $authService
    ) {}

    /**
     * Get all migrations for the current team.
     */
    #[OA\Get(
        path: '/api/v1/migrations',
        summary: 'List migrations',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of migrations'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $query = EnvironmentMigration::where('team_id', $teamId)
            ->with(['source', 'sourceEnvironment.project', 'targetEnvironment', 'targetServer', 'requestedBy', 'approvedBy'])
            ->orderByDesc('created_at');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min($request->integer('per_page', 25), 100);
        $migrations = $query->paginate($perPage);

        return response()->json($migrations);
    }

    /**
     * Get pending migrations that require approval.
     */
    #[OA\Get(
        path: '/api/v1/migrations/pending',
        summary: 'List pending migrations requiring approval',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of pending migrations'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function pending(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $user = auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $perPage = min($request->integer('per_page', 25), 100);
        $migrations = EnvironmentMigration::pendingForApprover($user)
            ->where('team_id', $teamId)
            ->paginate($perPage);

        return response()->json($migrations);
    }

    /**
     * Check if a migration is possible and what approval requirements apply.
     */
    #[OA\Post(
        path: '/api/v1/migrations/check',
        summary: 'Check migration feasibility',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Migration check result'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function check(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $validated = $request->validate([
            'source_type' => 'required|string|in:application,service,database',
            'source_uuid' => 'required|string',
            'target_environment_id' => 'required|integer|exists:environments,id',
            'target_server_id' => 'nullable|integer|exists:servers,id',
            'options' => 'nullable|array',
        ]);

        // Find source resource
        $resource = $this->findResource($validated['source_type'], $validated['source_uuid'], $teamId);
        if (! $resource) {
            return response()->json(['message' => 'Source resource not found.'], 404);
        }

        $sourceEnvironment = $resource->environment;
        $targetEnvironment = Environment::where('id', $validated['target_environment_id'])
            ->whereHas('project', fn ($q) => $q->where('team_id', $teamId))
            ->first();

        if (! $targetEnvironment) {
            return response()->json(['message' => 'Target environment not found.'], 404);
        }

        // Check if environments are in the same project
        if ($sourceEnvironment->project_id !== $targetEnvironment->project_id) {
            return response()->json([
                'allowed' => false,
                'reason' => 'Source and target environments must be in the same project.',
            ]);
        }

        // Get authorization details
        $user = auth()->user();
        $authDetails = $this->authService->getAuthorizationDetails($user, $sourceEnvironment, $targetEnvironment);

        // Add available target servers (using settings relation for is_usable check)
        // Include both team's servers and localhost (Saturn host available to all)
        $targetServers = [];
        if ($authDetails['allowed']) {
            $targetServers = Server::where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)
                    ->orWhere('id', 0); // localhost (Saturn host)
            })
                ->whereRelation('settings', 'is_usable', true)
                ->whereRelation('settings', 'is_reachable', true)
                ->get(['id', 'name', 'ip'])
                ->map(fn ($s) => $this->maskServerIp($s))
                ->toArray();
        }

        $response = [
            'allowed' => $authDetails['allowed'],
            'requires_approval' => $authDetails['requires_approval'],
            'reason' => $authDetails['reason'],
            'source' => [
                'name' => $resource->name,
                'type' => class_basename($resource),
                'environment' => $sourceEnvironment->name,
                'environment_type' => $sourceEnvironment->type ?? 'development',
            ],
            'target' => [
                'environment' => $targetEnvironment->name,
                'environment_type' => $targetEnvironment->type ?? 'development',
            ],
            'target_servers' => $targetServers,
        ];

        // Run pre-migration checks if target server is specified
        if (isset($validated['target_server_id'])) {
            $targetServer = $this->findTeamServer($validated['target_server_id'], $teamId);
            if ($targetServer) {
                $options = $validated['options'] ?? [];
                $response['pre_checks'] = PreMigrationCheckAction::run(
                    $resource, $targetEnvironment, $targetServer, $options
                );

                // Include diff preview
                $response['preview'] = MigrationDiffAction::run(
                    $resource, $targetEnvironment, $options
                );
            }
        }

        return response()->json($response);
    }

    /**
     * Create a new migration request.
     */
    #[OA\Post(
        path: '/api/v1/migrations',
        summary: 'Create migration request',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Migration created'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $validated = $request->validate([
            'source_type' => 'required|string|in:application,service,database',
            'source_uuid' => 'required|string',
            'target_environment_id' => 'required|integer|exists:environments,id',
            'target_server_id' => 'required|integer|exists:servers,id',
            'options' => 'nullable|array',
            'options.mode' => 'nullable|string|in:clone,promote',
            'options.copy_env_vars' => 'nullable|boolean',
            'options.copy_volumes' => 'nullable|boolean',
            'options.update_existing' => 'nullable|boolean',
            'options.config_only' => 'nullable|boolean',
            'options.auto_deploy' => 'nullable|boolean',
            'options.fqdn' => 'nullable|string|max:255',
            'dry_run' => 'nullable|boolean',
        ]);

        // Find source resource
        $resource = $this->findResource($validated['source_type'], $validated['source_uuid'], $teamId);
        if (! $resource) {
            return response()->json(['message' => 'Source resource not found.'], 404);
        }

        $targetEnvironment = Environment::where('id', $validated['target_environment_id'])
            ->whereHas('project', fn ($q) => $q->where('team_id', $teamId))
            ->first();
        $targetServer = $this->findTeamServer($validated['target_server_id'], $teamId);

        if (! $targetEnvironment || ! $targetServer) {
            return response()->json(['message' => 'Target environment or server not found.'], 404);
        }

        $options = $validated['options'] ?? [];

        // Dry run mode: return diff and pre-checks without creating migration
        if ($validated['dry_run'] ?? false) {
            $preChecks = PreMigrationCheckAction::run($resource, $targetEnvironment, $targetServer, $options);
            $diff = MigrationDiffAction::run($resource, $targetEnvironment, $options);

            return response()->json([
                'dry_run' => true,
                'pre_checks' => $preChecks,
                'diff' => $diff,
            ]);
        }

        $user = auth()->user();

        // Execute migration action
        $result = MigrateResourceAction::run(
            $resource,
            $targetEnvironment,
            $targetServer,
            $user,
            $options
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['error']], 400);
        }

        $statusCode = $result['requires_approval'] ? 202 : 201;
        $message = $result['requires_approval']
            ? 'Migration request created and pending approval.'
            : 'Migration started.';

        return response()->json([
            'message' => $message,
            'migration' => $result['migration'],
            'requires_approval' => $result['requires_approval'],
            'warnings' => $result['warnings'] ?? [],
        ], $statusCode);
    }

    /**
     * Batch create migrations for multiple resources.
     * Resources are processed in dependency order: databases → services → applications.
     */
    #[OA\Post(
        path: '/api/v1/migrations/batch',
        summary: 'Batch create migrations',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 201, description: 'Migrations created'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function batchStore(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $validated = $request->validate([
            'target_environment_id' => 'required|integer|exists:environments,id',
            'target_server_id' => 'required|integer|exists:servers,id',
            'resources' => 'required|array|min:1|max:50',
            'resources.*.type' => 'required|string|in:application,service,database',
            'resources.*.uuid' => 'required|string',
            'options' => 'nullable|array',
        ]);

        $targetEnvironment = Environment::where('id', $validated['target_environment_id'])
            ->whereHas('project', fn ($q) => $q->where('team_id', $teamId))
            ->first();
        $targetServer = $this->findTeamServer($validated['target_server_id'], $teamId);

        if (! $targetEnvironment || ! $targetServer) {
            return response()->json(['message' => 'Target environment or server not found.'], 404);
        }

        $user = auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Resolve resources
        $resources = [];
        $notFound = [];

        foreach ($validated['resources'] as $item) {
            $resource = $this->findResource($item['type'], $item['uuid'], $teamId);
            if ($resource) {
                $resources[] = ['type' => $item['type'], 'resource' => $resource];
            } else {
                $notFound[] = ['type' => $item['type'], 'uuid' => $item['uuid']];
            }
        }

        if (! empty($notFound)) {
            return response()->json([
                'message' => 'Some resources were not found.',
                'not_found' => $notFound,
            ], 404);
        }

        $result = BatchMigrateAction::run(
            $resources,
            $targetEnvironment,
            $targetServer,
            $user,
            $validated['options'] ?? []
        );

        $statusCode = $result['success'] ? 201 : 207; // 207 Multi-Status if partial

        return response()->json([
            'message' => $result['success'] ? 'All migrations created.' : 'Some migrations failed.',
            'migrations' => $result['migrations'],
            'errors' => $result['errors'],
        ], $statusCode);
    }

    /**
     * Get migration details.
     */
    #[OA\Get(
        path: '/api/v1/migrations/{uuid}',
        summary: 'Get migration details',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Migration details'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $migration = EnvironmentMigration::where('uuid', $uuid)
            ->where('team_id', $teamId)
            ->with(['source', 'target', 'sourceEnvironment.project', 'targetEnvironment', 'targetServer', 'requestedBy', 'approvedBy', 'history'])
            ->first();

        if (! $migration) {
            return response()->json(['message' => 'Migration not found.'], 404);
        }

        // Hide sensitive snapshot data from API response
        $migration->makeHidden(['rollback_snapshot']);

        return response()->json($migration);
    }

    /**
     * Approve a pending migration.
     */
    #[OA\Post(
        path: '/api/v1/migrations/{uuid}/approve',
        summary: 'Approve migration',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Migration approved'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function approve(Request $request, string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $migration = EnvironmentMigration::where('uuid', $uuid)
            ->where('team_id', $teamId)
            ->first();

        if (! $migration) {
            return response()->json(['message' => 'Migration not found.'], 404);
        }

        $user = auth()->user();

        // Check authorization
        if (! Gate::allows('approve', $migration)) {
            return response()->json(['message' => 'You are not authorized to approve this migration.'], 403);
        }

        // Atomic approve: prevents race condition where two parallel requests both pass
        // the status check and dispatch duplicate jobs
        $updated = EnvironmentMigration::where('uuid', $uuid)
            ->where('team_id', $teamId)
            ->where('status', EnvironmentMigration::STATUS_PENDING)
            ->where('requires_approval', true)
            ->update([
                'status' => EnvironmentMigration::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

        if (! $updated) {
            return response()->json(['message' => 'Migration already processed or not pending approval.'], 409);
        }

        $migration->refresh();

        // Dispatch execution job
        ExecuteMigrationJob::dispatch($migration);

        // Notify requester
        try {
            $migration->requestedBy?->notify(new MigrationApproved($migration));
        } catch (\Throwable $e) {
            Log::warning('Failed to send migration approved notification: '.$e->getMessage());
        }

        return response()->json([
            'message' => 'Migration approved and execution started.',
            'migration' => $migration->fresh(),
        ]);
    }

    /**
     * Reject a pending migration.
     */
    #[OA\Post(
        path: '/api/v1/migrations/{uuid}/reject',
        summary: 'Reject migration',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Migration rejected'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function reject(Request $request, string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $migration = EnvironmentMigration::where('uuid', $uuid)
            ->where('team_id', $teamId)
            ->first();

        if (! $migration) {
            return response()->json(['message' => 'Migration not found.'], 404);
        }

        $user = auth()->user();

        // Check authorization
        if (! Gate::allows('reject', $migration)) {
            return response()->json(['message' => 'You are not authorized to reject this migration.'], 403);
        }

        if (! $migration->isAwaitingApproval()) {
            return response()->json(['message' => 'Migration is not pending approval.'], 400);
        }

        // Reject the migration
        $migration->reject($user, $validated['reason']);

        // Notify requester
        try {
            $migration->requestedBy?->notify(new MigrationRejected($migration));
        } catch (\Throwable $e) {
            Log::warning('Failed to send migration rejected notification: '.$e->getMessage());
        }

        return response()->json([
            'message' => 'Migration rejected.',
            'migration' => $migration->fresh(),
        ]);
    }

    /**
     * Rollback a completed migration.
     */
    #[OA\Post(
        path: '/api/v1/migrations/{uuid}/rollback',
        summary: 'Rollback migration',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Migration rolled back'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function rollback(Request $request, string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $migration = EnvironmentMigration::where('uuid', $uuid)
            ->where('team_id', $teamId)
            ->first();

        if (! $migration) {
            return response()->json(['message' => 'Migration not found.'], 404);
        }

        $user = auth()->user();

        // Check authorization
        if (! Gate::allows('rollback', $migration)) {
            return response()->json(['message' => 'You are not authorized to rollback this migration.'], 403);
        }

        // Execute rollback
        $result = RollbackMigrationAction::run($migration, $user);

        if (! $result['success']) {
            return response()->json(['message' => $result['error']], 400);
        }

        return response()->json([
            'message' => 'Migration rolled back successfully.',
            'migration' => $migration->fresh(),
        ]);
    }

    /**
     * Cancel a pending or approved migration.
     */
    #[OA\Post(
        path: '/api/v1/migrations/{uuid}/cancel',
        summary: 'Cancel migration',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Migration cancelled'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $migration = EnvironmentMigration::where('uuid', $uuid)
            ->where('team_id', $teamId)
            ->first();

        if (! $migration) {
            return response()->json(['message' => 'Migration not found.'], 404);
        }

        // Only the requester or someone with cancel permission can cancel
        $user = auth()->user();
        if ($user && (int) $user->id !== (int) $migration->requested_by && ! Gate::allows('approve', $migration)) {
            return response()->json(['message' => 'You are not authorized to cancel this migration.'], 403);
        }

        if (! $migration->canBeCancelled()) {
            return response()->json([
                'message' => 'Migration cannot be cancelled. Current status: '.$migration->status,
            ], 400);
        }

        $migration->markAsCancelled();

        return response()->json([
            'message' => 'Migration cancelled successfully.',
            'migration' => $migration->fresh(),
        ]);
    }

    /**
     * Get available target environments for a resource.
     */
    #[OA\Get(
        path: '/api/v1/migrations/targets/{source_type}/{source_uuid}',
        summary: 'Get available migration targets',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'source_type', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'source_uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Available targets'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function targets(string $sourceType, string $sourceUuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $resource = $this->findResource($sourceType, $sourceUuid, $teamId);
        if (! $resource) {
            return response()->json(['message' => 'Source resource not found.'], 404);
        }

        $sourceEnvironment = $resource->environment;
        if (! $sourceEnvironment) {
            return response()->json(['message' => 'Source environment not found.'], 404);
        }

        // Get available target environments
        $targetEnvironments = ValidateMigrationChainAction::make()
            ->getAvailableTargets($sourceEnvironment);

        // Get available servers (using settings relation for is_usable check)
        // Include both team's servers and localhost (Saturn host available to all)
        $servers = Server::where(function ($query) use ($teamId) {
            $query->where('team_id', $teamId)
                ->orWhere('id', 0); // localhost (Saturn host)
        })
            ->whereRelation('settings', 'is_usable', true)
            ->whereRelation('settings', 'is_reachable', true)
            ->get(['id', 'name', 'ip'])
            ->map(fn ($s) => $this->maskServerIp($s));

        return response()->json([
            'source' => [
                'name' => $resource->name,
                'type' => class_basename($resource),
                'environment' => $sourceEnvironment->name,
                'environment_type' => $sourceEnvironment->type ?? 'development',
            ],
            'target_environments' => $targetEnvironments,
            'servers' => $servers,
        ]);
    }

    /**
     * Get available target environments for an environment (for bulk migration).
     */
    #[OA\Get(
        path: '/api/v1/migrations/environment-targets/{environment_uuid}',
        summary: 'Get available migration targets for an environment',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'environment_uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Available targets'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function environmentTargets(string $environmentUuid): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $environment = Environment::where('uuid', $environmentUuid)
            ->whereHas('project', fn ($q) => $q->where('team_id', $teamId))
            ->first();

        if (! $environment) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        // Get available target environments
        $targetEnvironments = ValidateMigrationChainAction::make()
            ->getAvailableTargets($environment);

        // Get available servers (using settings relation for is_usable check)
        // Include both team's servers and localhost (Saturn host available to all)
        $servers = Server::where(function ($query) use ($teamId) {
            $query->where('team_id', $teamId)
                ->orWhere('id', 0); // localhost (Saturn host)
        })
            ->whereRelation('settings', 'is_usable', true)
            ->whereRelation('settings', 'is_reachable', true)
            ->get(['id', 'name', 'ip'])
            ->map(fn ($s) => $this->maskServerIp($s));

        return response()->json([
            'source' => [
                'name' => $environment->name,
                'type' => 'environment',
                'environment' => $environment->name,
                'environment_type' => $environment->type ?? 'development',
            ],
            'target_environments' => $targetEnvironments,
            'servers' => $servers,
        ]);
    }

    /**
     * Bulk check migration feasibility for all resources in an environment.
     * Returns per-resource check results including auto-detected mode (clone vs promote)
     * and diff previews.
     */
    #[OA\Post(
        path: '/api/v1/migrations/environment-check',
        summary: 'Bulk check migration for an environment',
        tags: ['Migrations'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Bulk migration check result'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function environmentCheck(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $validated = $request->validate([
            'source_environment_uuid' => 'required|string',
            'target_environment_id' => 'required|integer|exists:environments,id',
            'target_server_id' => 'required|integer|exists:servers,id',
            'resources' => 'required|array|min:1',
            'resources.*.type' => 'required|string|in:application,service,database',
            'resources.*.uuid' => 'required|string',
        ]);

        $sourceEnv = Environment::where('uuid', $validated['source_environment_uuid'])
            ->whereHas('project', fn ($q) => $q->where('team_id', $teamId))
            ->first();

        if (! $sourceEnv) {
            return response()->json(['message' => 'Source environment not found.'], 404);
        }

        $targetEnv = Environment::where('id', $validated['target_environment_id'])
            ->whereHas('project', fn ($q) => $q->where('team_id', $teamId))
            ->first();
        $targetServer = $this->findTeamServer($validated['target_server_id'], $teamId);

        if (! $targetEnv || ! $targetServer) {
            return response()->json(['message' => 'Target environment or server not found.'], 404);
        }

        $isTargetProduction = $targetEnv->isProduction();
        $results = [];

        foreach ($validated['resources'] as $resourceData) {
            $resource = $this->findResource($resourceData['type'], $resourceData['uuid'], $teamId);

            if (! $resource) {
                $results[] = [
                    'uuid' => $resourceData['uuid'],
                    'type' => $resourceData['type'],
                    'error' => 'Resource not found',
                ];

                continue;
            }

            // Auto-detect mode: check if resource already exists in target env
            $existingTarget = $this->findExistingInEnvironment($resource, $targetEnv);
            $mode = $existingTarget ? 'promote' : 'clone';

            // Run pre-checks
            $options = ['mode' => $mode];
            if ($mode === 'promote') {
                $options['update_existing'] = true;
            }

            $preChecks = PreMigrationCheckAction::run($resource, $targetEnv, $targetServer, $options);

            // Generate diff preview
            $preview = MigrationDiffAction::run($resource, $targetEnv, $options);

            $results[] = [
                'uuid' => $resourceData['uuid'],
                'type' => $resourceData['type'],
                'name' => $resource->name ?? 'unnamed',
                'mode' => $mode,
                'target_exists' => $existingTarget !== null,
                'target_fqdn' => $existingTarget ? $existingTarget->getAttribute('fqdn') : null,
                'is_production' => $isTargetProduction,
                'pre_checks' => $preChecks,
                'preview' => $preview,
            ];
        }

        return response()->json([
            'target_environment' => [
                'name' => $targetEnv->name,
                'type' => $targetEnv->type ?? 'development',
                'is_production' => $isTargetProduction,
            ],
            'resources' => $results,
        ]);
    }

    /**
     * Find an existing resource in target environment by name.
     */
    protected function findExistingInEnvironment(\Illuminate\Database\Eloquent\Model $resource, Environment $targetEnv): ?\Illuminate\Database\Eloquent\Model
    {
        $name = $resource->name ?? null;
        if (! $name) {
            return null;
        }

        if ($resource instanceof Application) {
            return $targetEnv->applications()->where('name', $name)->first();
        }

        if ($resource instanceof Service) {
            return $targetEnv->services()->where('name', $name)->first();
        }

        // Check databases
        $databaseClasses = [
            \App\Models\StandalonePostgresql::class => 'postgresqls',
            \App\Models\StandaloneMysql::class => 'mysqls',
            \App\Models\StandaloneMariadb::class => 'mariadbs',
            \App\Models\StandaloneMongodb::class => 'mongodbs',
            \App\Models\StandaloneRedis::class => 'redis',
            \App\Models\StandaloneClickhouse::class => 'clickhouses',
            \App\Models\StandaloneKeydb::class => 'keydbs',
            \App\Models\StandaloneDragonfly::class => 'dragonflies',
        ];

        $resourceClass = get_class($resource);
        if (isset($databaseClasses[$resourceClass])) {
            $relation = $databaseClasses[$resourceClass];
            if (method_exists($targetEnv, $relation)) {
                return $targetEnv->$relation()->where('name', $name)->first();
            }
        }

        return null;
    }

    /**
     * Find a resource by type and UUID.
     */
    protected function findResource(string $type, string $uuid, int $teamId): mixed
    {
        return match ($type) {
            'application' => Application::where('uuid', $uuid)
                ->whereHas('environment.project', fn ($q) => $q->where('team_id', $teamId))
                ->first(),
            'service' => Service::where('uuid', $uuid)
                ->whereHas('environment.project', fn ($q) => $q->where('team_id', $teamId))
                ->first(),
            'database' => $this->findDatabase($uuid, $teamId),
            default => null,
        };
    }

    /**
     * Find a database by UUID across all database types.
     */
    protected function findDatabase(string $uuid, int $teamId): mixed
    {
        $databaseClasses = [
            \App\Models\StandalonePostgresql::class,
            \App\Models\StandaloneMysql::class,
            \App\Models\StandaloneMariadb::class,
            \App\Models\StandaloneMongodb::class,
            \App\Models\StandaloneRedis::class,
            \App\Models\StandaloneClickhouse::class,
            \App\Models\StandaloneKeydb::class,
            \App\Models\StandaloneDragonfly::class,
        ];

        foreach ($databaseClasses as $class) {
            $database = $class::where('uuid', $uuid)
                ->whereHas('environment.project', fn ($q) => $q->where('team_id', $teamId))
                ->first();

            if ($database) {
                return $database;
            }
        }

        return null;
    }

    /**
     * Find a server that belongs to the team (or is localhost).
     */
    private function findTeamServer(int $serverId, int $teamId): ?Server
    {
        return Server::where('id', $serverId)
            ->where(function ($query) use ($teamId) {
                $query->where('team_id', $teamId)
                    ->orWhere('id', 0); // localhost (Saturn host)
            })
            ->first();
    }

    /**
     * Mask server IP when Cloudflare protection is active and user is not admin/superadmin.
     */
    private function maskServerIp(Server $server): Server
    {
        $user = auth()->user();
        $isAdmin = $user && ($user->is_superadmin || $user->platform_role === 'admin');

        if (! $isAdmin && instanceSettings()->isCloudflareProtectionActive()) {
            $server->ip = '[protected]';
        }

        return $server;
    }
}
