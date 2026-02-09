<?php

namespace App\Http\Controllers\Inertia;

use App\Actions\Transfer\CloneApplicationAction;
use App\Actions\Transfer\CloneServiceAction;
use App\Actions\Transfer\CreateTransferAction;
use App\Actions\Transfer\GetDatabaseStructureAction;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Environment;
use App\Models\ResourceTransfer;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Inertia controller for resource transfers.
 */
class TransferController extends Controller
{
    /**
     * Display a listing of transfers.
     */
    public function index(Request $request)
    {
        $query = ResourceTransfer::ownedByCurrentTeam()
            ->with(['source', 'target', 'targetEnvironment', 'targetServer', 'user']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transfers = $query->paginate(20);

        return Inertia::render('Transfers/Index', [
            'transfers' => $transfers,
            'statusFilter' => $request->status,
        ]);
    }

    /**
     * Display a specific transfer.
     */
    public function show(string $uuid)
    {
        $transfer = ResourceTransfer::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->with(['source', 'target', 'targetEnvironment', 'targetEnvironment.project', 'targetServer', 'user'])
            ->firstOrFail();

        return Inertia::render('Transfers/Show', [
            'transfer' => $transfer,
        ]);
    }

    /**
     * Create a new transfer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'source_type' => 'nullable|string|in:application,service,database',
            'source_uuid' => 'required|string',
            'target_environment_id' => 'required|integer',
            'target_server_id' => 'required|integer',
            'transfer_mode' => 'required|string|in:clone,data_only,partial',
            'transfer_options' => 'nullable|array',
            'target_uuid' => 'nullable|string',
        ]);

        // Find target environment
        $targetEnvironment = Environment::ownedByCurrentTeam()
            ->find($validated['target_environment_id']);
        if (! $targetEnvironment) {
            return response()->json(['message' => 'Target environment not found'], 404);
        }

        // Find target server
        $targetServer = Server::ownedByCurrentTeamCached()
            ->firstWhere('id', $validated['target_server_id']);
        if (! $targetServer) {
            return response()->json(['message' => 'Target server not found'], 404);
        }

        $sourceType = $validated['source_type'] ?? 'database';
        $options = $validated['transfer_options'] ?? [];

        // Handle different source types
        switch ($sourceType) {
            case 'application':
                return $this->cloneApplication(
                    $validated['source_uuid'],
                    $targetEnvironment,
                    $targetServer,
                    $options
                );

            case 'service':
                return $this->cloneService(
                    $validated['source_uuid'],
                    $targetEnvironment,
                    $targetServer,
                    $options
                );

            default:
                return $this->transferDatabase(
                    $validated['source_uuid'],
                    $targetEnvironment,
                    $targetServer,
                    $validated['transfer_mode'],
                    $options,
                    $validated['target_uuid'] ?? null
                );
        }
    }

    /**
     * Clone an application to a different environment.
     */
    protected function cloneApplication(
        string $sourceUuid,
        Environment $targetEnvironment,
        Server $targetServer,
        array $options
    ) {
        $sourceApplication = Application::ownedByCurrentTeam()
            ->where('uuid', $sourceUuid)
            ->first();

        if (! $sourceApplication) {
            return response()->json(['message' => 'Source application not found'], 404);
        }

        // Create transfer record for tracking
        $transfer = ResourceTransfer::create([
            'team_id' => currentTeam()->id,
            'user_id' => auth()->id(),
            'source_type' => $sourceApplication->getMorphClass(),
            'source_id' => $sourceApplication->id,
            'target_environment_id' => $targetEnvironment->id,
            'target_server_id' => $targetServer->id,
            'transfer_mode' => ResourceTransfer::MODE_CLONE,
            'transfer_options' => $options,
            'status' => ResourceTransfer::STATUS_PENDING,
        ]);

        // Execute clone action
        $action = new CloneApplicationAction;
        $result = $action->handle(
            sourceApplication: $sourceApplication,
            targetEnvironment: $targetEnvironment,
            targetServer: $targetServer,
            options: [
                'copyEnvVars' => $options['copy_env_vars'] ?? true,
                'copyVolumes' => $options['copy_volumes'] ?? true,
                'copyTags' => $options['copy_tags'] ?? true,
                'instantDeploy' => $options['instant_deploy'] ?? false,
                'newName' => $options['new_name'] ?? null,
                'transferId' => $transfer->id,
            ]
        );

        if (! $result['success']) {
            $transfer->markAsFailed($result['error']);

            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'success' => true,
            'uuid' => $transfer->uuid,
            'application_uuid' => $result['application']->uuid ?? null,
        ]);
    }

