<?php

namespace App\Http\Controllers\Api;

use App\Actions\Transfer\CreateTransferAction;
use App\Actions\Transfer\GetDatabaseStructureAction;
use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\ResourceTransfer;
use App\Models\Server;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * API controller for resource transfers.
 *
 * Handles listing, creating, viewing, and cancelling transfers.
 */
class ResourceTransferController extends Controller
{
    #[OA\Get(
        summary: 'List Transfers',
        description: 'List all resource transfers for the current team.',
        path: '/transfers',
        operationId: 'list-transfers',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Transfers'],
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                description: 'Filter by status',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['pending', 'preparing', 'transferring', 'restoring', 'completed', 'failed', 'cancelled'])
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of transfers',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
        ]
    )]
    public function index(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $query = ResourceTransfer::where('team_id', $teamId)
            ->with(['source', 'target', 'targetEnvironment', 'targetServer', 'user'])
            ->orderByDesc('created_at');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transfers = $query->paginate(20);

        return response()->json($transfers);
    }

    #[OA\Post(
        summary: 'Create Transfer',
        description: 'Create a new resource transfer.',
        path: '/transfers',
        operationId: 'create-transfer',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Transfers'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['source_uuid', 'source_type', 'target_environment_uuid', 'target_server_uuid'],
                    properties: [
                        new OA\Property(property: 'source_uuid', type: 'string', description: 'UUID of the source database'),
                        new OA\Property(property: 'source_type', type: 'string', description: 'Type of source (standalone-postgresql, standalone-mysql, etc.)'),
                        new OA\Property(property: 'target_environment_uuid', type: 'string', description: 'UUID of the target environment'),
                        new OA\Property(property: 'target_server_uuid', type: 'string', description: 'UUID of the target server'),
                        new OA\Property(property: 'transfer_mode', type: 'string', enum: ['clone', 'data_only', 'partial'], default: 'clone'),
                        new OA\Property(property: 'transfer_options', type: 'object', description: 'Options for partial transfer'),
                        new OA\Property(property: 'existing_target_uuid', type: 'string', description: 'UUID of existing target database (for data_only mode)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Transfer created',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 400,
                ref: '#/components/responses/400',
            ),
            new OA\Response(
                response: 422,
                ref: '#/components/responses/422',
            ),
        ]
    )]
    public function store(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // Validate request
        $validator = customApiValidator($request->all(), [
            'source_uuid' => 'required|string',
            'source_type' => 'required|string',
            'target_environment_uuid' => 'required|string',
            'target_server_uuid' => 'required|string',
            'transfer_mode' => 'string|in:clone,data_only,partial',
            'transfer_options' => 'nullable|array',
            'existing_target_uuid' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find source database
        $sourceDatabase = queryDatabaseByUuidWithinTeam($request->source_uuid, $teamId);
        if (! $sourceDatabase) {
            return response()->json(['message' => 'Source database not found.'], 404);
        }

        // Find target environment
        $targetEnvironment = Environment::whereRelation('project.team', 'id', $teamId)
            ->where('uuid', $request->target_environment_uuid)
            ->first();
        if (! $targetEnvironment) {
            return response()->json(['message' => 'Target environment not found.'], 404);
        }

        // Find target server
        $targetServer = Server::whereTeamId($teamId)
            ->where('uuid', $request->target_server_uuid)
            ->first();
        if (! $targetServer) {
            return response()->json(['message' => 'Target server not found.'], 404);
        }

        // Create transfer
        $action = new CreateTransferAction;
        $result = $action->execute(
            sourceDatabase: $sourceDatabase,
            targetEnvironment: $targetEnvironment,
            targetServer: $targetServer,
            transferMode: $request->transfer_mode ?? ResourceTransfer::MODE_CLONE,
            transferOptions: $request->transfer_options,
            existingTargetUuid: $request->existing_target_uuid
        );

        if (! $result['success']) {
            return response()->json([
                'message' => $result['error'],
            ], 400);
        }

        return response()->json([
            'message' => $result['requires_approval'] ? 'Transfer created, awaiting approval.' : 'Transfer created.',
            'transfer' => $result['transfer'],
            'requires_approval' => $result['requires_approval'] ?? false,
        ], 201);
    }

    #[OA\Get(
        summary: 'Get Transfer',
        description: 'Get a transfer by UUID.',
        path: '/transfers/{uuid}',
        operationId: 'get-transfer',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Transfers'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transfer details',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function show(Request $request, string $uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $transfer = ResourceTransfer::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->with(['source', 'target', 'targetEnvironment', 'targetServer', 'user'])
            ->first();

        if (! $transfer) {
            return response()->json(['message' => 'Transfer not found.'], 404);
        }

        return response()->json($transfer);
    }

    #[OA\Post(
        summary: 'Cancel Transfer',
        description: 'Cancel a transfer that is in progress.',
        path: '/transfers/{uuid}/cancel',
        operationId: 'cancel-transfer',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Transfers'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transfer cancelled',
            ),
            new OA\Response(
                response: 400,
                description: 'Transfer cannot be cancelled',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function cancel(Request $request, string $uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $transfer = ResourceTransfer::where('team_id', $teamId)
            ->where('uuid', $uuid)
            ->first();

        if (! $transfer) {
            return response()->json(['message' => 'Transfer not found.'], 404);
        }

        if (! $transfer->canBeCancelled()) {
            return response()->json([
                'message' => 'Transfer cannot be cancelled in its current state.',
            ], 400);
        }

        $transfer->markAsCancelled();

        return response()->json([
            'message' => 'Transfer cancelled.',
            'transfer' => $transfer,
        ]);
    }

    #[OA\Get(
        summary: 'Get Database Structure',
        description: 'Get the structure (tables/collections/keys) of a database for partial transfer selection.',
        path: '/databases/{uuid}/structure',
        operationId: 'get-database-structure',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Transfers'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Database structure',
            ),
            new OA\Response(
                response: 401,
                ref: '#/components/responses/401',
            ),
            new OA\Response(
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function structure(Request $request, string $uuid)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $database = queryDatabaseByUuidWithinTeam($uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $action = new GetDatabaseStructureAction;
        $result = $action->execute($database);

        if (! $result['success']) {
            return response()->json([
                'message' => $result['error'],
            ], 400);
        }

        return response()->json($result);
    }
}
