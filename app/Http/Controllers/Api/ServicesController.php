<?php

namespace App\Http\Controllers\Api;

use App\Actions\Service\CreateCustomServiceAction;
use App\Actions\Service\CreateOneClickServiceAction;
use App\Actions\Service\UpdateServiceAction;
use App\Http\Controllers\Controller;
use App\Jobs\DeleteResourceJob;
use App\Models\Project;
use App\Models\Server;
use App\Models\Service;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ServicesController extends Controller
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
        summary: 'List',
        description: 'List all services.',
        path: '/services',
        operationId: 'list-services',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Services'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Get all services',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Service')
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
    public function services(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $projects = Project::where('team_id', $teamId)->get();
        $services = collect();
        foreach ($projects as $project) {
            $services->push($project->services()->get());
        }
        foreach ($services as $service) {
            $service = $this->removeSensitiveData($service);
        }

        return response()->json($services->flatten());
    }

    #[OA\Post(
        summary: 'Create service',
        description: 'Create a one-click / custom service',
        path: '/services',
        operationId: 'create-service',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Services'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['server_uuid', 'project_uuid', 'environment_name', 'environment_uuid'],
                    properties: [
                        new OA\Property(
                            property: 'type',
                            type: 'string',
                            enum: [
                                'activepieces',
                                'appsmith',
                                'appwrite',
                                'authentik',
                                'babybuddy',
                                'budge',
                                'changedetection',
                                'chatwoot',
                                'classicpress-with-mariadb',
                                'classicpress-with-mysql',
                                'classicpress-without-database',
                                'cloudflared',
                                'code-server',
                                'dashboard',
                                'directus',
                                'directus-with-postgresql',
                                'docker-registry',
                                'docuseal',
                                'docuseal-with-postgres',
                                'dokuwiki',
                                'duplicati',
                                'emby',
                                'embystat',
                                'fider',
                                'filebrowser',
                                'firefly',
                                'formbricks',
                                'ghost',
                                'gitea',
                                'gitea-with-mariadb',
                                'gitea-with-mysql',
                                'gitea-with-postgresql',
                                'glance',
                                'glances',
                                'glitchtip',
                                'grafana',
                                'grafana-with-postgresql',
                                'grocy',
                                'heimdall',
                                'homepage',
                                'jellyfin',
                                'kuzzle',
                                'listmonk',
                                'logto',
                                'mediawiki',
                                'meilisearch',
                                'metabase',
                                'metube',
                                'minio',
                                'moodle',
                                'n8n',
                                'n8n-with-postgresql',
                                'next-image-transformation',
                                'nextcloud',
                                'nocodb',
                                'odoo',
                                'openblocks',
                                'pairdrop',
                                'penpot',
                                'phpmyadmin',
                                'pocketbase',
                                'posthog',
                                'reactive-resume',
                                'rocketchat',
                                'shlink',
                                'slash',
                                'snapdrop',
                                'statusnook',
                                'stirling-pdf',
                                'supabase',
                                'syncthing',
                                'tolgee',
                                'trigger',
                                'trigger-with-external-database',
                                'twenty',
                                'umami',
                                'unleash-with-postgresql',
                                'unleash-without-database',
                                'uptime-kuma',
                                'vaultwarden',
                                'vikunja',
                                'weblate',
                                'whoogle',
                                'wordpress-with-mariadb',
                                'wordpress-with-mysql',
                                'wordpress-without-database',
                            ],
                            description: 'The one-click service type',
                        ),
                        new OA\Property(property: 'name', type: 'string', description: 'Name of the service.'),
                        new OA\Property(property: 'description', type: 'string', nullable: true, description: 'Description of the service.'),
                        new OA\Property(property: 'project_uuid', type: 'string', description: 'Project UUID.'),
                        new OA\Property(
                            property: 'environment_name',
                            type: 'string',
                            description: 'Environment name. You need to provide at least one of environment_name or environment_uuid.',
                        ),
                        new OA\Property(
                            property: 'environment_uuid',
                            type: 'string',
                            description: 'Environment UUID. You need to provide at least one of environment_name or environment_uuid.',
                        ),
                        new OA\Property(property: 'server_uuid', type: 'string', description: 'Server UUID.'),
                        new OA\Property(
                            property: 'destination_uuid',
                            type: 'string',
                            description: 'Destination UUID. Required if server has multiple destinations.',
                        ),
                        new OA\Property(property: 'instant_deploy', type: 'boolean', description: 'Start the service immediately after creation.'),
                        new OA\Property(property: 'docker_compose_raw', type: 'string', description: 'The Docker Compose raw content.'),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Service created successfully.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid', type: 'string', description: 'Service UUID.'),
                                new OA\Property(property: 'domains', type: 'array', items: new OA\Items(type: 'string'), description: 'Service domains.'),
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
                response: 422,
                ref: '#/components/responses/422',
            ),
        ]
    )]
    public function create_service(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $this->authorize('create', Service::class);

        $return = validateIncomingRequest($request);
        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }

        // Determine which type of service to create
        $isOneClick = filled($request->type);
        $isCustom = filled($request->docker_compose_raw);

        if (! $isOneClick && ! $isCustom) {
            return response()->json(['message' => 'No service type or docker_compose_raw provided.'], 400);
        }

        // Set allowed fields based on service type
        $allowedFields = $isCustom
            ? ['name', 'description', 'project_uuid', 'environment_name', 'environment_uuid', 'server_uuid', 'destination_uuid', 'instant_deploy', 'docker_compose_raw', 'connect_to_docker_network']
            : ['type', 'name', 'description', 'project_uuid', 'environment_name', 'environment_uuid', 'server_uuid', 'destination_uuid', 'instant_deploy', 'docker_compose_raw'];

        // Validation rules
        $rules = [
            'project_uuid' => 'string|required',
            'environment_name' => 'string|nullable',
            'environment_uuid' => 'string|nullable',
            'server_uuid' => 'string|required',
            'destination_uuid' => 'string|nullable',
            'name' => 'string|max:255',
            'description' => 'string|nullable',
            'instant_deploy' => 'boolean',
        ];

        if ($isOneClick) {
            $rules['type'] = 'string|required_without:docker_compose_raw';
            $rules['docker_compose_raw'] = 'string|required_without:type';
        } else {
            $rules['docker_compose_raw'] = 'string|required';
            $rules['connect_to_docker_network'] = 'boolean';
        }

        $validator = customApiValidator($request->all(), $rules);
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

        // Validate environment requirement
        if (blank($request->environment_uuid) && blank($request->environment_name)) {
            return response()->json(['message' => 'You need to provide at least one of environment_name or environment_uuid.'], 422);
        }

        // Resolve project
        $project = Project::whereTeamId($teamId)->whereUuid($request->project_uuid)->first();
        if (! $project) {
            return response()->json(['message' => 'Project not found.'], 404);
        }

        // Resolve environment
        $environment = $project->environments()->where('name', $request->environment_name)->first();
        if (! $environment) {
            $environment = $project->environments()->where('uuid', $request->environment_uuid)->first();
        }
        if (! $environment) {
            return response()->json(['message' => 'Environment not found.'], 404);
        }

        // Resolve server
        $server = Server::whereTeamId($teamId)->whereUuid($request->server_uuid)->first();
        if (! $server) {
            return response()->json(['message' => 'Server not found.'], 404);
        }

        // Resolve destination
        $destinations = $server->destinations();
        if ($destinations->count() == 0) {
            return response()->json(['message' => 'Server has no destinations.'], 400);
        }
        if ($destinations->count() > 1 && ! $request->has('destination_uuid')) {
            return response()->json(['message' => 'Server has multiple destinations and you do not set destination_uuid.'], 400);
        }
        $destination = $destinations->first();

        $instantDeploy = $request->instant_deploy ?? false;

        // Create one-click service
        if ($isOneClick) {
            try {
                $result = CreateOneClickServiceAction::run(
                    type: $request->type,
                    server: $server,
                    environment: $environment,
                    destination: $destination,
                    instantDeploy: $instantDeploy
                );
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['docker_compose_raw' => $e->getMessage()],
                ], 422);
            }

            if (isset($result['error'])) {
                return response()->json([
                    'message' => $result['error'],
                    'valid_service_types' => $result['valid_types'] ?? null,
                ], 404);
            }

            return response()->json([
                'uuid' => $result['service']->uuid,
                'domains' => $result['domains'],
            ]);
        }

        // Create custom service with docker-compose
        if (! isBase64Encoded($request->docker_compose_raw)) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['docker_compose_raw' => 'The docker_compose_raw should be base64 encoded.'],
            ], 422);
        }

        $dockerComposeRaw = base64_decode($request->docker_compose_raw);
        if (mb_detect_encoding($dockerComposeRaw, 'ASCII', true) === false) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['docker_compose_raw' => 'The docker_compose_raw should be base64 encoded.'],
            ], 422);
        }

        try {
            $result = CreateCustomServiceAction::run(
                dockerComposeRaw: $dockerComposeRaw,
                server: $server,
                environment: $environment,
                destination: $destination,
                name: $request->name,
                description: $request->description,
                connectToDockerNetwork: $request->connect_to_docker_network ?? false,
                instantDeploy: $instantDeploy
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['docker_compose_raw' => $e->getMessage()],
            ], 422);
        }

        return response()->json([
            'uuid' => $result['service']->uuid,
            'domains' => $result['domains'],
        ])->setStatusCode(201);
    }

    #[OA\Get(
        summary: 'Get',
        description: 'Get service by UUID.',
        path: '/services/{uuid}',
        operationId: 'get-service-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Service UUID', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Get a service by UUID.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            ref: '#/components/schemas/Service'
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
    public function service_by_uuid(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        if (! $request->uuid) {
            return response()->json(['message' => 'UUID is required.'], 404);
        }
        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('view', $service);

        $service = $service->load(['applications', 'databases']);

        return response()->json($this->removeSensitiveData($service));
    }

    #[OA\Delete(
        summary: 'Delete',
        description: 'Delete service by UUID.',
        path: '/services/{uuid}',
        operationId: 'delete-service-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Services'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Service UUID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'delete_configurations', in: 'query', required: false, description: 'Delete configurations.', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'delete_volumes', in: 'query', required: false, description: 'Delete volumes.', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'docker_cleanup', in: 'query', required: false, description: 'Run docker cleanup.', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'delete_connected_networks', in: 'query', required: false, description: 'Delete connected networks.', schema: new OA\Schema(type: 'boolean', default: true)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Delete a service by UUID',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Service deletion request queued.'),
                            ],
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
        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('delete', $service);

        DeleteResourceJob::dispatch(
            resource: $service,
            deleteVolumes: $request->boolean('delete_volumes', true),
            deleteConnectedNetworks: $request->boolean('delete_connected_networks', true),
            deleteConfigurations: $request->boolean('delete_configurations', true),
            dockerCleanup: $request->boolean('docker_cleanup', true)
        );

        return response()->json([
            'message' => 'Service deletion request queued.',
        ]);
    }

    #[OA\Patch(
        summary: 'Update',
        description: 'Update service by UUID.',
        path: '/services/{uuid}',
        operationId: 'update-service-by-uuid',
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
            description: 'Service updated.',
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'name', type: 'string', description: 'The service name.'),
                            new OA\Property(property: 'description', type: 'string', description: 'The service description.'),
                            new OA\Property(property: 'project_uuid', type: 'string', description: 'The project UUID.'),
                            new OA\Property(property: 'environment_name', type: 'string', description: 'The environment name.'),
                            new OA\Property(property: 'environment_uuid', type: 'string', description: 'The environment UUID.'),
                            new OA\Property(property: 'server_uuid', type: 'string', description: 'The server UUID.'),
                            new OA\Property(property: 'destination_uuid', type: 'string', description: 'The destination UUID.'),
                            new OA\Property(
                                property: 'instant_deploy',
                                type: 'boolean',
                                description: 'The flag to indicate if the service should be deployed instantly.',
                            ),
                            new OA\Property(
                                property: 'connect_to_docker_network',
                                type: 'boolean',
                                description: 'Connect the service to the predefined docker network.',
                            ),
                            new OA\Property(property: 'docker_compose_raw', type: 'string', description: 'The Docker Compose raw content.'),
                            new OA\Property(
                                property: 'limits_memory',
                                type: 'string',
                                description: 'Memory limit for all containers (e.g., "512m", "1g", "0" for no limit).',
                            ),
                            new OA\Property(property: 'limits_memory_swap', type: 'string', description: 'Memory swap limit (e.g., "1g", "0" for no limit).'),
                            new OA\Property(property: 'limits_memory_swappiness', type: 'integer', description: 'Memory swappiness (0-100).'),
                            new OA\Property(property: 'limits_memory_reservation', type: 'string', description: 'Memory reservation (soft limit).'),
                            new OA\Property(property: 'limits_cpus', type: 'string', description: 'CPU limit (e.g., "0.5", "2", "0" for no limit).'),
                            new OA\Property(property: 'limits_cpuset', type: 'string', nullable: true, description: 'CPU set (e.g., "0,1" or "0-3").'),
                            new OA\Property(property: 'limits_cpu_shares', type: 'integer', description: 'CPU shares (relative weight, default 1024).'),
                        ],
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service updated.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid', type: 'string', description: 'Service UUID.'),
                                new OA\Property(property: 'domains', type: 'array', items: new OA\Items(type: 'string'), description: 'Service domains.'),
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

        $service = Service::whereRelation('environment.project.team', 'id', $teamId)->whereUuid($request->uuid)->first();
        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $this->authorize('update', $service);

        $allowedFields = [
            'name', 'description', 'instant_deploy', 'docker_compose_raw', 'connect_to_docker_network',
            'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation',
            'limits_cpus', 'limits_cpuset', 'limits_cpu_shares',
        ];

        $validator = customApiValidator($request->all(), [
            'name' => 'string|max:255',
            'description' => 'string|nullable',
            'instant_deploy' => 'boolean',
            'connect_to_docker_network' => 'boolean',
            'docker_compose_raw' => 'string|nullable',
            'limits_memory' => 'string|nullable',
            'limits_memory_swap' => 'string|nullable',
            'limits_memory_swappiness' => 'integer|min:0|max:100|nullable',
            'limits_memory_reservation' => 'string|nullable',
            'limits_cpus' => 'string|nullable',
            'limits_cpuset' => 'string|nullable',
            'limits_cpu_shares' => 'integer|min:0|nullable',
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

        // Prepare update data
        $updateData = $request->only([
            'name', 'description', 'connect_to_docker_network',
            'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness',
            'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares',
        ]);

        // Handle docker_compose_raw validation and decoding
        if ($request->has('docker_compose_raw')) {
            if (! isBase64Encoded($request->docker_compose_raw)) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['docker_compose_raw' => 'The docker_compose_raw should be base64 encoded.'],
                ], 422);
            }

            $dockerComposeRaw = base64_decode($request->docker_compose_raw);
            if (mb_detect_encoding($dockerComposeRaw, 'ASCII', true) === false) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['docker_compose_raw' => 'The docker_compose_raw should be base64 encoded.'],
                ], 422);
            }

            $updateData['docker_compose_raw'] = $dockerComposeRaw;
        }

        try {
            $result = UpdateServiceAction::run(
                service: $service,
                data: $updateData,
                instantDeploy: $request->instant_deploy ?? false
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['docker_compose_raw' => $e->getMessage()],
            ], 422);
        }

        return response()->json([
            'uuid' => $result['service']->uuid,
            'domains' => $result['domains'],
        ])->setStatusCode(200);
    }
}
