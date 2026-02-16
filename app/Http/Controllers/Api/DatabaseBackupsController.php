<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DatabaseBackupJob;
use App\Models\S3Storage;
use App\Models\ScheduledDatabaseBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

/**
 * API controller for database backup operations.
 *
 * Handles CRUD operations for scheduled database backups,
 * backup executions, and restore operations.
 */
class DatabaseBackupsController extends Controller
{
    #[OA\Get(
        summary: 'Get',
        description: 'Get backups details by database UUID.',
        path: '/databases/{uuid}/backups',
        operationId: 'get-database-backups-by-uuid',
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
                description: 'Get all backups for a database',
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
    public function index(Request $request)
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

        $backupConfig = ScheduledDatabaseBackup::ownedByCurrentTeamAPI($teamId)->with('executions')->where('database_id', $database->id)->get();

        return response()->json($backupConfig);
    }

    #[OA\Post(
        summary: 'Create Backup',
        description: 'Create a new scheduled backup configuration for a database',
        path: '/databases/{uuid}/backups',
        operationId: 'create-database-backup',
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
            description: 'Backup configuration data',
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    required: ['frequency'],
                    properties: [
                        new OA\Property(
                            property: 'frequency',
                            type: 'string',
                            description: 'Backup frequency (cron expression or: every_minute, hourly, daily, weekly, monthly, yearly)',
                        ),
                        new OA\Property(property: 'enabled', type: 'boolean', description: 'Whether the backup is enabled'),
                        new OA\Property(property: 'save_s3', type: 'boolean', description: 'Whether to save backups to S3'),
                        new OA\Property(property: 's3_storage_uuid', type: 'string', description: 'S3 storage UUID (required if save_s3 is true)'),
                        new OA\Property(property: 'databases_to_backup', type: 'string', description: 'Comma separated list of databases to backup'),
                        new OA\Property(property: 'dump_all', type: 'boolean', description: 'Whether to dump all databases'),
                        new OA\Property(property: 'backup_now', type: 'boolean', description: 'Whether to trigger backup immediately after creation'),
                        new OA\Property(
                            property: 'database_backup_retention_amount_locally',
                            type: 'integer',
                            description: 'Number of backups to retain locally',
                        ),
                        new OA\Property(
                            property: 'database_backup_retention_days_locally',
                            type: 'integer',
                            description: 'Number of days to retain backups locally',
                        ),
                        new OA\Property(
                            property: 'database_backup_retention_max_storage_locally',
                            type: 'integer',
                            description: 'Max storage (MB) for local backups',
                        ),
                        new OA\Property(property: 'database_backup_retention_amount_s3', type: 'integer', description: 'Number of backups to retain in S3'),
                        new OA\Property(property: 'database_backup_retention_days_s3', type: 'integer', description: 'Number of days to retain backups in S3'),
                        new OA\Property(property: 'database_backup_retention_max_storage_s3', type: 'integer', description: 'Max storage (MB) for S3 backups'),
                    ],
                ),
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Backup configuration created successfully',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'uuid', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                        new OA\Property(property: 'message', type: 'string', example: 'Backup configuration created successfully.'),
                    ]
                )
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
        $backupConfigFields = ['save_s3', 'enabled', 'dump_all', 'frequency', 'databases_to_backup', 'database_backup_retention_amount_locally', 'database_backup_retention_days_locally', 'database_backup_retention_max_storage_locally', 'database_backup_retention_amount_s3', 'database_backup_retention_days_s3', 'database_backup_retention_max_storage_s3', 's3_storage_uuid'];

        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // Validate incoming request is valid JSON
        $return = validateIncomingRequest($request);
        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }

        $validator = customApiValidator($request->all(), [
            'frequency' => 'required|string',
            'enabled' => 'boolean',
            'save_s3' => 'boolean',
            'dump_all' => 'boolean',
            'backup_now' => 'boolean|nullable',
            's3_storage_uuid' => 'string|exists:s3_storages,uuid|nullable',
            'databases_to_backup' => 'string|nullable',
            'database_backup_retention_amount_locally' => 'integer|min:0',
            'database_backup_retention_days_locally' => 'integer|min:0',
            'database_backup_retention_max_storage_locally' => 'integer|min:0',
            'database_backup_retention_amount_s3' => 'integer|min:0',
            'database_backup_retention_days_s3' => 'integer|min:0',
            'database_backup_retention_max_storage_s3' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! $request->uuid) {
            return response()->json(['message' => 'UUID is required.'], 404);
        }

        $uuid = $request->uuid;
        $database = queryDatabaseByUuidWithinTeam($uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $this->authorize('manageBackups', $database);

        // Validate frequency is a valid cron expression
        $isValid = validate_cron_expression($request->frequency);
        if (! $isValid) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['frequency' => ['Invalid cron expression or frequency format.']],
            ], 422);
        }

        // Validate S3 storage if save_s3 is true
        if ($request->boolean('save_s3') && ! $request->filled('s3_storage_uuid')) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['s3_storage_uuid' => ['The s3_storage_uuid field is required when save_s3 is true.']],
            ], 422);
        }

        if ($request->filled('s3_storage_uuid')) {
            $existsInTeam = S3Storage::ownedByCurrentTeam()->where('uuid', $request->s3_storage_uuid)->exists();
            if (! $existsInTeam) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['s3_storage_uuid' => ['The selected S3 storage is invalid for this team.']],
                ], 422);
            }
        }

        // Check for extra fields
        $extraFields = array_diff(array_keys($request->all()), $backupConfigFields, ['backup_now']);
        if (! empty($extraFields)) {
            $errors = $validator->errors();
            foreach ($extraFields as $field) {
                $errors->add($field, 'This field is not allowed.');
            }

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        $backupData = $request->only($backupConfigFields);

        // Convert s3_storage_uuid to s3_storage_id
        if (isset($backupData['s3_storage_uuid'])) {
            $s3Storage = S3Storage::ownedByCurrentTeam()->where('uuid', $backupData['s3_storage_uuid'])->first();
            if ($s3Storage) {
                $backupData['s3_storage_id'] = $s3Storage->id;
            } elseif ($request->boolean('save_s3')) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['s3_storage_uuid' => ['The selected S3 storage is invalid for this team.']],
                ], 422);
            }
            unset($backupData['s3_storage_uuid']);
        }

        // Set default databases_to_backup based on database type if not provided
        if (! isset($backupData['databases_to_backup']) || empty($backupData['databases_to_backup'])) {
            if ($database->type() === 'standalone-postgresql') {
                $backupData['databases_to_backup'] = $database->postgres_db;
            } elseif ($database->type() === 'standalone-mysql') {
                $backupData['databases_to_backup'] = $database->mysql_database;
            } elseif ($database->type() === 'standalone-mariadb') {
                $backupData['databases_to_backup'] = $database->mariadb_database;
            }
        }

        // Add required fields
        $backupData['database_id'] = $database->id;
        $backupData['database_type'] = $database->getMorphClass();
        $backupData['team_id'] = $teamId;

        // Set defaults
        if (! isset($backupData['enabled'])) {
            $backupData['enabled'] = true;
        }

        $backupConfig = ScheduledDatabaseBackup::create($backupData);

        // Trigger immediate backup if requested
        if ($request->backup_now) {
            dispatch(new DatabaseBackupJob($backupConfig));
        }

        return response()->json([
            'uuid' => $backupConfig->uuid,
            'message' => 'Backup configuration created successfully.',
        ], 201);
    }

    #[OA\Patch(
        summary: 'Update',
        description: 'Update a specific backup configuration for a given database, identified by its UUID and the backup ID',
        path: '/databases/{uuid}/backups/{scheduled_backup_uuid}',
        operationId: 'update-database-backup',
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
            new OA\Parameter(
                name: 'scheduled_backup_uuid',
                in: 'path',
                description: 'UUID of the backup configuration.',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    format: 'uuid',
                )
            ),
        ],
        requestBody: new OA\RequestBody(
            description: 'Database backup configuration data',
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'save_s3', type: 'boolean', description: 'Whether data is saved in s3 or not'),
                        new OA\Property(property: 's3_storage_uuid', type: 'string', description: 'S3 storage UUID'),
                        new OA\Property(property: 'backup_now', type: 'boolean', description: 'Whether to take a backup now or not'),
                        new OA\Property(property: 'enabled', type: 'boolean', description: 'Whether the backup is enabled or not'),
                        new OA\Property(property: 'databases_to_backup', type: 'string', description: 'Comma separated list of databases to backup'),
                        new OA\Property(property: 'dump_all', type: 'boolean', description: 'Whether all databases are dumped or not'),
                        new OA\Property(property: 'frequency', type: 'string', description: 'Frequency of the backup'),
                        new OA\Property(
                            property: 'database_backup_retention_amount_locally',
                            type: 'integer',
                            description: 'Retention amount of the backup locally',
                        ),
                        new OA\Property(
                            property: 'database_backup_retention_days_locally',
                            type: 'integer',
                            description: 'Retention days of the backup locally',
                        ),
                        new OA\Property(
                            property: 'database_backup_retention_max_storage_locally',
                            type: 'integer',
                            description: 'Max storage of the backup locally',
                        ),
                        new OA\Property(property: 'database_backup_retention_amount_s3', type: 'integer', description: 'Retention amount of the backup in s3'),
                        new OA\Property(property: 'database_backup_retention_days_s3', type: 'integer', description: 'Retention days of the backup in s3'),
                        new OA\Property(property: 'database_backup_retention_max_storage_s3', type: 'integer', description: 'Max storage of the backup in S3'),
                    ],
                ),
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Database backup configuration updated',
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
        $backupConfigFields = ['save_s3', 'enabled', 'dump_all', 'frequency', 'databases_to_backup', 'database_backup_retention_amount_locally', 'database_backup_retention_days_locally', 'database_backup_retention_max_storage_locally', 'database_backup_retention_amount_s3', 'database_backup_retention_days_s3', 'database_backup_retention_max_storage_s3', 's3_storage_uuid'];

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
            'save_s3' => 'boolean',
            'backup_now' => 'boolean|nullable',
            'enabled' => 'boolean',
            'dump_all' => 'boolean',
            's3_storage_uuid' => 'string|exists:s3_storages,uuid|nullable',
            'databases_to_backup' => 'string|nullable',
            'frequency' => 'string|in:every_minute,hourly,daily,weekly,monthly,yearly',
            'database_backup_retention_amount_locally' => 'integer|min:0',
            'database_backup_retention_days_locally' => 'integer|min:0',
            'database_backup_retention_max_storage_locally' => 'integer|min:0',
            'database_backup_retention_amount_s3' => 'integer|min:0',
            'database_backup_retention_days_s3' => 'integer|min:0',
            'database_backup_retention_max_storage_s3' => 'integer|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! $request->uuid) {
            return response()->json(['message' => 'UUID is required.'], 404);
        }

        // Validate scheduled_backup_uuid is provided
        if (! $request->scheduled_backup_uuid) {
            return response()->json(['message' => 'Scheduled backup UUID is required.'], 400);
        }

        $uuid = $request->uuid;
        removeUnnecessaryFieldsFromRequest($request);
        $database = queryDatabaseByUuidWithinTeam($uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $this->authorize('update', $database);

        if ($request->boolean('save_s3') && ! $request->filled('s3_storage_uuid')) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['s3_storage_uuid' => ['The s3_storage_uuid field is required when save_s3 is true.']],
            ], 422);
        }
        if ($request->filled('s3_storage_uuid')) {
            $existsInTeam = S3Storage::ownedByCurrentTeam()->where('uuid', $request->s3_storage_uuid)->exists();
            if (! $existsInTeam) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['s3_storage_uuid' => ['The selected S3 storage is invalid for this team.']],
                ], 422);
            }
        }

        $backupConfig = ScheduledDatabaseBackup::ownedByCurrentTeamAPI($teamId)->where('database_id', $database->id)
            ->where('uuid', $request->scheduled_backup_uuid)
            ->first();
        if (! $backupConfig) {
            return response()->json(['message' => 'Backup config not found.'], 404);
        }

        $extraFields = array_diff(array_keys($request->all()), $backupConfigFields, ['backup_now']);
        if (! empty($extraFields)) {
            $errors = $validator->errors();
            foreach ($extraFields as $field) {
                $errors->add($field, 'This field is not allowed.');
            }

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        $backupData = $request->only($backupConfigFields);

        // Convert s3_storage_uuid to s3_storage_id
        if (isset($backupData['s3_storage_uuid'])) {
            $s3Storage = S3Storage::ownedByCurrentTeam()->where('uuid', $backupData['s3_storage_uuid'])->first();
            if ($s3Storage) {
                $backupData['s3_storage_id'] = $s3Storage->id;
            } elseif ($request->boolean('save_s3')) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['s3_storage_uuid' => ['The selected S3 storage is invalid for this team.']],
                ], 422);
            }
            unset($backupData['s3_storage_uuid']);
        }

        $backupConfig->update($backupData);

        if ($request->backup_now) {
            dispatch(new DatabaseBackupJob($backupConfig));
        }

        return response()->json([
            'message' => 'Database backup configuration updated',
        ]);
    }

    #[OA\Delete(
        summary: 'Delete backup configuration',
        description: 'Deletes a backup configuration and all its executions.',
        path: '/databases/{uuid}/backups/{scheduled_backup_uuid}',
        operationId: 'delete-backup-configuration-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID of the database',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'scheduled_backup_uuid',
                in: 'path',
                required: true,
                description: 'UUID of the backup configuration to delete',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'delete_s3',
                in: 'query',
                required: false,
                description: 'Whether to delete all backup files from S3',
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Backup configuration deleted.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Backup configuration and all executions deleted.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Backup configuration not found.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Backup configuration not found.'),
                    ]
                )
            ),
        ]
    )]
    public function destroy(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // Validate scheduled_backup_uuid is provided
        if (! $request->scheduled_backup_uuid) {
            return response()->json(['message' => 'Scheduled backup UUID is required.'], 400);
        }

        $database = queryDatabaseByUuidWithinTeam($request->uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $this->authorize('update', $database);

        // Find the backup configuration by its UUID
        $backup = ScheduledDatabaseBackup::ownedByCurrentTeamAPI($teamId)->where('database_id', $database->id)
            ->where('uuid', $request->scheduled_backup_uuid)
            ->first();

        if (! $backup) {
            return response()->json(['message' => 'Backup configuration not found.'], 404);
        }

        $deleteS3 = $request->boolean('delete_s3', false);

        try {
            DB::beginTransaction();
            // Get all executions for this backup configuration
            $executions = $backup->executions()->get();

            // Delete all execution files (locally and optionally from S3)
            foreach ($executions as $execution) {
                if ($execution->filename) {
                    deleteBackupsLocally($execution->filename, $database->destination->server);

                    if ($deleteS3 && $backup->s3) {
                        deleteBackupsS3($execution->filename, $backup->s3);
                    }
                }

                $execution->delete();
            }

            // Delete the backup configuration itself
            $backup->delete();
            DB::commit();

            return response()->json([
                'message' => 'Backup configuration and all executions deleted.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Failed to delete backup: '.$e->getMessage()], 500);
        }
    }

    #[OA\Get(
        summary: 'List backup executions',
        description: 'Get all executions for a specific backup configuration.',
        path: '/databases/{uuid}/backups/{scheduled_backup_uuid}/executions',
        operationId: 'list-backup-executions',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID of the database',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'scheduled_backup_uuid',
                in: 'path',
                required: true,
                description: 'UUID of the backup configuration',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of backup executions',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'executions',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'uuid', type: 'string'),
                                    new OA\Property(property: 'filename', type: 'string'),
                                    new OA\Property(property: 'size', type: 'integer'),
                                    new OA\Property(property: 'created_at', type: 'string'),
                                    new OA\Property(property: 'message', type: 'string'),
                                    new OA\Property(property: 'status', type: 'string'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Backup configuration not found.',
            ),
        ]
    )]
    public function listExecutions(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // Validate scheduled_backup_uuid is provided
        if (! $request->scheduled_backup_uuid) {
            return response()->json(['message' => 'Scheduled backup UUID is required.'], 400);
        }

        $database = queryDatabaseByUuidWithinTeam($request->uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        // Find the backup configuration by its UUID
        $backup = ScheduledDatabaseBackup::ownedByCurrentTeamAPI($teamId)->where('database_id', $database->id)
            ->where('uuid', $request->scheduled_backup_uuid)
            ->first();

        if (! $backup) {
            return response()->json(['message' => 'Backup configuration not found.'], 404);
        }

        // Get all executions for this backup configuration
        $executions = $backup->executions()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($execution) {
                return [
                    'uuid' => $execution->uuid,
                    'filename' => $execution->filename,
                    'size' => $execution->size,
                    'created_at' => $execution->created_at->toIso8601String(),
                    'message' => $execution->message,
                    'status' => $execution->status,
                ];
            });

        return response()->json([
            'executions' => $executions,
        ]);
    }

    #[OA\Delete(
        summary: 'Delete backup execution',
        description: 'Deletes a specific backup execution.',
        path: '/databases/{uuid}/backups/{scheduled_backup_uuid}/executions/{execution_uuid}',
        operationId: 'delete-backup-execution-by-uuid',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(
                name: 'uuid',
                in: 'path',
                required: true,
                description: 'UUID of the database',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'scheduled_backup_uuid',
                in: 'path',
                required: true,
                description: 'UUID of the backup configuration',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'execution_uuid',
                in: 'path',
                required: true,
                description: 'UUID of the backup execution to delete',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'delete_s3',
                in: 'query',
                required: false,
                description: 'Whether to delete the backup from S3',
                schema: new OA\Schema(type: 'boolean', default: false)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Backup execution deleted.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Backup execution deleted.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Backup execution not found.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Backup execution not found.'),
                    ]
                )
            ),
        ]
    )]
    public function destroyExecution(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        // Validate parameters
        if (! $request->scheduled_backup_uuid) {
            return response()->json(['message' => 'Scheduled backup UUID is required.'], 400);
        }
        if (! $request->execution_uuid) {
            return response()->json(['message' => 'Execution UUID is required.'], 400);
        }

        $database = queryDatabaseByUuidWithinTeam($request->uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $this->authorize('update', $database);

        // Find the backup configuration by its UUID
        $backup = ScheduledDatabaseBackup::ownedByCurrentTeamAPI($teamId)->where('database_id', $database->id)
            ->where('uuid', $request->scheduled_backup_uuid)
            ->first();

        if (! $backup) {
            return response()->json(['message' => 'Backup configuration not found.'], 404);
        }

        // Find the specific execution
        $execution = $backup->executions()->where('uuid', $request->execution_uuid)->first();
        if (! $execution) {
            return response()->json(['message' => 'Backup execution not found.'], 404);
        }

        $deleteS3 = $request->boolean('delete_s3', false);

        try {
            if ($execution->filename) {
                deleteBackupsLocally($execution->filename, $database->destination->server);

                if ($deleteS3 && $backup->s3) {
                    deleteBackupsS3($execution->filename, $backup->s3);
                }
            }

            $execution->delete();

            return response()->json([
                'message' => 'Backup execution deleted.',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete backup execution: '.$e->getMessage()], 500);
        }
    }

    #[OA\Post(
        summary: 'Restore Backup',
        description: 'Restore database from a backup execution.',
        path: '/databases/{uuid}/backups/{backup_uuid}/restore',
        operationId: 'restore-database-backup',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Databases'],
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, description: 'Database UUID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'backup_uuid', in: 'path', required: true, description: 'Backup UUID', schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Restore options',
            required: false,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'execution_uuid', type: 'string', description: 'Specific backup execution UUID to restore from'),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Database restore initiated.',
                content: [
                    new OA\MediaType(
                        mediaType: 'application/json',
                        schema: new OA\Schema(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'message', type: 'string', example: 'Database restore initiated.'),
                            ]
                        )
                    ),
                ]),
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
    public function restore(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $database = queryDatabaseByUuidWithinTeam($request->uuid, $teamId);
        if (! $database) {
            return response()->json(['message' => 'Database not found.'], 404);
        }

        $this->authorize('manage', $database);

        $backup_uuid = $request->route('backup_uuid');
        if (! $backup_uuid) {
            return response()->json(['message' => 'Backup UUID is required.'], 400);
        }

        $backup = ScheduledDatabaseBackup::where('uuid', $backup_uuid)
            ->where('database_id', $database->id)
            ->first();

        if (! $backup) {
            return response()->json(['message' => 'Backup configuration not found.'], 404);
        }

        // Get the execution to restore from
        $execution_uuid = $request->input('execution_uuid');
        if ($execution_uuid) {
            $execution = $backup->executions()->where('uuid', $execution_uuid)->first();
        } else {
            // Get the latest successful execution
            $execution = $backup->executions()
                ->where('status', 'success')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if (! $execution) {
            return response()->json(['message' => 'No backup execution found to restore from.'], 404);
        }

        if ($execution->status !== 'success') {
            return response()->json(['message' => 'Cannot restore from a failed backup.'], 400);
        }

        // Check if restore is already in progress
        if ($execution->restore_status === 'in_progress') {
            return response()->json([
                'message' => 'Restore is already in progress for this backup.',
            ], 409);
        }

        try {
            // Dispatch the restore job
            \App\Jobs\DatabaseRestoreJob::dispatch($backup, $execution);

            return response()->json([
                'message' => 'Database restore initiated.',
                'backup_execution' => [
                    'uuid' => $execution->uuid,
                    'created_at' => $execution->created_at,
                    'filename' => $execution->filename,
                    'size' => $execution->size,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to initiate database restore.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
