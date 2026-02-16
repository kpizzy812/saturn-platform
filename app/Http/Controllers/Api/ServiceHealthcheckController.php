<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ServiceHealthcheckController extends Controller
{
    #[OA\Get(
        summary: 'Get Healthcheck',
        description: 'Get healthcheck configuration for a service.',
        path: '/services/{uuid}/healthcheck',
        operationId: 'get-service-healthcheck',
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
                description: 'Healthcheck configuration.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'enabled', type: 'boolean', description: 'Whether healthcheck is enabled.'),
                                new OA\Property(property: 'type', type: 'string', description: 'Healthcheck type (http or tcp).'),
                                new OA\Property(property: 'test', type: 'string', description: 'Healthcheck test command.'),
                                new OA\Property(property: 'interval', type: 'integer', description: 'Healthcheck interval in seconds.'),
                                new OA\Property(property: 'timeout', type: 'integer', description: 'Healthcheck timeout in seconds.'),
                                new OA\Property(property: 'retries', type: 'integer', description: 'Number of retries before unhealthy.'),
                                new OA\Property(property: 'start_period', type: 'integer', description: 'Start period in seconds.'),
                                new OA\Property(property: 'service_name', type: 'string', nullable: true, description: 'Name of the docker service.'),
                                new OA\Property(property: 'status', type: 'string', description: 'Current service status.'),
                                new OA\Property(property: 'services_status', type: 'object', description: 'Healthcheck status per service.'),
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
                response: 404,
                ref: '#/components/responses/404',
            ),
        ]
    )]
    public function show(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('view', $service);

        $config = $service->getHealthcheckConfig();
        $config['status'] = $service->status;
        $config['services_status'] = $service->getServicesHealthcheckStatus();

        return response()->json($config);
    }

    #[OA\Patch(
        summary: 'Update Healthcheck',
        description: 'Update healthcheck configuration for a service.',
        path: '/services/{uuid}/healthcheck',
        operationId: 'update-service-healthcheck',
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
        requestBody: new OA\RequestBody(
            description: 'Healthcheck configuration.',
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'enabled', type: 'boolean', description: 'Whether healthcheck is enabled.'),
                            new OA\Property(property: 'type', type: 'string', enum: ['http', 'tcp'], description: 'Healthcheck type.'),
                            new OA\Property(property: 'test', type: 'string', description: 'Healthcheck test command.'),
                            new OA\Property(property: 'interval', type: 'integer', description: 'Healthcheck interval in seconds.'),
                            new OA\Property(property: 'timeout', type: 'integer', description: 'Healthcheck timeout in seconds.'),
                            new OA\Property(property: 'retries', type: 'integer', description: 'Number of retries before unhealthy.'),
                            new OA\Property(property: 'start_period', type: 'integer', description: 'Start period in seconds.'),
                            new OA\Property(property: 'service_name', type: 'string', nullable: true, description: 'Target docker service name.'),
                        ],
                    ),
                ),
            ],
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Healthcheck configuration updated.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Healthcheck configuration updated.'),
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
            new OA\Response(
                response: 422,
                ref: '#/components/responses/422',
            ),
        ]
    )]
    public function update(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('update', $service);

        $validator = customApiValidator($request->all(), [
            'enabled' => 'boolean',
            'type' => 'string|in:http,tcp',
            'test' => 'string|nullable',
            'interval' => 'integer|min:1',
            'timeout' => 'integer|min:1',
            'retries' => 'integer|min:1',
            'start_period' => 'integer|min:0',
            'service_name' => 'string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $config = $request->only([
            'enabled',
            'type',
            'test',
            'interval',
            'timeout',
            'retries',
            'start_period',
            'service_name',
        ]);

        $success = $service->setHealthcheckConfig($config);

        if (! $success) {
            return response()->json(['message' => 'Failed to update healthcheck configuration.'], 400);
        }

        return response()->json(['message' => 'Healthcheck configuration updated.']);
    }
}
