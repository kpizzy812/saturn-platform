<?php

namespace App\Http\Controllers\Api;

use App\Actions\Database\StartDatabaseProxy;
use App\Actions\Database\StopDatabaseProxy;
use App\Http\Controllers\Controller;
use App\Jobs\DeleteResourceJob;
use App\Models\Project;
use App\Models\ScheduledDatabaseBackup;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * API controller for database CRUD operations.
 *
 * Handles listing, viewing, updating, and deleting databases.
 * For creation, see DatabaseCreateController.
 * For backups, see DatabaseBackupsController.
 * For actions (start/stop/restart), see DatabaseActionsController.
 */
class DatabasesController extends Controller
{
    /**
     * Hide sensitive data from database response.
     */
    private function removeSensitiveData($database)
    {
        $database->makeHidden([
            'id',
            'laravel_through_key',
        ]);
        if (request()->attributes->get('can_read_sensitive', false) === false) {
            $database->makeHidden([
                'internal_db_url',
                'external_db_url',
                'postgres_password',
                'dragonfly_password',
                'redis_password',
                'mongo_initdb_root_password',
                'keydb_password',
                'clickhouse_admin_password',
            ]);
        }

        return serializeApiResponse($database);
    }

    #[OA\Get(
        summary: 'List',
        description: 'List all databases.',
        path: '/databases',
        operationId: 'list-databases',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Databases'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Get all databases',
                content: new OA\JsonContent(
                    type: 'string',
                    example: 'Content is very complex. Will be implemented later.',
                ),
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
    public function index(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        $projects = Project::where('team_id', $teamId)->get();
        $databases = collect();
        foreach ($projects as $project) {
            $databases = $databases->merge($project->databases());
        }

        $databaseIds = $databases->pluck('id')->toArray();

        $backupConfigs = ScheduledDatabaseBackup::ownedByCurrentTeamAPI($teamId)->with('latest_log')
            ->whereIn('database_id', $databaseIds)
            ->get()
            ->groupBy('database_id');

        $databases = $databases->map(function ($database) use ($backupConfigs) {
            $database->backup_configs = $backupConfigs->get($database->id, collect())->values();

            return $this->removeSensitiveData($database);
        });

        return response()->json($databases);
    }

    #[OA\Get(
        summary: 'Get',
        description: 'Get database by UUID.',
        path: '/databases/{uuid}',
        operationId: 'get-database-by-uuid',
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
                description: 'Get all databases',
                content: new OA\JsonContent(
                    type: 'string',
                    example: 'Content is very complex. Will be implemented later.',
                ),
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
    public function show(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }
        if (! $request->uuid) {
            return response()->json(['message' => 'UUID is required.'], 404);
        }
        $database = queryDatabaseByUuidWithinTeam($request->uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $this->authorize('view', $database);

        return response()->json($this->removeSensitiveData($database));
    }

