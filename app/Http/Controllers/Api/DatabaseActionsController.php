<?php

namespace App\Http\Controllers\Api;

use App\Actions\Database\RestartDatabase;
use App\Actions\Database\StartDatabase;
use App\Actions\Database\StopDatabase;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * API controller for database lifecycle actions.
 *
 * Handles start, stop, and restart operations for databases.
 */
class DatabaseActionsController extends Controller
{
    #[OA\Get(
        summary: 'Start',
        description: 'Start database. `Post` request is also accepted.',
        path: '/databases/{uuid}/start',
        operationId: 'start-database-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                description: 'UUID of the database.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    format: 'uuid',
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Start database.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Database starting request queued.'),
                            ]
                        )
                    ),
                ]
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
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function start(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $uuid = $request->route('uuid');
        if (! $uuid) {
            return response()->json(['message' => 'UUID is required.'], 400);
        }
        $database = queryDatabaseByUuidWithinTeam($request->uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $this->authorize('manage', $database);

        if (str($database->status)->contains('running')) {
            return response()->json(['message' => 'Database is already running.'], 400);
        }
        StartDatabase::dispatch($database);

        return response()->json(
            [
                'message' => 'Database starting request queued.',
            ],
            200
        );
    }

    #[OA\Get(
        summary: 'Stop',
        description: 'Stop database. `Post` request is also accepted.',
        path: '/databases/{uuid}/stop',
        operationId: 'stop-database-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                description: 'UUID of the database.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    format: 'uuid',
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stop database.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Database stopping request queued.'),
                            ]
                        )
                    ),
                ]
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
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function stop(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $uuid = $request->route('uuid');
        if (! $uuid) {
            return response()->json(['message' => 'UUID is required.'], 400);
        }
        $database = queryDatabaseByUuidWithinTeam($request->uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $this->authorize('manage', $database);

        if (str($database->status)->contains('stopped') || str($database->status)->contains('exited')) {
            return response()->json(['message' => 'Database is already stopped.'], 400);
        }
        StopDatabase::dispatch($database);

        return response()->json(
            [
                'message' => 'Database stopping request queued.',
            ],
            200
        );
    }

    #[OA\Get(
        summary: 'Restart',
        description: 'Restart database. `Post` request is also accepted.',
        path: '/databases/{uuid}/restart',
        operationId: 'restart-database-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                description: 'UUID of the database.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    format: 'uuid',
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Restart database.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Database restaring request queued.'),
                            ]
                        )
                    ),
                ]
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
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function restart(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $uuid = $request->route('uuid');
        if (! $uuid) {
            return response()->json(['message' => 'UUID is required.'], 400);
        }
        $database = queryDatabaseByUuidWithinTeam($request->uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $this->authorize('manage', $database);

        RestartDatabase::dispatch($database);

        return response()->json(
            [
                'message' => 'Database restarting request queued.',
            ],
            200
        );
    }
}