    /**
     * Clone a service to a different environment.
     */
    protected function cloneService(
        string $sourceUuid,
        Environment $targetEnvironment,
        Server $targetServer,
        array $options
    ) {
        $sourceService = Service::ownedByCurrentTeam()
            ->where('uuid', $sourceUuid)
            ->first();

        if (! $sourceService) {
            return response()->json(['message' => 'Source service not found'], 404);
        }

        // Create transfer record for tracking
        $transfer = ResourceTransfer::create([
            'team_id' => currentTeam()->id,
            'user_id' => auth()->id(),
            'source_type' => $sourceService->getMorphClass(),
            'source_id' => $sourceService->id,
            'target_environment_id' => $targetEnvironment->id,
            'target_server_id' => $targetServer->id,
            'transfer_mode' => ResourceTransfer::MODE_CLONE,
            'transfer_options' => $options,
            'status' => ResourceTransfer::STATUS_PENDING,
        ]);

        // Execute clone action
        $action = new CloneServiceAction;
        $result = $action->handle(
            sourceService: $sourceService,
            targetEnvironment: $targetEnvironment,
            targetServer: $targetServer,
            options: [
                'copyEnvVars' => $options['copy_env_vars'] ?? true,
                'copyVolumes' => $options['copy_volumes'] ?? true,
                'copyTags' => $options['copy_tags'] ?? true,
                'newName' => $options['new_name'] ?? null,
                'transferId' => $transfer->id,
            ]
        );

        if (! $result['success']) {
            $transfer->markAsFailed($result['error']);

            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'success' => true,
            'uuid' => $transfer->uuid,
            'service_uuid' => $result['service']->uuid ?? null,
        ]);
    }

    /**
     * Transfer database data.
     */
    protected function transferDatabase(
        string $sourceUuid,
        Environment $targetEnvironment,
        Server $targetServer,
        string $transferMode,
        ?array $options,
        ?string $existingTargetUuid
    ) {
        $sourceDatabase = queryDatabaseByUuidWithinTeam($sourceUuid, currentTeam()->id);
        if (! $sourceDatabase) {
            return response()->json(['message' => 'Source database not found'], 404);
        }

        // Create transfer
        $action = new CreateTransferAction;
        $result = $action->execute(
            sourceDatabase: $sourceDatabase,
            targetEnvironment: $targetEnvironment,
            targetServer: $targetServer,
            transferMode: $transferMode,
            transferOptions: $options,
            existingTargetUuid: $existingTargetUuid
        );

        if (! $result['success']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'success' => true,
            'uuid' => $result['transfer']->uuid,
        ]);
    }

    /**
     * Cancel a transfer.
     */
    public function cancel(string $uuid)
    {
        $transfer = ResourceTransfer::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! $transfer->canBeCancelled()) {
            return back()->withErrors(['general' => 'Transfer cannot be cancelled in its current state']);
        }

        $transfer->markAsCancelled();

        return back()->with('success', 'Transfer cancelled');
    }

    /**
     * Get database structure for partial transfer selection.
     */
    public function structure(string $uuid)
    {
        $database = queryDatabaseByUuidWithinTeam($uuid, currentTeam()->id);
        if (! $database) {
            return response()->json(['error' => 'Database not found'], 404);
        }

        $action = new GetDatabaseStructureAction;
        $result = $action->execute($database);

        return response()->json($result);
    }

    /**
     * Get available environments and servers for transfer target selection.
     */
    public function targets(Request $request)
    {
        $sourceType = $request->get('source_type');
        $sourceUuid = $request->get('source_uuid');

        $environments = Environment::ownedByCurrentTeam()
            ->with('project')
            ->get()
            ->map(function ($env) {
                return [
                    'id' => $env->id,
                    'uuid' => $env->uuid,
                    'name' => $env->name,
                    'project_name' => $env->project->name,
                    'project_uuid' => $env->project->uuid,
                ];
            });

        // Get servers with their associated environments via destinations
        $maskIp = instanceSettings()->isCloudflareProtectionActive()
            && ! (auth()->user()?->is_superadmin || auth()->user()?->platform_role === 'admin');

        $servers = Server::ownedByCurrentTeamCached()
            ->filter(fn ($server) => $server->isFunctional())
            ->flatMap(function ($server) use ($environments, $maskIp) {
                // Server can be target for any environment
                // We create entries for each environment to enable environment-server pairing
                return $environments->map(function ($env) use ($server, $maskIp) {
                    return [
                        'id' => $server->id,
                        'uuid' => $server->uuid,
                        'name' => $server->name,
                        'ip' => $maskIp ? '[protected]' : $server->ip,
                        'environment_id' => $env['id'],
                        'is_functional' => true,
                    ];
                });
            })
            ->values();

        // Get existing databases that could be targets for data_only mode
        $existingDatabases = [];
        if ($sourceType && $sourceUuid) {
            // Map database type from frontend to model class patterns
            $databaseTypes = [
                'postgresql' => 'StandalonePostgresql',
                'mysql' => 'StandaloneMysql',
                'mariadb' => 'StandaloneMariadb',
                'mongodb' => 'StandaloneMongodb',
                'redis' => 'StandaloneRedis',
                'keydb' => 'StandaloneKeydb',
                'dragonfly' => 'StandaloneDragonfly',
                'clickhouse' => 'StandaloneClickhouse',
            ];

            $targetType = $databaseTypes[$sourceType] ?? null;
            if ($targetType) {
                $modelClass = "App\\Models\\{$targetType}";
                if (class_exists($modelClass)) {
                    $existingDatabases = $modelClass::ownedByCurrentTeam()
                        ->where('uuid', '!=', $sourceUuid) // Exclude source
                        ->with('destination.server')
                        ->get()
                        ->filter(fn ($db) => $db->destination?->server)
                        ->map(function ($db) {
                            return [
                                'id' => $db->id,
                                'uuid' => $db->uuid,
                                'name' => $db->name,
                                'database_type' => $db->database_type ?? class_basename($db),
                                'server_id' => $db->destination->server->id,
                            ];
                        })
                        ->values();
                }
            }
        }

        return response()->json([
            'environments' => $environments,
            'servers' => $servers,
            'existing_databases' => $existingDatabases,
        ]);
    }
}
