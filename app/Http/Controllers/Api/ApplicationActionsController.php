<?php

namespace App\Http\Controllers\Api;

use App\Actions\Application\StopApplication;
use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Visus\Cuid2\Cuid2;

class ApplicationActionsController extends Controller
{
    #[OA\Get(
        summary: 'Start',
        description: 'Start application. `Post` request is also accepted.',
        path: '/applications/{uuid}/start',
        operationId: 'start-application-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Applications'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                description: 'UUID of the application.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    format: 'uuid',
                )
            ),
            new OA\Parameter(
                name: 'force',
                in: 'query',
                description: 'Force rebuild.',
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false,
                )
            ),
            new OA\Parameter(
                name: 'instant_deploy',
                in: 'query',
                description: 'Instant deploy (skip queuing).',
                schema: new OA\Schema(
                    type: 'boolean',
                    default: false,
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Start application.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Deployment request queued.', description: 'Message.'),
                                new OA\Property(property: 'deployment_uuid', type: 'string', example: 'doogksw', description: 'UUID of the deployment.'),
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
    public function action_deploy(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $force = $request->boolean('force', false);
        $instant_deploy = $request->boolean('instant_deploy', false);
        $uuid = $request->route('uuid');
        if (! $uuid) {
            return response()->json(['message' => 'UUID is required.'], 400);
        }
        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $request->uuid)->first();
        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        $this->authorize('deploy', $application);

        $deployment_uuid = new Cuid2;

        $result = queue_application_deployment(
            application: $application,
            deployment_uuid: $deployment_uuid,
            force_rebuild: $force,
            is_api: true,
            no_questions_asked: $instant_deploy
        );
        if ($result['status'] === 'skipped') {
            return response()->json(
                [
                    'message' => $result['message'],
                ],
                200
            );
        }

        return response()->json(
            [
                'message' => 'Deployment request queued.',
                'deployment_uuid' => $deployment_uuid->toString(),
            ],
            200
        );
    }

    #[OA\Get(
        summary: 'Stop',
        description: 'Stop application. `Post` request is also accepted.',
        path: '/applications/{uuid}/stop',
        operationId: 'stop-application-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Applications'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                description: 'UUID of the application.',
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
                description: 'Stop application.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Application stopping request queued.'),
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
    public function action_stop(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $uuid = $request->route('uuid');
        if (! $uuid) {
            return response()->json(['message' => 'UUID is required.'], 400);
        }
        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $request->uuid)->first();
        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        $this->authorize('deploy', $application);

        StopApplication::dispatch($application);

        return response()->json(
            [
                'message' => 'Application stopping request queued.',
            ],
        );
    }

    #[OA\Get(
        summary: 'Restart',
        description: 'Restart application. `Post` request is also accepted.',
        path: '/applications/{uuid}/restart',
        operationId: 'restart-application-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Applications'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                description: 'UUID of the application.',
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
                description: 'Restart application.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Restart request queued.'),
                                new OA\Property(property: 'deployment_uuid', type: 'string', example: 'doogksw', description: 'UUID of the deployment.'),
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
    public function action_restart(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $uuid = $request->route('uuid');
        if (! $uuid) {
            return response()->json(['message' => 'UUID is required.'], 400);
        }
        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $request->uuid)->first();
        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        $this->authorize('deploy', $application);

        $deployment_uuid = new Cuid2;

        $result = queue_application_deployment(
            application: $application,
            deployment_uuid: $deployment_uuid,
            restart_only: true,
            is_api: true,
        );
        if ($result['status'] === 'skipped') {
            return response()->json([
                'message' => $result['message'],
            ], 200);
        }

        return response()->json(
            [
                'message' => 'Restart request queued.',
                'deployment_uuid' => $deployment_uuid->toString(),
            ],
        );
    }
}
