<?php

namespace App\Http\Controllers\Api;

use App\Actions\Service\RestartService;
use App\Actions\Service\StartService;
use App\Actions\Service\StopService;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ServiceActionsController extends Controller
{
    #[OA\Get(
        summary: 'Start',
        description: 'Start service. `Post` request is also accepted.',
        path: '/services/{uuid}/start',
        operationId: 'start-service-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                description: 'UUID of the service.',
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
                description: 'Start service.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'message' => ['type' => 'string', 'example' => 'Service starting request queued.'],
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
        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('deploy', $service);

        if (str($service->status)->contains('running')) {
            return response()->json(['message' => 'Service is already running.'], 400);
        }
        StartService::dispatch($service);

        return response()->json(
            [
                'message' => 'Service starting request queued.',
            ],
            200
        );
    }

    #[OA\Get(
        summary: 'Stop',
        description: 'Stop service. `Post` request is also accepted.',
        path: '/services/{uuid}/stop',
        operationId: 'stop-service-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                description: 'UUID of the service.',
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
                description: 'Stop service.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'message' => ['type' => 'string', 'example' => 'Service stopping request queued.'],
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
        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('stop', $service);

        if (str($service->status)->contains('stopped') || str($service->status)->contains('exited')) {
            return response()->json(['message' => 'Service is already stopped.'], 400);
        }
        StopService::dispatch($service);

        return response()->json(
            [
                'message' => 'Service stopping request queued.',
            ],
            200
        );
    }

    #[OA\Get(
        summary: 'Restart',
        description: 'Restart service. `Post` request is also accepted.',
        path: '/services/{uuid}/restart',
        operationId: 'restart-service-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                description: 'UUID of the service.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    format: 'uuid',
                )
            ),
            new OA\Parameter(
                name: 'latest',
                in: 'query',
                description: 'Pull latest images.',
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false,
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Restart service.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                'message' => ['type' => 'string', 'example' => 'Service restaring request queued.'],
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
        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('deploy', $service);

        $pullLatest = $request->boolean('latest');
        RestartService::dispatch($service, $pullLatest);

        return response()->json(
            [
                'message' => 'Service restarting request queued.',
            ],
            200
        );
    }
}
