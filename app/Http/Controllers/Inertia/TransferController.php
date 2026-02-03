<?php

namespace App\Http\Controllers\Inertia;

use App\Actions\Transfer\CreateTransferAction;
use App\Actions\Transfer\GetDatabaseStructureAction;
use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\ResourceTransfer;
use App\Models\Server;
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
            'source_uuid' => 'required|string',
            'target_environment_id' => 'required|integer',
            'target_server_id' => 'required|integer',
            'transfer_mode' => 'required|string|in:clone,data_only,partial',
            'transfer_options' => 'nullable|array',
            'target_uuid' => 'nullable|string',
        ]);

        // Find source database
        $sourceDatabase = queryDatabaseByUuidWithinTeam($validated['source_uuid'], currentTeam()->id);
        if (! $sourceDatabase) {
            return back()->withErrors(['source_uuid' => 'Source database not found']);
        }

        // Find target environment
        $targetEnvironment = Environment::ownedByCurrentTeam()
            ->find($validated['target_environment_id']);
        if (! $targetEnvironment) {
            return back()->withErrors(['target_environment_id' => 'Target environment not found']);
        }

        // Find target server
        $targetServer = Server::ownedByCurrentTeamCached()
            ->firstWhere('id', $validated['target_server_id']);
        if (! $targetServer) {
            return back()->withErrors(['target_server_id' => 'Target server not found']);
        }

        // Create transfer
        $action = new CreateTransferAction;
        $result = $action->execute(
            sourceDatabase: $sourceDatabase,
            targetEnvironment: $targetEnvironment,
            targetServer: $targetServer,
            transferMode: $validated['transfer_mode'],
            transferOptions: $validated['transfer_options'] ?? null,
            existingTargetUuid: $validated['target_uuid'] ?? null
        );

        if (! $result['success']) {
            return back()->withErrors(['general' => $result['error']]);
        }

        return redirect()->route('transfers.show', $result['transfer']->uuid)
            ->with('success', 'Transfer started successfully');
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
        $servers = Server::ownedByCurrentTeamCached()
            ->filter(fn ($server) => $server->isFunctional())
            ->flatMap(function ($server) use ($environments) {
                // Server can be target for any environment
                // We create entries for each environment to enable environment-server pairing
                return $environments->map(function ($env) use ($server) {
                    return [
                        'id' => $server->id,
                        'uuid' => $server->uuid,
                        'name' => $server->name,
                        'ip' => $server->ip,
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
