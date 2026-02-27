<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DeleteResourceJob;
use App\Models\Application;
use App\Models\Server;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\Yaml\Yaml;
use Visus\Cuid2\Cuid2;

class ApplicationsController extends Controller
{
    private function removeSensitiveData($application)
    {
        $application->makeHidden([
            'id',
            'resourceable',
            'resourceable_id',
            'resourceable_type',
        ]);
        if (request()->attributes->get('can_read_sensitive', false) === false) {
            $application->makeHidden([
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

        return serializeApiResponse($application);
    }

    #[OA\Get(
        summary: 'List',
        description: 'List all applications.',
        path: '/applications',
        operationId: 'list-applications',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Applications'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Get all applications.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Application')
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
        ]
    )]
    public function applications(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $perPage = (int) $request->query('per_page', 0);
        // Eager-load relations required by serverStatus() accessor and API serialization.
        // Without this, each Application triggers separate queries for destination/server.
        $baseQuery = Application::ownedByCurrentTeamAPI($teamId)
            ->with(['environment.project', 'destination.server.settings']);

        if ($perPage > 0) {
            $perPage = min($perPage, 100);
            $paginator = $baseQuery->paginate($perPage);

            return response()->json([
                'data' => $paginator->getCollection()->map(fn ($app) => $this->removeSensitiveData($app))->values(),
                'meta' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        }

        $applications = $baseQuery
            ->limit(500)
            ->get()
            ->map(fn ($app) => $this->removeSensitiveData($app));

        return response()->json($applications->values());
    }

    #[OA\Get(
        summary: 'Get',
        description: 'Get application by UUID.',
        path: '/applications/{uuid}',
        operationId: 'get-application-by-uuid',
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
                description: 'Get application by UUID.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            ref: '#/components/schemas/Application'
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
    public function application_by_uuid(Request $request)
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

        $this->authorize('view', $application);

        return response()->json($this->removeSensitiveData($application));
    }

    #[OA\Get(
        summary: 'Get application logs.',
        description: 'Get application logs by UUID.',
        path: '/applications/{uuid}/logs',
        operationId: 'get-application-logs-by-uuid',
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
                name: 'lines',
                in: 'query',
                description: 'Number of lines to show from the end of the logs.',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    format: 'int32',
                    default: 100,
                )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Get application logs by UUID.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'logs', type: 'string'),
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
    public function logs_by_uuid(Request $request)
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

        try {
            $containers = getCurrentApplicationContainerStatus($application->destination->server, $application->id);

            if ($containers->count() == 0) {
                return response()->json([
                    'message' => 'Application is not running.',
                ], 400);
            }

            $container = $containers->first();

            $status = getContainerStatus($application->destination->server, $container['Names']);
            if ($status !== 'running') {
                return response()->json([
                    'message' => 'Application is not running.',
                ], 400);
            }

            $lines = $request->query->get('lines', 100) ?: 100;
            $logs = getContainerLogs($application->destination->server, $container['ID'], $lines);

            return response()->json([
                'logs' => $logs,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to connect to server: '.$e->getMessage()], 500);
        }
    }

    #[OA\Delete(
        summary: 'Delete',
        description: 'Delete application by UUID.',
        path: '/applications/{uuid}',
        operationId: 'delete-application-by-uuid',
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
            new OA\Parameter(name: 'delete_configurations', in: 'query', required: false, description: 'Delete configurations.', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'delete_volumes', in: 'query', required: false, description: 'Delete volumes.', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'docker_cleanup', in: 'query', required: false, description: 'Run docker cleanup.', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'delete_connected_networks', in: 'query', required: false, description: 'Delete connected networks.', schema: new OA\Schema(type: 'boolean', default: true)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Application deleted.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Application deleted.'),
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
    public function delete_by_uuid(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        if (! $request->uuid) {
            return response()->json(['message' => 'UUID is required.'], 404);
        }
        $application = Application::ownedByCurrentTeamAPI($teamId)->where('uuid', $request->uuid)->first();

        if (! $application) {
            return response()->json([
                'message' => 'Application not found',
            ], 404);
        }

        $this->authorize('delete', $application);

        DeleteResourceJob::dispatch(
            resource: $application,
            deleteVolumes: $request->boolean('delete_volumes', true),
            deleteConnectedNetworks: $request->boolean('delete_connected_networks', true),
            deleteConfigurations: $request->boolean('delete_configurations', true),
            dockerCleanup: $request->boolean('docker_cleanup', true)
        );

        return response()->json([
            'message' => 'Application deletion request queued.',
        ]);
    }

    #[OA\Patch(
        summary: 'Update',
        description: 'Update application by UUID.',
        path: '/applications/{uuid}',
        operationId: 'update-application-by-uuid',
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
            description: 'Application updated.',
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'project_uuid', type: 'string', description: 'The project UUID.'),
                            new OA\Property(property: 'server_uuid', type: 'string', description: 'The server UUID.'),
                            new OA\Property(property: 'environment_name', type: 'string', description: 'The environment name.'),
                            new OA\Property(property: 'github_app_uuid', type: 'string', description: 'The Github App UUID.'),
                            new OA\Property(property: 'git_repository', type: 'string', description: 'The git repository URL.'),
                            new OA\Property(property: 'git_branch', type: 'string', description: 'The git branch.'),
                            new OA\Property(property: 'ports_exposes', type: 'string', description: 'The ports to expose.'),
                            new OA\Property(property: 'destination_uuid', type: 'string', description: 'The destination UUID.'),
                            new OA\Property(
                                property: 'build_pack',
                                type: 'string',
                                enum: ['nixpacks', 'static', 'dockerfile', 'dockercompose'],
                                description: 'The build pack type.',
                            ),
                            new OA\Property(property: 'name', type: 'string', description: 'The application name.'),
                            new OA\Property(property: 'description', type: 'string', description: 'The application description.'),
                            new OA\Property(property: 'domains', type: 'string', description: 'The application domains.'),
                            new OA\Property(property: 'git_commit_sha', type: 'string', description: 'The git commit SHA.'),
                            new OA\Property(property: 'docker_registry_image_name', type: 'string', description: 'The docker registry image name.'),
                            new OA\Property(property: 'docker_registry_image_tag', type: 'string', description: 'The docker registry image tag.'),
                            new OA\Property(property: 'is_static', type: 'boolean', description: 'The flag to indicate if the application is static.'),
                            new OA\Property(property: 'install_command', type: 'string', description: 'The install command.'),
                            new OA\Property(property: 'build_command', type: 'string', description: 'The build command.'),
                            new OA\Property(property: 'start_command', type: 'string', description: 'The start command.'),
                            new OA\Property(property: 'ports_mappings', type: 'string', description: 'The ports mappings.'),
                            new OA\Property(property: 'base_directory', type: 'string', description: 'The base directory for all commands.'),
                            new OA\Property(property: 'publish_directory', type: 'string', description: 'The publish directory.'),
                            new OA\Property(property: 'health_check_enabled', type: 'boolean', description: 'Health check enabled.'),
                            new OA\Property(property: 'health_check_path', type: 'string', description: 'Health check path.'),
                            new OA\Property(property: 'health_check_port', type: 'string', nullable: true, description: 'Health check port.'),
                            new OA\Property(property: 'health_check_host', type: 'string', nullable: true, description: 'Health check host.'),
                            new OA\Property(property: 'health_check_method', type: 'string', description: 'Health check method.'),
                            new OA\Property(property: 'health_check_return_code', type: 'integer', description: 'Health check return code.'),
                            new OA\Property(property: 'health_check_scheme', type: 'string', description: 'Health check scheme.'),
                            new OA\Property(property: 'health_check_response_text', type: 'string', nullable: true, description: 'Health check response text.'),
                            new OA\Property(property: 'health_check_interval', type: 'integer', description: 'Health check interval in seconds.'),
                            new OA\Property(property: 'health_check_timeout', type: 'integer', description: 'Health check timeout in seconds.'),
                            new OA\Property(property: 'health_check_retries', type: 'integer', description: 'Health check retries count.'),
                            new OA\Property(property: 'health_check_start_period', type: 'integer', description: 'Health check start period in seconds.'),
                            new OA\Property(property: 'limits_memory', type: 'string', description: 'Memory limit.'),
                            new OA\Property(property: 'limits_memory_swap', type: 'string', description: 'Memory swap limit.'),
                            new OA\Property(property: 'limits_memory_swappiness', type: 'integer', description: 'Memory swappiness.'),
                            new OA\Property(property: 'limits_memory_reservation', type: 'string', description: 'Memory reservation.'),
                            new OA\Property(property: 'limits_cpus', type: 'string', description: 'CPU limit.'),
                            new OA\Property(property: 'limits_cpuset', type: 'string', nullable: true, description: 'CPU set.'),
                            new OA\Property(property: 'limits_cpu_shares', type: 'integer', description: 'CPU shares.'),
                            new OA\Property(property: 'custom_labels', type: 'string', description: 'Custom labels.'),
                            new OA\Property(property: 'custom_docker_run_options', type: 'string', description: 'Custom docker run options.'),
                            new OA\Property(property: 'post_deployment_command', type: 'string', description: 'Post deployment command.'),
                            new OA\Property(property: 'post_deployment_command_container', type: 'string', description: 'Post deployment command container.'),
                            new OA\Property(property: 'pre_deployment_command', type: 'string', description: 'Pre deployment command.'),
                            new OA\Property(property: 'pre_deployment_command_container', type: 'string', description: 'Pre deployment command container.'),
                            new OA\Property(property: 'manual_webhook_secret_github', type: 'string', description: 'Manual webhook secret for Github.'),
                            new OA\Property(property: 'manual_webhook_secret_gitlab', type: 'string', description: 'Manual webhook secret for Gitlab.'),
                            new OA\Property(property: 'manual_webhook_secret_bitbucket', type: 'string', description: 'Manual webhook secret for Bitbucket.'),
                            new OA\Property(property: 'manual_webhook_secret_gitea', type: 'string', description: 'Manual webhook secret for Gitea.'),
                            new OA\Property(property: 'redirect', type: 'string', nullable: true, enum: ['www', 'non-www', 'both'], description: 'How to set redirect with Traefik / Caddy. www<->non-www.'),
                            new OA\Property(property: 'instant_deploy', type: 'boolean', description: 'The flag to indicate if the application should be deployed instantly.'),
                            new OA\Property(property: 'dockerfile', type: 'string', description: 'The Dockerfile content.'),
                            new OA\Property(property: 'docker_compose_location', type: 'string', description: 'The Docker Compose location.'),
                            new OA\Property(property: 'docker_compose_raw', type: 'string', description: 'The Docker Compose raw content.'),
                            new OA\Property(property: 'docker_compose_custom_start_command', type: 'string', description: 'The Docker Compose custom start command.'),
                            new OA\Property(property: 'docker_compose_custom_build_command', type: 'string', description: 'The Docker Compose custom build command.'),
                            new OA\Property(property: 'docker_compose_domains', type: 'array', description: 'The Docker Compose domains.'),
                            new OA\Property(property: 'watch_paths', type: 'string', description: 'The watch paths.'),
                            new OA\Property(property: 'use_build_server', type: 'boolean', nullable: true, description: 'Use build server.'),
                            new OA\Property(property: 'connect_to_docker_network', type: 'boolean', description: 'The flag to connect the service to the predefined Docker network.'),
                            new OA\Property(property: 'force_domain_override', type: 'boolean', description: 'Force domain usage even if conflicts are detected. Default is false.'),
                        ],
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Application updated.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid', type: 'string'),
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
                response: 409,
                description: 'Domain conflicts detected.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'message',
                                    type: 'string',
                                    example: 'Domain conflicts detected. Use force_domain_override=true to proceed.',
                                ),
                                new OA\Property(
                                    property: 'warning',
                                    type: 'string',
                                    example: 'Using the same domain for multiple resources can cause routing conflicts and unpredictable behavior.',
                                ),
                                new OA\Property(
                                    property: 'conflicts',
                                    type: 'array',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'domain', type: 'string', example: 'example.com'),
                                            new OA\Property(property: 'resource_name', type: 'string', example: 'My Application'),
                                            new OA\Property(property: 'resource_uuid', type: 'string', nullable: true, example: 'abc123-def456'),
                                            new OA\Property(
                                                property: 'resource_type',
                                                type: 'string',
                                                enum: ['application', 'service', 'instance'],
                                                example: 'application',
                                            ),
                                            new OA\Property(
                                                property: 'message',
                                                type: 'string',
                                                example: 'Domain example.com is already in use by application \'My Application\'',
                                            ),
                                        ]
                                    ),
                                ),
                            ]
                        )
                    ),
                ]
            ),
        ]
    )]
    public function update_by_uuid(Request $request)
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

        $this->authorize('update', $application);

        $server = $application->destination->server;
        $allowedFields = ['name', 'description', 'is_static', 'domains', 'git_repository', 'git_branch', 'git_commit_sha', 'docker_registry_image_name', 'docker_registry_image_tag', 'build_pack', 'static_image', 'install_command', 'build_command', 'start_command', 'ports_exposes', 'ports_mappings', 'base_directory', 'publish_directory', 'health_check_enabled', 'health_check_path', 'health_check_port', 'health_check_host', 'health_check_method', 'health_check_return_code', 'health_check_scheme', 'health_check_response_text', 'health_check_interval', 'health_check_timeout', 'health_check_retries', 'health_check_start_period', 'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares', 'custom_labels', 'custom_docker_run_options', 'post_deployment_command', 'post_deployment_command_container', 'pre_deployment_command', 'pre_deployment_command_container', 'watch_paths', 'manual_webhook_secret_github', 'manual_webhook_secret_gitlab', 'manual_webhook_secret_bitbucket', 'manual_webhook_secret_gitea', 'docker_compose_location', 'docker_compose_raw', 'docker_compose_custom_start_command', 'docker_compose_custom_build_command', 'docker_compose_domains', 'redirect', 'instant_deploy', 'use_build_server', 'custom_nginx_configuration', 'is_http_basic_auth_enabled', 'http_basic_auth_username', 'http_basic_auth_password', 'connect_to_docker_network', 'force_domain_override'];

        $validationRules = [
            'name' => 'string|max:255',
            'description' => 'string|nullable',
            'static_image' => 'string',
            'watch_paths' => 'string|nullable',
            'docker_compose_location' => ['string', 'regex:/^[a-zA-Z0-9._\\/\\-]+$/'],
            'docker_compose_raw' => 'string|nullable',
            'docker_compose_domains' => 'array|nullable',
            'docker_compose_custom_start_command' => 'string|nullable',
            'docker_compose_custom_build_command' => 'string|nullable',
            'custom_nginx_configuration' => 'string|nullable',
            'is_http_basic_auth_enabled' => 'boolean|nullable',
            'http_basic_auth_username' => 'string',
            'http_basic_auth_password' => 'string',
        ];
        $validationRules = array_merge(sharedDataApplications(), $validationRules);
        $validator = customApiValidator($request->all(), $validationRules);

        // Validate ports_exposes
        if ($request->has('ports_exposes')) {
            $ports = explode(',', $request->ports_exposes);
            foreach ($ports as $port) {
                if (! is_numeric($port)) {
                    return response()->json([
                        'message' => 'Validation failed.',
                        'errors' => [
                            'ports_exposes' => 'The ports_exposes should be a comma separated list of numbers.',
                        ],
                    ], 422);
                }
            }
        }
        if ($request->has('custom_nginx_configuration')) {
            if (! isBase64Encoded($request->custom_nginx_configuration)) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'custom_nginx_configuration' => 'The custom_nginx_configuration should be base64 encoded.',
                    ],
                ], 422);
            }
            $customNginxConfiguration = base64_decode($request->custom_nginx_configuration);
            if (mb_detect_encoding($customNginxConfiguration, 'ASCII', true) === false) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'custom_nginx_configuration' => 'The custom_nginx_configuration should be base64 encoded.',
                    ],
                ], 422);
            }
        }
        $return = $this->validateDataApplications($request, $server);
        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }
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

        if ($request->has('is_http_basic_auth_enabled') && $request->is_http_basic_auth_enabled === true) {
            if (blank($application->http_basic_auth_username) || blank($application->http_basic_auth_password)) {
                $validationErrors = [];
                if (blank($request->http_basic_auth_username)) {
                    $validationErrors['http_basic_auth_username'] = 'The http_basic_auth_username is required.';
                }
                if (blank($request->http_basic_auth_password)) {
                    $validationErrors['http_basic_auth_password'] = 'The http_basic_auth_password is required.';
                }
                if (count($validationErrors) > 0) {
                    return response()->json([
                        'message' => 'Validation failed.',
                        'errors' => $validationErrors,
                    ], 422);
                }
            }
        }
        if ($request->has('is_http_basic_auth_enabled') && $application->is_container_label_readonly_enabled === false) {
            $application->custom_labels = str(implode('|saturn|', generateLabelsApplication($application)))->replace('|saturn|', "\n");
            $application->save();
        }

        $domains = $request->domains;
        $requestHasDomains = $request->has('domains');
        if ($requestHasDomains && $server->isProxyShouldRun()) {
            $uuid = $request->uuid;
            $fqdn = $request->domains;
            $fqdn = str($fqdn)->replaceEnd(',', '')->trim();
            $fqdn = str($fqdn)->replaceStart(',', '')->trim();
            $errors = [];
            $fqdn = str($fqdn)->trim()->explode(',')->map(function ($domain) use (&$errors) {
                $domain = trim($domain);
                if (filter_var($domain, FILTER_VALIDATE_URL) === false || ! preg_match('/^https?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}/', $domain)) {
                    $errors[] = 'Invalid domain: '.$domain;
                }

                return $domain;
            });
            if (count($errors) > 0) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $errors,
                ], 422);
            }
            // Check for domain conflicts
            $result = checkIfDomainIsAlreadyUsedViaAPI($fqdn, $teamId, $uuid);
            if (isset($result['error'])) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['domains' => $result['error']],
                ], 422);
            }

            // If there are conflicts and force is not enabled, return warning
            if ($result['hasConflicts'] && ! $request->boolean('force_domain_override')) {
                return response()->json([
                    'message' => 'Domain conflicts detected. Use force_domain_override=true to proceed.',
                    'conflicts' => $result['conflicts'],
                    'warning' => 'Using the same domain for multiple resources can cause routing conflicts and unpredictable behavior.',
                ], 409);
            }
        }

        $dockerComposeDomainsJson = collect();
        if ($request->has('docker_compose_domains')) {
            if (! $request->has('docker_compose_raw')) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'docker_compose_raw' => 'The base64 encoded docker_compose_raw is required.',
                    ],
                ], 422);
            }

            if (! isBase64Encoded($request->docker_compose_raw)) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'docker_compose_raw' => 'The docker_compose_raw should be base64 encoded.',
                    ],
                ], 422);
            }
            $dockerComposeRaw = base64_decode($request->docker_compose_raw);
            if (mb_detect_encoding($dockerComposeRaw, 'ASCII', true) === false) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'docker_compose_raw' => 'The docker_compose_raw should be base64 encoded.',
                    ],
                ], 422);
            }
            $dockerComposeRaw = base64_decode($request->docker_compose_raw);
            $yaml = Yaml::parse($dockerComposeRaw);
            $services = data_get($yaml, 'services');
            $dockerComposeDomains = collect($request->docker_compose_domains);
            if ($dockerComposeDomains->count() > 0) {
                $dockerComposeDomains->each(function ($domain, $key) use ($services, $dockerComposeDomainsJson) {
                    $name = data_get($domain, 'name');
                    if (data_get($services, $name)) {
                        $dockerComposeDomainsJson->put($name, ['domain' => data_get($domain, 'domain')]);
                    }
                });
            }
            $request->offsetUnset('docker_compose_domains');
        }
        $instantDeploy = $request->instant_deploy;
        $isStatic = $request->is_static;
        $connectToDockerNetwork = $request->connect_to_docker_network;
        $useBuildServer = $request->use_build_server;

        if (isset($useBuildServer)) {
            $application->settings->is_build_server_enabled = $useBuildServer;
            $application->settings->save();
        }

        if (isset($isStatic)) {
            $application->settings->is_static = $isStatic;
            $application->settings->save();
        }

        if (isset($connectToDockerNetwork)) {
            $application->settings->connect_to_docker_network = $connectToDockerNetwork;
            $application->settings->save();
        }

        removeUnnecessaryFieldsFromRequest($request);

        $data = $request->all();
        if ($requestHasDomains && $server->isProxyShouldRun()) {
            data_set($data, 'fqdn', $domains);
        }

        if ($dockerComposeDomainsJson->count() > 0) {
            data_set($data, 'docker_compose_domains', json_encode($dockerComposeDomainsJson));
        }

        // Mark build_pack as explicitly set when user changes it via API
        if ($request->has('build_pack')) {
            data_set($data, 'build_pack_explicitly_set', true);
        }

        $application->fill($data);
        if ($application->settings->is_container_label_readonly_enabled && $requestHasDomains && $server->isProxyShouldRun()) {
            $application->custom_labels = str(implode('|saturn|', generateLabelsApplication($application)))->replace('|saturn|', "\n");
        }
        $application->save();

        if ($instantDeploy) {
            $deployment_uuid = new Cuid2;

            $result = queue_application_deployment(
                application: $application,
                deployment_uuid: $deployment_uuid,
                is_api: true,
            );
            if ($result['status'] === 'skipped') {
                return response()->json([
                    'message' => $result['message'],
                ], 200);
            }
        }

        return response()->json([
            'uuid' => $application->uuid,
        ]);
    }

    private function validateDataApplications(Request $request, Server $server)
    {
        $teamId = getTeamIdFromToken();

        // Validate ports_mappings
        if ($request->has('ports_mappings')) {
            $ports = [];
            foreach (explode(',', $request->ports_mappings) as $portMapping) {
                $port = explode(':', $portMapping);
                if (in_array($port[0], $ports)) {
                    return response()->json([
                        'message' => 'Validation failed.',
                        'errors' => [
                            'ports_mappings' => 'The first number before : should be unique between mappings.',
                        ],
                    ], 422);
                }
                $ports[] = $port[0];
            }
        }
        // Validate custom_labels
        if ($request->has('custom_labels')) {
            if (! isBase64Encoded($request->custom_labels)) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'custom_labels' => 'The custom_labels should be base64 encoded.',
                    ],
                ], 422);
            }
            $customLabels = base64_decode($request->custom_labels);
            if (mb_detect_encoding($customLabels, 'ASCII', true) === false) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'custom_labels' => 'The custom_labels should be base64 encoded.',
                    ],
                ], 422);
            }
        }
        if ($request->has('domains') && $server->isProxyShouldRun()) {
            $uuid = $request->uuid;
            $fqdn = $request->domains;
            $fqdn = str($fqdn)->replaceEnd(',', '')->trim();
            $fqdn = str($fqdn)->replaceStart(',', '')->trim();
            $errors = [];
            $fqdn = str($fqdn)->trim()->explode(',')->map(function ($domain) use (&$errors) {
                $domain = trim($domain);
                if (filter_var($domain, FILTER_VALIDATE_URL) === false) {
                    $errors[] = 'Invalid domain: '.$domain;
                }

                return str($domain)->lower();
            });
            if (count($errors) > 0) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $errors,
                ], 422);
            }
            // Check for domain conflicts
            $result = checkIfDomainIsAlreadyUsedViaAPI($fqdn, $teamId, $uuid);
            if (isset($result['error'])) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['domains' => $result['error']],
                ], 422);
            }

            // If there are conflicts and force is not enabled, return warning
            if ($result['hasConflicts'] && ! $request->boolean('force_domain_override')) {
                return response()->json([
                    'message' => 'Domain conflicts detected. Use force_domain_override=true to proceed.',
                    'conflicts' => $result['conflicts'],
                    'warning' => 'Using the same domain for multiple resources can cause routing conflicts and unpredictable behavior.',
                ], 409);
            }
        }
    }
}
