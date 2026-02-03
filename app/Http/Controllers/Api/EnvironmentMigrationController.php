<?php

namespace App\Http\Controllers\Api;

use App\Actions\Migration\MigrateResourceAction;
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

        $migrations = $query->paginate($request->input('per_page', 25));

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

        $migrations = EnvironmentMigration::pendingForApprover($user)
            ->where('team_id', $teamId)
            ->paginate($request->input('per_page', 25));

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
        ]);

        // Find source resource
        $resource = $this->findResource($validated['source_type'], $validated['source_uuid'], $teamId);
        if (! $resource) {
            return response()->json(['message' => 'Source resource not found.'], 404);
        }

        $sourceEnvironment = $resource->environment;
        $targetEnvironment = Environment::find($validated['target_environment_id']);

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

        // Add available target servers
        $targetServers = [];
        if ($authDetails['allowed']) {
            $targetServers = Server::ownedByCurrentTeam()
                ->where('is_usable', true)
                ->get(['id', 'name', 'ip'])
                ->toArray();
        }

        return response()->json([
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
        ]);
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
            'options.copy_env_vars' => 'nullable|boolean',
            'options.copy_volumes' => 'nullable|boolean',
            'options.update_existing' => 'nullable|boolean',
            'options.config_only' => 'nullable|boolean',
        ]);

        // Find source resource
        $resource = $this->findResource($validated['source_type'], $validated['source_uuid'], $teamId);
        if (! $resource) {
            return response()->json(['message' => 'Source resource not found.'], 404);
        }

        $targetEnvironment = Environment::find($validated['target_environment_id']);
        $targetServer = Server::find($validated['target_server_id']);

        if (! $targetEnvironment || ! $targetServer) {
            return response()->json(['message' => 'Target environment or server not found.'], 404);
        }

        // Verify server belongs to team
        if ($targetServer->team_id !== $teamId) {
            return response()->json(['message' => 'Target server does not belong to your team.'], 403);
        }

        $user = auth()->user();
        $options = $validated['options'] ?? [];

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

        if (! $migration->isAwaitingApproval()) {
            return response()->json(['message' => 'Migration is not pending approval.'], 400);
        }

        // Approve the migration
        $migration->approve($user);

        // Dispatch execution job
        ExecuteMigrationJob::dispatch($migration);

        // Notify requester
        try {
            $migration->requestedBy?->notify(new MigrationApproved($migration));
        } catch (\Throwable $e) {
            \Log::warning('Failed to send migration approved notification: '.$e->getMessage());
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
            \Log::warning('Failed to send migration rejected notification: '.$e->getMessage());
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

        // Get available servers
        $servers = Server::ownedByCurrentTeam()
            ->where('is_usable', true)
            ->get(['id', 'name', 'ip']);

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
}