    #[OA\Patch(
        summary: 'Update',
        description: 'Update database by UUID.',
        path: '/databases/{uuid}',
        operationId: 'update-database-by-uuid',
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
        requestBody: new OA\RequestBody(
            description: 'Database data',
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'name', type: 'string', description: 'Name of the database'),
                        new OA\Property(property: 'description', type: 'string', description: 'Description of the database'),
                        new OA\Property(property: 'image', type: 'string', description: 'Docker Image of the database'),
                        new OA\Property(property: 'is_public', type: 'boolean', description: 'Is the database public?'),
                        new OA\Property(property: 'public_port', type: 'integer', description: 'Public port of the database'),
                        new OA\Property(property: 'limits_memory', type: 'string', description: 'Memory limit of the database'),
                        new OA\Property(property: 'limits_memory_swap', type: 'string', description: 'Memory swap limit of the database'),
                        new OA\Property(property: 'limits_memory_swappiness', type: 'integer', description: 'Memory swappiness of the database'),
                        new OA\Property(property: 'limits_memory_reservation', type: 'string', description: 'Memory reservation of the database'),
                        new OA\Property(property: 'limits_cpus', type: 'string', description: 'CPU limit of the database'),
                        new OA\Property(property: 'limits_cpuset', type: 'string', description: 'CPU set of the database'),
                        new OA\Property(property: 'limits_cpu_shares', type: 'integer', description: 'CPU shares of the database'),
                        new OA\Property(property: 'postgres_user', type: 'string', description: 'PostgreSQL user'),
                        new OA\Property(property: 'postgres_password', type: 'string', description: 'PostgreSQL password'),
                        new OA\Property(property: 'postgres_db', type: 'string', description: 'PostgreSQL database'),
                        new OA\Property(property: 'postgres_initdb_args', type: 'string', description: 'PostgreSQL initdb args'),
                        new OA\Property(property: 'postgres_host_auth_method', type: 'string', description: 'PostgreSQL host auth method'),
                        new OA\Property(property: 'postgres_conf', type: 'string', description: 'PostgreSQL conf'),
                        new OA\Property(property: 'clickhouse_admin_user', type: 'string', description: 'Clickhouse admin user'),
                        new OA\Property(property: 'clickhouse_admin_password', type: 'string', description: 'Clickhouse admin password'),
                        new OA\Property(property: 'dragonfly_password', type: 'string', description: 'DragonFly password'),
                        new OA\Property(property: 'redis_password', type: 'string', description: 'Redis password'),
                        new OA\Property(property: 'redis_conf', type: 'string', description: 'Redis conf'),
                        new OA\Property(property: 'keydb_password', type: 'string', description: 'KeyDB password'),
                        new OA\Property(property: 'keydb_conf', type: 'string', description: 'KeyDB conf'),
                        new OA\Property(property: 'mariadb_conf', type: 'string', description: 'MariaDB conf'),
                        new OA\Property(property: 'mariadb_root_password', type: 'string', description: 'MariaDB root password'),
                        new OA\Property(property: 'mariadb_user', type: 'string', description: 'MariaDB user'),
                        new OA\Property(property: 'mariadb_password', type: 'string', description: 'MariaDB password'),
                        new OA\Property(property: 'mariadb_database', type: 'string', description: 'MariaDB database'),
                        new OA\Property(property: 'mongo_conf', type: 'string', description: 'Mongo conf'),
                        new OA\Property(property: 'mongo_initdb_root_username', type: 'string', description: 'Mongo initdb root username'),
                        new OA\Property(property: 'mongo_initdb_root_password', type: 'string', description: 'Mongo initdb root password'),
                        new OA\Property(property: 'mongo_initdb_database', type: 'string', description: 'Mongo initdb init database'),
                        new OA\Property(property: 'mysql_root_password', type: 'string', description: 'MySQL root password'),
                        new OA\Property(property: 'mysql_password', type: 'string', description: 'MySQL password'),
                        new OA\Property(property: 'mysql_user', type: 'string', description: 'MySQL user'),
                        new OA\Property(property: 'mysql_database', type: 'string', description: 'MySQL database'),
                        new OA\Property(property: 'mysql_conf', type: 'string', description: 'MySQL conf'),
                    ],
                ),
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Database updated',
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
        $allowedFields = ['name', 'description', 'image', 'public_port', 'is_public', 'instant_deploy', 'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares', 'postgres_user', 'postgres_password', 'postgres_db', 'postgres_initdb_args', 'postgres_host_auth_method', 'postgres_conf', 'clickhouse_admin_user', 'clickhouse_admin_password', 'dragonfly_password', 'redis_password', 'redis_conf', 'keydb_password', 'keydb_conf', 'mariadb_conf', 'mariadb_root_password', 'mariadb_user', 'mariadb_password', 'mariadb_database', 'mongo_conf', 'mongo_initdb_root_username', 'mongo_initdb_root_password', 'mongo_initdb_database', 'mysql_root_password', 'mysql_password', 'mysql_user', 'mysql_database', 'mysql_conf'];
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // this check if the request is a valid json
        $return = validateIncomingRequest($request);
        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }
        $validator = customApiValidator($request->all(), [
            'name' => 'string|max:255',
            'description' => 'string|nullable',
            'image' => 'string',
            'is_public' => 'boolean',
            'public_port' => 'integer|nullable|min:1024|max:65535',
            'limits_memory' => 'string',
            'limits_memory_swap' => 'string',
            'limits_memory_swappiness' => 'numeric',
            'limits_memory_reservation' => 'string',
            'limits_cpus' => 'string',
            'limits_cpuset' => 'string|nullable',
            'limits_cpu_shares' => 'numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }
        $uuid = $request->uuid;
        removeUnnecessaryFieldsFromRequest($request);
        $database = queryDatabaseByUuidWithinTeam($uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $this->authorize('update', $database);

        // Only admin+ can toggle public network access (exposes server IP)
        if ($request->is_public === true) {
            $user = auth()->user();
            $role = $user?->role();
            if (! in_array($role, ['owner', 'admin'])) {
                return response()->json(['message' => 'Only team admins can enable public network access.'], 403);
            }
        }

        if ($request->is_public && $request->public_port) {
            if (isPublicPortAlreadyUsed($database->destination->server, $request->public_port, $database->uuid)) {
                return response()->json(['message' => 'Public port already used by another database.'], 400);
            }
        }

        // Type-specific validation
        $validationResult = $this->validateByDatabaseType($request, $database, $allowedFields);
        if ($validationResult !== null) {
            return $validationResult;
        }

        $whatToDoWithDatabaseProxy = null;
        if ($request->is_public === false && $database->is_public === true) {
            $whatToDoWithDatabaseProxy = 'stop';
        }
        if ($request->is_public === true && $request->public_port && $database->is_public === false) {
            $whatToDoWithDatabaseProxy = 'start';
        }

        // Only update database fields, not backup configuration
        $database->update($request->only($allowedFields));

        if ($whatToDoWithDatabaseProxy === 'start') {
            StartDatabaseProxy::dispatch($database);
        } elseif ($whatToDoWithDatabaseProxy === 'stop') {
            StopDatabaseProxy::dispatch($database);
        }

        return response()->json([
            'message' => 'Database updated.',
        ]);
    }

    /**
     * Validate request based on database type and return validation error response or null if valid.
     */
    private function validateByDatabaseType(Request $request, $database, array &$allowedFields): ?\Illuminate\Http\JsonResponse
    {
        switch ($database->type()) {
            case 'standalone-postgresql':
                $allowedFields = ['name', 'description', 'image', 'public_port', 'is_public', 'instant_deploy', 'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares', 'postgres_user', 'postgres_password', 'postgres_db', 'postgres_initdb_args', 'postgres_host_auth_method', 'postgres_conf'];
                $validator = customApiValidator($request->all(), [
                    'postgres_user' => 'string',
                    'postgres_password' => 'string',
                    'postgres_db' => 'string',
                    'postgres_initdb_args' => 'string',
                    'postgres_host_auth_method' => 'string',
                    'postgres_conf' => 'string',
                ]);
                if ($request->has('postgres_conf')) {
                    $result = $this->validateBase64Config($request, 'postgres_conf');
                    if ($result !== null) {
                        return $result;
                    }
                }
                break;
            case 'standalone-clickhouse':
                $allowedFields = ['name', 'description', 'image', 'public_port', 'is_public', 'instant_deploy', 'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares', 'clickhouse_admin_user', 'clickhouse_admin_password'];
                $validator = customApiValidator($request->all(), [
                    'clickhouse_admin_user' => 'string',
                    'clickhouse_admin_password' => 'string',
                ]);
                break;
            case 'standalone-dragonfly':
                $allowedFields = ['name', 'description', 'image', 'public_port', 'is_public', 'instant_deploy', 'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares', 'dragonfly_password'];
                $validator = customApiValidator($request->all(), [
                    'dragonfly_password' => 'string',
                ]);
                break;
            case 'standalone-redis':
                $allowedFields = ['name', 'description', 'image', 'public_port', 'is_public', 'instant_deploy', 'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares', 'redis_password', 'redis_conf'];
                $validator = customApiValidator($request->all(), [
                    'redis_password' => 'string',
                    'redis_conf' => 'string',
                ]);
                if ($request->has('redis_conf')) {
                    $result = $this->validateBase64Config($request, 'redis_conf');
                    if ($result !== null) {
                        return $result;
                    }
                }
                break;
            case 'standalone-keydb':
                $allowedFields = ['name', 'description', 'image', 'public_port', 'is_public', 'instant_deploy', 'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares', 'keydb_password', 'keydb_conf'];
                $validator = customApiValidator($request->all(), [
                    'keydb_password' => 'string',
                    'keydb_conf' => 'string',
                ]);
                if ($request->has('keydb_conf')) {
                    $result = $this->validateBase64Config($request, 'keydb_conf');
                    if ($result !== null) {
                        return $result;
                    }
                }
                break;
            case 'standalone-mariadb':
                $allowedFields = ['name', 'description', 'image', 'public_port', 'is_public', 'instant_deploy', 'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares', 'mariadb_conf', 'mariadb_root_password', 'mariadb_user', 'mariadb_password', 'mariadb_database'];
                $validator = customApiValidator($request->all(), [
                    'mariadb_conf' => 'string',
                    'mariadb_root_password' => 'string',
                    'mariadb_user' => 'string',
                    'mariadb_password' => 'string',
                    'mariadb_database' => 'string',
                ]);
                if ($request->has('mariadb_conf')) {
                    $result = $this->validateBase64Config($request, 'mariadb_conf');
                    if ($result !== null) {
                        return $result;
                    }
                }
                break;
            case 'standalone-mongodb':
                $allowedFields = ['name', 'description', 'image', 'public_port', 'is_public', 'instant_deploy', 'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares', 'mongo_conf', 'mongo_initdb_root_username', 'mongo_initdb_root_password', 'mongo_initdb_database'];
                $validator = customApiValidator($request->all(), [
                    'mongo_conf' => 'string',
                    'mongo_initdb_root_username' => 'string',
                    'mongo_initdb_root_password' => 'string',
                    'mongo_initdb_database' => 'string',
                ]);
                if ($request->has('mongo_conf')) {
                    $result = $this->validateBase64Config($request, 'mongo_conf');
                    if ($result !== null) {
                        return $result;
                    }
                }
                break;
            case 'standalone-mysql':
                $allowedFields = ['name', 'description', 'image', 'public_port', 'is_public', 'instant_deploy', 'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness', 'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares', 'mysql_root_password', 'mysql_password', 'mysql_user', 'mysql_database', 'mysql_conf'];
                $validator = customApiValidator($request->all(), [
                    'mysql_root_password' => 'string',
                    'mysql_password' => 'string',
                    'mysql_user' => 'string',
                    'mysql_database' => 'string',
                    'mysql_conf' => 'string',
                ]);
                if ($request->has('mysql_conf')) {
                    $result = $this->validateBase64Config($request, 'mysql_conf');
                    if ($result !== null) {
                        return $result;
                    }
                }
                break;
            default:
                $validator = customApiValidator($request->all(), []);
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

        return null;
    }

    /**
     * Validate and decode base64 config field.
     */
    private function validateBase64Config(Request $request, string $field): ?\Illuminate\Http\JsonResponse
    {
        if (! isBase64Encoded($request->$field)) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => [
                    $field => "The {$field} should be base64 encoded.",
                ],
            ], 422);
        }
        $decoded = base64_decode($request->$field);
        if (mb_detect_encoding($decoded, 'ASCII', true) === false) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => [
                    $field => "The {$field} should be base64 encoded.",
                ],
            ], 422);
        }
        $request->offsetSet($field, $decoded);

        return null;
    }

    #[OA\Delete(
        summary: 'Delete',
        description: 'Delete database by UUID.',
        path: '/databases/{uuid}',
        operationId: 'delete-database-by-uuid',
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
            new OA\Parameter(name: 'delete_configurations', in: 'query', required: false, description: 'Delete configurations.', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'delete_volumes', in: 'query', required: false, description: 'Delete volumes.', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'docker_cleanup', in: 'query', required: false, description: 'Run docker cleanup.', schema: new OA\Schema(type: 'boolean', default: true)),
            new OA\Parameter(name: 'delete_connected_networks', in: 'query', required: false, description: 'Delete connected networks.', schema: new OA\Schema(type: 'boolean', default: true)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Database deleted.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Database deleted.'),
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
        if (! $request->uuid) {
            return response()->json(['message' => 'UUID is required.'], 404);
        }
        $database = queryDatabaseByUuidWithinTeam($request->uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $this->authorize('delete', $database);

        DeleteResourceJob::dispatch(
            resource: $database,
            deleteVolumes: $request->boolean('delete_volumes', true),
            deleteConnectedNetworks: $request->boolean('delete_connected_networks', true),
            deleteConfigurations: $request->boolean('delete_configurations', true),
            dockerCleanup: $request->boolean('docker_cleanup', true)
        );

        return response()->json([
            'message' => 'Database deletion request queued.',
        ]);
    }
}
