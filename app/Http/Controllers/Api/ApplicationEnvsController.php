<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\EnvironmentVariable;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ApplicationEnvsController extends Controller
{
    private function removeSensitiveData($resource)
    {
        $resource->makeHidden([
            'id',
            'resourceable',
            'resourceable_id',
            'resourceable_type',
        ]);
        if (request()->attributes->get('can_read_sensitive', false) === false) {
            $resource->makeHidden([
                'custom_labels',
                'dockerfile',
                'docker_compose',
                'docker_compose_raw',
                'manual_webhook_secret_bitbucket',
                'manual_webhook_secret_gitea',
                'manual_webhook_secret_github',
                'manual_webhook_secret_gitlab',
                'private_key_id',
                'value',
                'real_value',
                'http_basic_auth_password',
            ]);
        }

        return serializeApiResponse($resource);
    }

    #[OA\Get(
        summary: 'List Envs',
        description: 'List all envs by application UUID.',
        path: '/applications/{uuid}/envs',
        operationId: 'list-envs-by-application-uuid',
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
                description: 'All environment variables by application UUID.',
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
    public function envs(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $request->uuid)->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found',
            ], 404);
        }

        $this->authorize('view', $application);

        $envs = $application->environment_variables->sortBy('id')->merge($application->environment_variables_preview->sortBy('id'));

        $envs = $envs->map(function ($env) {
            $env->makeHidden([
                'service_id',
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

    #[OA\Patch(
        summary: 'Update Env',
        description: 'Update env by application UUID.',
        path: '/applications/{uuid}/envs',
        operationId: 'update-env-by-application-uuid',
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
                            'key' => ['type' => 'string', 'description' => 'The key of the environment variable.'],
                            'value' => ['type' => 'string', 'description' => 'The value of the environment variable.'],
                            'is_preview' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable is used in preview deployments.'],
                            'is_literal' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable is a literal, nothing espaced.'],
                            'is_multiline' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable is multiline.'],
                            'is_shown_once' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable\'s value is shown on the UI.'],
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
                                'message' => ['type' => 'string', 'example' => 'Environment variable updated.'],
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
    public function update_env_by_uuid(Request $request)
    {
        $allowedFields = ['key', 'value', 'is_preview', 'is_literal', 'is_multiline', 'is_shown_once', 'is_runtime', 'is_buildtime'];
        $teamId = getTeamIdFromToken();

        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $return = validateIncomingRequest($request);
        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }
        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $request->uuid)->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found',
            ], 404);
        }

        $this->authorize('manageEnvironment', $application);

        $validator = customApiValidator($request->all(), [
            'key' => 'string|required',
            'value' => 'string|nullable',
            'is_preview' => 'boolean',
            'is_literal' => 'boolean',
            'is_multiline' => 'boolean',
            'is_shown_once' => 'boolean',
            'is_runtime' => 'boolean',
            'is_buildtime' => 'boolean',
        ]);

        $extraFields = array_diff(array_keys($request->all()), $allowedFields);
        if ($validator->fails() || ! empty($extraFields)) {
            $errors = $validator->errors();
            if (! empty($extraFields)) {
                foreach ($extraFields as $field) {
                    $errors->add($field, 'This field is not allowed.');
                }
            }

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }
        $is_preview = $request->is_preview ?? false;
        $is_literal = $request->is_literal ?? false;
        $key = str($request->key)->trim()->replace(' ', '_')->value;
        if ($is_preview) {
            $env = $application->environment_variables_preview->where('key', $key)->first();
            if ($env) {
                $env->value = $request->value;
                if ($env->is_literal != $is_literal) {
                    $env->is_literal = $is_literal;
                }
                if ($env->is_preview != $is_preview) {
                    $env->is_preview = $is_preview;
                }
                if ($env->is_multiline != $request->is_multiline) {
                    $env->is_multiline = $request->is_multiline;
                }
                if ($env->is_shown_once != $request->is_shown_once) {
                    $env->is_shown_once = $request->is_shown_once;
                }
                if ($request->has('is_runtime') && $env->is_runtime != $request->is_runtime) {
                    $env->is_runtime = $request->is_runtime;
                }
                if ($request->has('is_buildtime') && $env->is_buildtime != $request->is_buildtime) {
                    $env->is_buildtime = $request->is_buildtime;
                }
                $env->save();

                return response()->json($this->removeSensitiveData($env))->setStatusCode(201);
            } else {
                return response()->json([
                    'message' => 'Environment variable not found.',
                ], 404);
            }
        } else {
            $env = $application->environment_variables->where('key', $key)->first();
            if ($env) {
                $env->value = $request->value;
                if ($env->is_literal != $is_literal) {
                    $env->is_literal = $is_literal;
                }
                if ($env->is_preview != $is_preview) {
                    $env->is_preview = $is_preview;
                }
                if ($env->is_multiline != $request->is_multiline) {
                    $env->is_multiline = $request->is_multiline;
                }
                if ($env->is_shown_once != $request->is_shown_once) {
                    $env->is_shown_once = $request->is_shown_once;
                }
                if ($request->has('is_runtime') && $env->is_runtime != $request->is_runtime) {
                    $env->is_runtime = $request->is_runtime;
                }
                if ($request->has('is_buildtime') && $env->is_buildtime != $request->is_buildtime) {
                    $env->is_buildtime = $request->is_buildtime;
                }
                $env->save();

                return response()->json($this->removeSensitiveData($env))->setStatusCode(201);
            } else {
                return response()->json([
                    'message' => 'Environment variable not found.',
                ], 404);
            }
        }

        return response()->json([
            'message' => 'Something is not okay. Are you okay?',
        ], 500);
    }

    #[OA\Patch(
        summary: 'Update Envs (Bulk)',
        description: 'Update multiple envs by application UUID.',
        path: '/applications/{uuid}/envs/bulk',
        operationId: 'update-envs-by-application-uuid',
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
                            'data' => [
                                'type' => 'array',
                                'items' => new OA\Schema(
                                    type: 'object',
                                    properties: [
                                        'key' => ['type' => 'string', 'description' => 'The key of the environment variable.'],
                                        'value' => ['type' => 'string', 'description' => 'The value of the environment variable.'],
                                        'is_preview' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable is used in preview deployments.'],
                                        'is_literal' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable is a literal, nothing espaced.'],
                                        'is_multiline' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable is multiline.'],
                                        'is_shown_once' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable\'s value is shown on the UI.'],
                                    ],
                                ),
                            ],
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
                                'message' => ['type' => 'string', 'example' => 'Environment variables updated.'],
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
    public function create_bulk_envs(Request $request)
    {
        $teamId = getTeamIdFromToken();

        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $return = validateIncomingRequest($request);
        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }
        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $request->uuid)->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found',
            ], 404);
        }

        $this->authorize('manageEnvironment', $application);

        $bulk_data = $request->get('data');
        if (! $bulk_data) {
            return response()->json([
                'message' => 'Bulk data is required.',
            ], 400);
        }
        $bulk_data = collect($bulk_data)->map(function ($item) {
            return collect($item)->only(['key', 'value', 'is_preview', 'is_literal', 'is_multiline', 'is_shown_once', 'is_runtime', 'is_buildtime']);
        });
        $returnedEnvs = collect();
        foreach ($bulk_data as $item) {
            $validator = customApiValidator($item, [
                'key' => 'string|required',
                'value' => 'string|nullable',
                'is_preview' => 'boolean',
                'is_literal' => 'boolean',
                'is_multiline' => 'boolean',
                'is_shown_once' => 'boolean',
                'is_runtime' => 'boolean',
                'is_buildtime' => 'boolean',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }
            $is_preview = $item->get('is_preview') ?? false;
            $is_literal = $item->get('is_literal') ?? false;
            $is_multi_line = $item->get('is_multiline') ?? false;
            $is_shown_once = $item->get('is_shown_once') ?? false;
            $key = str($item->get('key'))->trim()->replace(' ', '_')->value;
            if ($is_preview) {
                $env = $application->environment_variables_preview->where('key', $key)->first();
                if ($env) {
                    $env->value = $item->get('value');

                    if ($env->is_literal != $is_literal) {
                        $env->is_literal = $is_literal;
                    }
                    if ($env->is_multiline != $item->get('is_multiline')) {
                        $env->is_multiline = $item->get('is_multiline');
                    }
                    if ($env->is_shown_once != $item->get('is_shown_once')) {
                        $env->is_shown_once = $item->get('is_shown_once');
                    }
                    if ($item->has('is_runtime') && $env->is_runtime != $item->get('is_runtime')) {
                        $env->is_runtime = $item->get('is_runtime');
                    }
                    if ($item->has('is_buildtime') && $env->is_buildtime != $item->get('is_buildtime')) {
                        $env->is_buildtime = $item->get('is_buildtime');
                    }
                    $env->save();
                } else {
                    $env = $application->environment_variables()->create([
                        'key' => $item->get('key'),
                        'value' => $item->get('value'),
                        'is_preview' => $is_preview,
                        'is_literal' => $is_literal,
                        'is_multiline' => $is_multi_line,
                        'is_shown_once' => $is_shown_once,
                        'is_runtime' => $item->get('is_runtime', true),
                        'is_buildtime' => $item->get('is_buildtime', true),
                        'resourceable_type' => get_class($application),
                        'resourceable_id' => $application->id,
                    ]);
                }
            } else {
                $env = $application->environment_variables->where('key', $key)->first();
                if ($env) {
                    $env->value = $item->get('value');
                    if ($env->is_literal != $is_literal) {
                        $env->is_literal = $is_literal;
                    }
                    if ($env->is_multiline != $item->get('is_multiline')) {
                        $env->is_multiline = $item->get('is_multiline');
                    }
                    if ($env->is_shown_once != $item->get('is_shown_once')) {
                        $env->is_shown_once = $item->get('is_shown_once');
                    }
                    if ($item->has('is_runtime') && $env->is_runtime != $item->get('is_runtime')) {
                        $env->is_runtime = $item->get('is_runtime');
                    }
                    if ($item->has('is_buildtime') && $env->is_buildtime != $item->get('is_buildtime')) {
                        $env->is_buildtime = $item->get('is_buildtime');
                    }
                    $env->save();
                } else {
                    $env = $application->environment_variables()->create([
                        'key' => $item->get('key'),
                        'value' => $item->get('value'),
                        'is_preview' => $is_preview,
                        'is_literal' => $is_literal,
                        'is_multiline' => $is_multi_line,
                        'is_shown_once' => $is_shown_once,
                        'is_runtime' => $item->get('is_runtime', true),
                        'is_buildtime' => $item->get('is_buildtime', true),
                        'resourceable_type' => get_class($application),
                        'resourceable_id' => $application->id,
                    ]);
                }
            }
            $returnedEnvs->push($this->removeSensitiveData($env));
        }

        return response()->json($returnedEnvs)->setStatusCode(201);
    }

    #[OA\Post(
        summary: 'Create Env',
        description: 'Create env by application UUID.',
        path: '/applications/{uuid}/envs',
        operationId: 'create-env-by-application-uuid',
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
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Env created.',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        'key' => ['type' => 'string', 'description' => 'The key of the environment variable.'],
                        'value' => ['type' => 'string', 'description' => 'The value of the environment variable.'],
                        'is_preview' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable is used in preview deployments.'],
                        'is_literal' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable is a literal, nothing espaced.'],
                        'is_multiline' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable is multiline.'],
                        'is_shown_once' => ['type' => 'boolean', 'description' => 'The flag to indicate if the environment variable\'s value is shown on the UI.'],
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
                                'uuid' => ['type' => 'string', 'example' => 'nc0k04gk8g0cgsk440g0koko'],
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
    public function create_env(Request $request)
    {
        $allowedFields = ['key', 'value', 'is_preview', 'is_literal', 'is_multiline', 'is_shown_once', 'is_runtime', 'is_buildtime'];
        $teamId = getTeamIdFromToken();

        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $request->uuid)->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found',
            ], 404);
        }

        $this->authorize('manageEnvironment', $application);

        $validator = customApiValidator($request->all(), [
            'key' => 'string|required',
            'value' => 'string|nullable',
            'is_preview' => 'boolean',
            'is_literal' => 'boolean',
            'is_multiline' => 'boolean',
            'is_shown_once' => 'boolean',
            'is_runtime' => 'boolean',
            'is_buildtime' => 'boolean',
        ]);

        $extraFields = array_diff(array_keys($request->all()), $allowedFields);
        if ($validator->fails() || ! empty($extraFields)) {
            $errors = $validator->errors();
            if (! empty($extraFields)) {
                foreach ($extraFields as $field) {
                    $errors->add($field, 'This field is not allowed.');
                }
            }

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }
        $is_preview = $request->is_preview ?? false;
        $key = str($request->key)->trim()->replace(' ', '_')->value;

        if ($is_preview) {
            $env = $application->environment_variables_preview->where('key', $key)->first();
            if ($env) {
                return response()->json([
                    'message' => 'Environment variable already exists. Use PATCH request to update it.',
                ], 409);
            } else {
                $env = $application->environment_variables()->create([
                    'key' => $request->key,
                    'value' => $request->value,
                    'is_preview' => $request->is_preview ?? false,
                    'is_literal' => $request->is_literal ?? false,
                    'is_multiline' => $request->is_multiline ?? false,
                    'is_shown_once' => $request->is_shown_once ?? false,
                    'is_runtime' => $request->is_runtime ?? true,
                    'is_buildtime' => $request->is_buildtime ?? true,
                    'resourceable_type' => get_class($application),
                    'resourceable_id' => $application->id,
                ]);

                return response()->json([
                    'uuid' => $env->uuid,
                ])->setStatusCode(201);
            }
        } else {
            $env = $application->environment_variables->where('key', $key)->first();
            if ($env) {
                return response()->json([
                    'message' => 'Environment variable already exists. Use PATCH request to update it.',
                ], 409);
            } else {
                $env = $application->environment_variables()->create([
                    'key' => $request->key,
                    'value' => $request->value,
                    'is_preview' => $request->is_preview ?? false,
                    'is_literal' => $request->is_literal ?? false,
                    'is_multiline' => $request->is_multiline ?? false,
                    'is_shown_once' => $request->is_shown_once ?? false,
                    'is_runtime' => $request->is_runtime ?? true,
                    'is_buildtime' => $request->is_buildtime ?? true,
                    'resourceable_type' => get_class($application),
                    'resourceable_id' => $application->id,
                ]);

                return response()->json([
                    'uuid' => $env->uuid,
                ])->setStatusCode(201);
            }
        }
    }

    #[OA\Delete(
        summary: 'Delete Env',
        description: 'Delete env by UUID.',
        path: '/applications/{uuid}/envs/{env_uuid}',
        operationId: 'delete-env-by-application-uuid',
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
                                'message' => ['type' => 'string', 'example' => 'Environment variable deleted.'],
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
    public function delete_env_by_uuid(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $request->uuid)->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found.',
            ], 404);
        }

        $this->authorize('manageEnvironment', $application);

        $found_env = EnvironmentVariable::where('uuid', $request->env_uuid)
            ->where('resourceable_type', Application::class)
            ->where('resourceable_id', $application->id)
            ->first();
        if (! $found_env) {
            return response()->json([
                'message' => 'Environment variable not found.',
            ], 404);
        }
        $found_env->forceDelete();

        return response()->json([
            'message' => 'Environment variable deleted.',
        ]);
    }
}
