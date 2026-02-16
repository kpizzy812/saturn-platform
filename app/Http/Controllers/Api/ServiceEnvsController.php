<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnvironmentVariable;
use App\Models\Service;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ServiceEnvsController extends Controller
{
    private function removeSensitiveData($service)
    {
        $service->makeHidden([
            'id',
            'resourceable',
            'resourceable_id',
            'resourceable_type',
        ]);
        if (request()->attributes->get('can_read_sensitive', false) === false) {
            $service->makeHidden([
                'docker_compose_raw',
                'docker_compose',
                'value',
                'real_value',
            ]);
        }

        return serializeApiResponse($service);
    }

    #[OA\Get(
        summary: 'List Envs',
        description: 'List all envs by service UUID.',
        path: '/services/{uuid}/envs',
        operationId: 'list-envs-by-service-uuid',
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
                description: 'All environment variables by service UUID.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/EnvironmentVariable')
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
    public function index(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('manageEnvironment', $service);

        $envs = $service->environment_variables->map(function ($env) {
            $env->makeHidden([
                'application_id',
                'standalone_clickhouse_id',
                'standalone_dragonfly_id',
                'standalone_keydb_id',
                'standalone_mariadb_id',
                'standalone_mongodb_id',
                'standalone_mysql_id',
                'standalone_postgresql_id',
                'standalone_redis_id',
            ]);

            return $this->removeSensitiveData($env);
        });

        return response()->json($envs);
    }

    #[OA\Post(
        summary: 'Create Env',
        description: 'Create env by service UUID.',
        path: '/services/{uuid}/envs',
        operationId: 'create-env-by-service-uuid',
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
            required: true,
            description: 'Env created.',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'key', type: 'string', description: 'The key of the environment variable.'),
                        new OA\Property(property: 'value', type: 'string', description: 'The value of the environment variable.'),
                        new OA\Property(
                            property: 'is_preview',
                            type: 'boolean',
                            description: 'The flag to indicate if the environment variable is used in preview deployments.',
                        ),
                        new OA\Property(
                            property: 'is_literal',
                            type: 'boolean',
                            description: 'The flag to indicate if the environment variable is a literal, nothing espaced.',
                        ),
                        new OA\Property(
                            property: 'is_multiline',
                            type: 'boolean',
                            description: 'The flag to indicate if the environment variable is multiline.',
                        ),
                        new OA\Property(
                            property: 'is_shown_once',
                            type: 'boolean',
                            description: 'The flag to indicate if the environment variable\'s value is shown on the UI.',
                        ),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Environment variable created.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid', type: 'string', example: 'nc0k04gk8g0cgsk440g0koko'),
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
    public function store(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('manageEnvironment', $service);

        $validator = customApiValidator($request->all(), [
            'key' => 'string|required',
            'value' => 'string|nullable',
            'is_literal' => 'boolean',
            'is_multiline' => 'boolean',
            'is_shown_once' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $key = str($request->key)->trim()->replace(' ', '_')->toString();
        $existingEnv = $service->environment_variables()->where('key', $key)->first();
        if ($existingEnv) {
            return response()->json([
                'message' => 'Environment variable already exists. Use PATCH request to update it.',
            ], 409);
        }

        $env = $service->environment_variables()->create($request->all());

        return response()->json($this->removeSensitiveData($env))->setStatusCode(201);
    }

    #[OA\Patch(
        summary: 'Update Env',
        description: 'Update env by service UUID.',
        path: '/services/{uuid}/envs',
        operationId: 'update-env-by-service-uuid',
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
            description: 'Env updated.',
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        required: ['key', 'value'],
                        properties: [
                            new OA\Property(property: 'key', type: 'string', description: 'The key of the environment variable.'),
                            new OA\Property(property: 'value', type: 'string', description: 'The value of the environment variable.'),
                            new OA\Property(
                                property: 'is_preview',
                                type: 'boolean',
                                description: 'The flag to indicate if the environment variable is used in preview deployments.',
                            ),
                            new OA\Property(
                                property: 'is_literal',
                                type: 'boolean',
                                description: 'The flag to indicate if the environment variable is a literal, nothing espaced.',
                            ),
                            new OA\Property(
                                property: 'is_multiline',
                                type: 'boolean',
                                description: 'The flag to indicate if the environment variable is multiline.',
                            ),
                            new OA\Property(
                                property: 'is_shown_once',
                                type: 'boolean',
                                description: 'The flag to indicate if the environment variable\'s value is shown on the UI.',
                            ),
                        ],
                    ),
                ),
            ],
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Environment variable updated.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Environment variable updated.'),
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

        $this->authorize('manageEnvironment', $service);

        $validator = customApiValidator($request->all(), [
            'key' => 'string|required',
            'value' => 'string|nullable',
            'is_literal' => 'boolean',
            'is_multiline' => 'boolean',
            'is_shown_once' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $key = str($request->key)->trim()->replace(' ', '_')->toString();
        $env = $service->environment_variables()->where('key', $key)->first();
        if (! $env) {
            return response()->json(['message' => 'Environment variable not found.'], 404);
        }

        $env->fill($request->all());
        $env->save();

        return response()->json($this->removeSensitiveData($env))->setStatusCode(201);
    }

    #[OA\Patch(
        summary: 'Update Envs (Bulk)',
        description: 'Update multiple envs by service UUID.',
        path: '/services/{uuid}/envs/bulk',
        operationId: 'update-envs-by-service-uuid',
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
            description: 'Bulk envs updated.',
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        required: ['data'],
                        properties: [
                            new OA\Property(
                                property: 'data',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'key', type: 'string', description: 'The key of the environment variable.'),
                                        new OA\Property(property: 'value', type: 'string', description: 'The value of the environment variable.'),
                                        new OA\Property(
                                            property: 'is_preview',
                                            type: 'boolean',
                                            description: 'The flag to indicate if the environment variable is used in preview deployments.',
                                        ),
                                        new OA\Property(
                                            property: 'is_literal',
                                            type: 'boolean',
                                            description: 'The flag to indicate if the environment variable is a literal, nothing espaced.',
                                        ),
                                        new OA\Property(
                                            property: 'is_multiline',
                                            type: 'boolean',
                                            description: 'The flag to indicate if the environment variable is multiline.',
                                        ),
                                        new OA\Property(
                                            property: 'is_shown_once',
                                            type: 'boolean',
                                            description: 'The flag to indicate if the environment variable\'s value is shown on the UI.',
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ),
            ],
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Environment variables updated.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Environment variables updated.'),
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
    public function bulkUpdate(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('manageEnvironment', $service);

        $bulk_data = $request->get('data');
        if (! $bulk_data) {
            return response()->json(['message' => 'Bulk data is required.'], 400);
        }

        $updatedEnvs = collect();
        foreach ($bulk_data as $item) {
            $validator = customApiValidator($item, [
                'key' => 'string|required',
                'value' => 'string|nullable',
                'is_literal' => 'boolean',
                'is_multiline' => 'boolean',
                'is_shown_once' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }
            $key = str($item['key'])->trim()->replace(' ', '_')->toString();
            $env = $service->environment_variables()->updateOrCreate(
                ['key' => $key],
                $item
            );

            $updatedEnvs->push($this->removeSensitiveData($env));
        }

        return response()->json($updatedEnvs)->setStatusCode(201);
    }

    #[OA\Delete(
        summary: 'Delete Env',
        description: 'Delete env by UUID.',
        path: '/services/{uuid}/envs/{env_uuid}',
        operationId: 'delete-env-by-service-uuid',
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
                name: 'env_uuid',
                in: 'path',
                description: 'UUID of the environment variable.',
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
                description: 'Environment variable deleted.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Environment variable deleted.'),
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
    public function destroy(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('manageEnvironment', $service);

        $env = EnvironmentVariable::where('uuid', $request->env_uuid)
            ->where('resourceable_type', Service::class)
            ->where('resourceable_id', $service->id)
            ->first();

        if (! $env) {
            return response()->json(['message' => 'Environment variable not found.'], 404);
        }

        $env->forceDelete();

        return response()->json(['message' => 'Environment variable deleted.']);
    }
}
