<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\DatabaseMetric;
use App\Services\DatabaseMetrics\ClickhouseMetricsService;
use App\Services\DatabaseMetrics\DatabaseResolver;
use App\Services\DatabaseMetrics\InputValidator;
use App\Services\DatabaseMetrics\MongoMetricsService;
use App\Services\DatabaseMetrics\MysqlMetricsService;
use App\Services\DatabaseMetrics\PostgresMetricsService;
use App\Services\DatabaseMetrics\RedisMetricsService;
use App\Traits\DatabaseControllerHelpers;
use App\Traits\FormatHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for database metrics and operations.
 * Delegates to specialized services for each database type.
 */
class DatabaseMetricsController extends Controller
{
    use DatabaseControllerHelpers;
    use FormatHelpers;

    public function __construct(
        protected DatabaseResolver $databaseResolver,
        protected PostgresMetricsService $postgresService,
        protected MysqlMetricsService $mysqlService,
        protected RedisMetricsService $redisService,
        protected MongoMetricsService $mongoService,
        protected ClickhouseMetricsService $clickhouseService,
    ) {}

    public function getMetrics(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server, $type) => $this->availableResponse([
            'metrics' => $this->collectMetrics($db, $server, $type),
        ]), errorPrefix: 'Failed to collect metrics');
    }

    public function getHistoricalMetrics(Request $request, string $uuid): JsonResponse
    {
        [$database] = $this->findDatabase($uuid);
        if (! $database) {
            return response()->json(['available' => false, 'error' => 'Database not found'], 404);
        }

        $timeRange = in_array($tr = $request->input('timeRange', '24h'), ['1h', '6h', '24h', '7d', '30d']) ? $tr : '24h';
        $metrics = DatabaseMetric::getAggregatedMetrics($uuid, $timeRange);

        return $this->availableResponse([
            'hasHistoricalData' => ! empty($metrics['cpu']['data']) || ! empty($metrics['memory']['data']),
            'timeRange' => $timeRange,
            'metrics' => $metrics,
        ]);
    }

    protected function collectMetrics(mixed $database, mixed $server, string $type): array
    {
        return match ($type) {
            'postgresql' => $this->postgresService->collectMetrics($server, $database),
            'mysql', 'mariadb' => $this->mysqlService->collectMetrics($server, $database),
            'redis', 'keydb', 'dragonfly' => $this->redisService->collectMetrics($server, $database),
            'mongodb' => $this->mongoService->collectMetrics($server, $database),
            'clickhouse' => $this->clickhouseService->collectMetrics($server, $database),
            default => ['error' => 'Unsupported database type'],
        };
    }

    protected function findDatabase(string $uuid): array
    {
        return $this->databaseResolver->findByUuid($uuid);
    }

    /**
     * Authorize the current user against a database policy ability.
     */
    private function authorizeDatabase(string $uuid, string $ability): void
    {
        [$database] = $this->databaseResolver->findByUuid($uuid);
        if ($database) {
            $this->authorize($ability, $database);
        }
    }

    public function getExtensions(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse([
            'extensions' => $this->postgresService->getExtensions($server, $db),
        ]), 'postgresql', 'Failed to fetch extensions');
    }

    public function getUsers(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server, $type) => $this->availableResponse([
            'users' => match ($type) {
                'postgresql' => $this->postgresService->getUsers($server, $db),
                'mysql', 'mariadb' => $this->mysqlService->getUsers($server, $db),
                'mongodb' => $this->mongoService->getUsers($server, $db),
                default => [],
            },
        ]), errorPrefix: 'Failed to fetch users');
    }

    public function getLogs(Request $request, string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, function ($db, $server) use ($request) {
            $lines = min(max((int) $request->input('lines', 100), 10), 1000);
            $containerName = escapeshellarg($db->uuid);

            $checkCommand = "docker inspect --format='{{.State.Status}}' {$containerName} 2>&1";
            $containerStatus = trim(instant_remote_process([$checkCommand], $server, false) ?? '');

            if (str_contains($containerStatus, 'No such') || str_contains($containerStatus, 'Error')) {
                return $this->availableResponse(['logs' => [[
                    'timestamp' => date('Y-m-d H:i:s'),
                    'level' => 'WARNING',
                    'message' => 'Container is not running. The database may need to be started first.',
                ]]]);
            }

            $result = trim(instant_remote_process(["docker logs --tail {$lines} {$containerName} 2>&1 | tail -{$lines}"], $server, false) ?? '');
            $logs = $this->parseLogs($result);

            return $this->availableResponse(['logs' => array_slice($logs, -100)]);
        }, errorPrefix: 'Failed to fetch logs');
    }

    protected function parseLogs(string $result): array
    {
        $logs = [];
        foreach (explode("\n", $result) as $line) {
            if (empty($line = trim($line))) {
                continue;
            }
            $timestamp = date('Y-m-d H:i:s');
            $level = 'INFO';
            $message = $line;

            if (preg_match('/^(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}).*?(LOG|WARNING|ERROR|FATAL|PANIC|INFO|DEBUG|NOTICE):?\s*(.*)$/i', $line, $m)) {
                [$timestamp, $level, $message] = [$m[1], strtoupper($m[2]), $m[3]];
            } elseif (preg_match('/^(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}).*?\[(Note|Warning|Error|System)\]\s*(.*)$/i', $line, $m)) {
                $timestamp = $m[1];
                $level = in_array($l = strtoupper($m[2]), ['NOTE', 'SYSTEM']) ? 'INFO' : $l;
                $message = $m[3];
            }
            $logs[] = compact('timestamp', 'level', 'message');
        }

        return $logs;
    }

    public function executeQuery(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        $request->validate(['query' => 'required|string|max:10000']);
        $query = trim($request->input('query'));

        foreach (['/^\s*(DROP\s+DATABASE|DROP\s+USER|DROP\s+ROLE|TRUNCATE\s+ALL)/i', '/;\s*(DROP|TRUNCATE)/i'] as $pattern) {
            if (preg_match($pattern, $query)) {
                return $this->errorResponse('This query contains potentially dangerous operations and has been blocked');
            }
        }

        return $this->withDatabase($uuid, function ($db, $server, $type) use ($query) {
            $startTime = microtime(true);
            $result = match ($type) {
                'postgresql' => $this->postgresService->executeQuery($server, $db, $query),
                'mysql', 'mariadb' => $this->mysqlService->executeQuery($server, $db, $query),
                'clickhouse' => $this->clickhouseService->executeQuery($server, $db, $query),
                default => ['error' => 'Unsupported database type'],
            };

            if (isset($result['error'])) {
                return $this->errorResponse($result['error']);
            }

            return $this->successResponse([
                'columns' => $result['columns'] ?? [],
                'rows' => $result['rows'] ?? [],
                'rowCount' => $result['rowCount'] ?? 0,
                'executionTime' => round(microtime(true) - $startTime, 3),
            ]);
        }, $this->getSqlTypes(), 'Query execution failed');
    }

    public function getClickhouseQueryLog(Request $request, string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse([
            'queries' => $this->clickhouseService->getQueryLog($server, $db, min((int) $request->input('limit', 50), 100)),
        ]), 'clickhouse', 'Failed to fetch query log');
    }

    public function getClickhouseMergeStatus(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse(
            $this->clickhouseService->getMergeStatus($server, $db)
        ), 'clickhouse', 'Failed to fetch merge status');
    }

    public function getClickhouseReplicationStatus(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse(
            $this->clickhouseService->getReplicationStatus($server, $db)
        ), 'clickhouse', 'Failed to fetch replication status');
    }

    public function getClickhouseSettings(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse([
            'settings' => $this->clickhouseService->getSettings($server, $db),
        ]), 'clickhouse', 'Failed to fetch settings');
    }

    public function getMongoCollections(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse([
            'collections' => $this->mongoService->getCollections($server, $db),
        ]), 'mongodb', 'Failed to fetch collections');
    }

    public function getMongoIndexes(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse([
            'indexes' => $this->mongoService->getIndexes($server, $db),
        ]), 'mongodb', 'Failed to fetch indexes');
    }

    public function getMongoReplicaSet(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse([
            'replicaSet' => $this->mongoService->getReplicaSetStatus($server, $db),
        ]), 'mongodb', 'Failed to fetch replica set status');
    }

    public function getRedisKeys(Request $request, string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, function ($db, $server) use ($request) {
            $pattern = $request->input('pattern', '*');
            if (! InputValidator::isValidRedisPattern($pattern)) {
                return response()->json(['available' => false, 'error' => 'Invalid pattern format']);
            }

            return $this->availableResponse([
                'keys' => $this->redisService->getKeys($server, $db, $pattern, min((int) $request->input('limit', 100), 500)),
            ]);
        }, $this->getRedisTypes(), 'Failed to fetch keys');
    }

    public function getRedisMemory(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse([
            'memory' => $this->redisService->getMemoryInfo($server, $db),
        ]), $this->getRedisTypes(), 'Failed to fetch memory info');
    }

    public function redisFlush(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        $flushType = $request->input('type', 'db');
        if (! in_array($flushType, ['db', 'all'])) {
            return $this->errorResponse('Invalid flush type');
        }

        return $this->withDatabase($uuid, function ($db, $server) use ($flushType) {
            $result = $this->redisService->flush($server, $db, $flushType);

            return $result['success']
                ? $this->successResponse(message: $flushType === 'all' ? 'All databases flushed' : 'Current database flushed')
                : $this->errorResponse($result['error'] ?? 'Flush command failed');
        }, $this->getRedisTypes(), 'Failed to flush');
    }

    public function postgresMaintenace(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        $operation = $request->input('operation', 'vacuum');
        // Validate against centralized whitelist (defense-in-depth)
        try {
            InputValidator::validateMaintenanceOperation($operation);
        } catch (\InvalidArgumentException) {
            return $this->errorResponse('Invalid operation. Allowed: vacuum, analyze');
        }

        return $this->withDatabase($uuid, function ($db, $server) use ($operation) {
            $result = $this->postgresService->runMaintenance($server, $db, $operation);

            return $result['success']
                ? $this->successResponse(message: $result['message'] ?? strtoupper($operation).' completed successfully')
                : $this->errorResponse($result['error'] ?? 'Maintenance failed');
        }, 'postgresql', 'Failed to run maintenance');
    }

    public function getMysqlSettings(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse([
            'settings' => $this->mysqlService->getSettings($server, $db),
        ]), $this->getMysqlTypes(), 'Failed to fetch MySQL settings');
    }

    public function getRedisPersistence(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse([
            'persistence' => $this->redisService->getPersistenceSettings($server, $db),
        ]), $this->getRedisTypes(), 'Failed to fetch Redis persistence settings');
    }

    public function getMongoStorageSettings(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server) => $this->availableResponse([
            'settings' => $this->mongoService->getStorageSettings($server, $db),
        ]), 'mongodb', 'Failed to fetch MongoDB storage settings');
    }

    public function toggleExtension(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'update');

        $extensionName = $request->input('extension');
        if (! $extensionName || ! InputValidator::isValidExtensionName($extensionName)) {
            return $this->errorResponse('Invalid extension name');
        }

        return $this->withDatabase($uuid, fn ($db, $server) => response()->json(
            $this->postgresService->toggleExtension($server, $db, $extensionName, $request->boolean('enable', true))
        ), 'postgresql', 'Failed to toggle extension');
    }

    public function getActiveConnections(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server, $type) => $this->availableResponse([
            'connections' => match ($type) {
                'postgresql' => $this->postgresService->getActiveConnections($server, $db),
                'mysql', 'mariadb' => $this->mysqlService->getActiveConnections($server, $db),
                default => [],
            },
        ]), errorPrefix: 'Failed to fetch active connections');
    }

    public function killConnection(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        $request->validate(['pid' => 'required|integer']);
        $pid = (int) $request->input('pid');

        return $this->withDatabase($uuid, function ($db, $server, $type) use ($pid) {
            $result = match ($type) {
                'postgresql' => $this->postgresService->killConnection($server, $db, $pid),
                'mysql', 'mariadb' => $this->mysqlService->killConnection($server, $db, $pid),
                default => false,
            };

            return $result
                ? $this->successResponse(message: "Connection PID {$pid} terminated")
                : $this->errorResponse("Failed to terminate connection PID {$pid}");
        }, errorPrefix: 'Failed to kill connection');
    }

    public function createUser(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        $request->validate([
            'username' => ['required', 'string', 'max:63', 'regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/'],
            'password' => 'required|string|min:8',
        ]);

        return $this->withDatabase($uuid, fn ($db, $server, $type) => response()->json(match ($type) {
            'postgresql' => $this->postgresService->createUser($server, $db, $request->input('username'), $request->input('password')),
            'mysql', 'mariadb' => $this->mysqlService->createUser($server, $db, $request->input('username'), $request->input('password')),
            default => ['success' => false, 'error' => 'User creation not supported for this database type'],
        }), errorPrefix: 'Failed to create user');
    }

    public function deleteUser(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        $request->validate(['username' => 'required|string']);
        $username = $request->input('username');

        return $this->withDatabase($uuid, function ($db, $server, $type) use ($username) {
            $protected = match ($type) {
                'postgresql' => ['postgres'],
                'mysql', 'mariadb' => ['root', 'mysql.sys', 'mysql.session', 'mysql.infoschema'],
                default => [],
            };

            if (in_array($username, $protected)) {
                return $this->errorResponse("Cannot delete system user: {$username}");
            }

            return response()->json(match ($type) {
                'postgresql' => $this->postgresService->deleteUser($server, $db, $username),
                'mysql', 'mariadb' => $this->mysqlService->deleteUser($server, $db, $username),
                default => ['success' => false, 'error' => 'User deletion not supported for this database type'],
            });
        }, errorPrefix: 'Failed to delete user');
    }

    public function createMongoIndex(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'update');

        $request->validate(['collection' => 'required|string|max:255', 'fields' => 'required|string', 'unique' => 'boolean']);

        return $this->withDatabase($uuid, function ($db, $server) use ($request) {
            $collection = preg_replace('/[^a-zA-Z0-9_.]/', '', $request->input('collection'));
            if (empty($collection) || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $collection)) {
                return $this->errorResponse('Invalid collection name');
            }

            $indexSpec = [];
            foreach (explode(',', $request->input('fields')) as $part) {
                $kv = explode(':', trim($part));
                $fieldName = trim($kv[0]);
                if (! empty($fieldName) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $fieldName)) {
                    $indexSpec[$fieldName] = isset($kv[1]) ? (int) trim($kv[1]) : 1;
                }
            }

            if (empty($indexSpec)) {
                return $this->errorResponse('Invalid field specification');
            }

            return response()->json($this->mongoService->createIndex($server, $db, $collection, $indexSpec, $request->boolean('unique')));
        }, 'mongodb', 'Failed to create index');
    }

    public function deleteRedisKey(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        $request->validate(['key' => 'required|string|max:1024']);

        return $this->withDatabase($uuid, fn ($db, $server) => response()->json(
            $this->redisService->deleteKey($server, $db, $request->input('key'))
        ), $this->getRedisTypes(), 'Failed to delete key');
    }

    public function getRedisKeyValue(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['key' => 'required|string|max:1024']);

        return $this->withDatabase($uuid, fn ($db, $server) => response()->json(
            $this->redisService->getKeyValue($server, $db, $request->input('key'))
        ), $this->getRedisTypes(), 'Failed to get key value');
    }

    public function setRedisKeyValue(Request $request, string $uuid): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        $request->validate([
            'key' => 'required|string|max:1024',
            'type' => 'required|string|in:string,list,set,zset,hash',
            'value' => 'required',
            'ttl' => 'nullable|integer|min:-1',
        ]);

        return $this->withDatabase($uuid, fn ($db, $server) => response()->json(
            $this->redisService->setKeyValue($server, $db, $request->input('key'), $request->input('type'), $request->input('value'), $request->input('ttl', -1))
        ), $this->getRedisTypes(), 'Failed to set key value');
    }

    public function getTablesList(string $uuid): JsonResponse
    {
        return $this->withDatabase($uuid, fn ($db, $server, $type) => $this->availableResponse([
            'tables' => match ($type) {
                'postgresql' => $this->postgresService->getTables($server, $db),
                'mysql', 'mariadb' => $this->mysqlService->getTables($server, $db),
                'mongodb' => $this->mongoService->getTables($server, $db),
                'clickhouse' => $this->clickhouseService->getTables($server, $db),
                default => [],
            },
            'type' => $type,
        ]), errorPrefix: 'Failed to fetch tables');
    }

    public function testS3Connection(Request $request): JsonResponse
    {
        $request->validate([
            'bucket' => 'required|string',
            'region' => 'required|string',
            'access_key' => 'required|string',
            'secret_key' => 'required|string',
            'endpoint' => 'nullable|url',
        ]);

        try {
            $config = [
                'driver' => 's3',
                'key' => $request->input('access_key'),
                'secret' => $request->input('secret_key'),
                'region' => $request->input('region'),
                'bucket' => $request->input('bucket'),
            ];

            if ($endpoint = $request->input('endpoint')) {
                // SSRF protection: block private/reserved/link-local IP ranges
                $parsed = parse_url($endpoint);
                $host = $parsed['host'] ?? '';
                $resolvedIp = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

                if (filter_var($resolvedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    return $this->errorResponse('Invalid endpoint: private or reserved addresses are not allowed');
                }

                // Block link-local (169.254.x.x) and shared address space (100.64.0.0/10)
                if (str_starts_with($resolvedIp, '169.254.') || (ip2long($resolvedIp) >= ip2long('100.64.0.0') && ip2long($resolvedIp) <= ip2long('100.127.255.255'))) {
                    return $this->errorResponse('Invalid endpoint: link-local and shared addresses are not allowed');
                }

                $config['endpoint'] = $endpoint;
                $config['use_path_style_endpoint'] = true;
            }

            \Illuminate\Support\Facades\Storage::build($config)->files('/', false);

            return $this->successResponse(message: 'S3 connection successful');
        } catch (\Exception $e) {
            return $this->errorResponse('S3 connection failed: '.$e->getMessage());
        }
    }

    public function regeneratePassword(string $uuid): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        return $this->withDatabase($uuid, function ($db, $server, $type) {
            $passwordField = match ($type) {
                'postgresql' => 'postgres_password',
                'mysql' => 'mysql_password',
                'mariadb' => 'mariadb_password',
                'mongodb' => 'mongo_initdb_root_password',
                'redis' => 'redis_password',
                'keydb' => 'keydb_password',
                'dragonfly' => 'dragonfly_password',
                'clickhouse' => 'clickhouse_admin_password',
                default => null,
            };

            if (! $passwordField) {
                return $this->errorResponse('Unsupported database type');
            }

            $db->{$passwordField} = \Illuminate\Support\Str::password(32);
            $db->save();

            $safeUuid = escapeshellarg($db->uuid);
            instant_remote_process(["docker restart {$safeUuid} 2>&1"], $server, false);

            return $this->successResponse(message: 'Password regenerated and database restarting. Services using this database will need redeployment.');
        }, errorPrefix: 'Failed to regenerate password');
    }

    /**
     * Validate table/collection name to prevent SQL/NoSQL injection.
     */
    private function validateTableName(string $tableName): bool
    {
        return InputValidator::isValidTableName($tableName);
    }

    public function getTableColumns(string $uuid, string $tableName): JsonResponse
    {
        if (! $this->validateTableName($tableName)) {
            return $this->errorResponse('Invalid table name.', 400);
        }

        return $this->withDatabase($uuid, fn ($db, $server, $type) => $this->successResponse([
            'columns' => match ($type) {
                'postgresql' => $this->postgresService->getColumns($server, $db, $tableName),
                'mysql', 'mariadb' => $this->mysqlService->getColumns($server, $db, $tableName),
                'mongodb' => $this->mongoService->getColumns($server, $db, $tableName),
                default => [],
            },
        ]), errorPrefix: 'Failed to fetch columns');
    }

    public function getTableData(Request $request, string $uuid, string $tableName): JsonResponse
    {
        if (! $this->validateTableName($tableName)) {
            return $this->errorResponse('Invalid table name.', 400);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(100, max(10, (int) $request->input('per_page', 50)));
        $search = $request->input('search', '') ?? '';
        $orderBy = $request->input('order_by', '') ?? '';
        $orderDir = $request->input('order_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        // Sanitize search: strip SQL/NoSQL special chars via centralized validator
        $search = InputValidator::sanitizeSearch($search);

        // Remove raw $filters entirely — it was passed directly into SQL, creating injection risk
        $filters = '';

        return $this->withDatabase($uuid, function ($db, $server, $type) use ($tableName, $page, $perPage, $search, $orderBy, $orderDir, $filters) {
            $result = match ($type) {
                'postgresql' => $this->postgresService->getData($server, $db, $tableName, $page, $perPage, $search, $orderBy, $orderDir, $filters),
                'mysql', 'mariadb' => $this->mysqlService->getData($server, $db, $tableName, $page, $perPage, $search, $orderBy, $orderDir, $filters),
                'mongodb' => $this->mongoService->getData($server, $db, $tableName, $page, $perPage, $search, $orderBy, $orderDir, $filters),
                default => ['rows' => [], 'total' => 0, 'columns' => []],
            };

            return $this->successResponse([
                'data' => $result['rows'],
                'columns' => $result['columns'],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $result['total'],
                    'last_page' => (int) ceil($result['total'] / $perPage),
                ],
            ]);
        }, errorPrefix: 'Failed to fetch data');
    }

    public function updateTableRow(Request $request, string $uuid, string $tableName): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        if (! $this->validateTableName($tableName)) {
            return $this->errorResponse('Invalid table name.', 400);
        }

        $request->validate(['primary_key' => 'required|array', 'updates' => 'required|array']);

        return $this->withDatabase($uuid, function ($db, $server, $type) use ($request, $tableName) {
            $success = match ($type) {
                'postgresql' => $this->postgresService->updateRow($server, $db, $tableName, $request->input('primary_key'), $request->input('updates')),
                'mysql', 'mariadb' => $this->mysqlService->updateRow($server, $db, $tableName, $request->input('primary_key'), $request->input('updates')),
                'mongodb' => $this->mongoService->updateRow($server, $db, $tableName, $request->input('primary_key'), $request->input('updates')),
                default => false,
            };

            return $success ? $this->successResponse(message: 'Row updated successfully') : $this->errorResponse('Failed to update row', 500);
        }, errorPrefix: 'Failed to update row');
    }

    public function deleteTableRow(Request $request, string $uuid, string $tableName): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        if (! $this->validateTableName($tableName)) {
            return $this->errorResponse('Invalid table name.', 400);
        }

        $request->validate(['primary_key' => 'required|array']);

        return $this->withDatabase($uuid, function ($db, $server, $type) use ($request, $tableName) {
            $success = match ($type) {
                'postgresql' => $this->postgresService->deleteRow($server, $db, $tableName, $request->input('primary_key')),
                'mysql', 'mariadb' => $this->mysqlService->deleteRow($server, $db, $tableName, $request->input('primary_key')),
                'mongodb' => $this->mongoService->deleteRow($server, $db, $tableName, $request->input('primary_key')),
                default => false,
            };

            return $success ? $this->successResponse(message: 'Row deleted successfully') : $this->errorResponse('Failed to delete row', 500);
        }, errorPrefix: 'Failed to delete row');
    }

    public function createTableRow(Request $request, string $uuid, string $tableName): JsonResponse
    {
        $this->authorizeDatabase($uuid, 'manage');

        if (! $this->validateTableName($tableName)) {
            return $this->errorResponse('Invalid table name.', 400);
        }

        $request->validate(['data' => 'required|array']);

        return $this->withDatabase($uuid, function ($db, $server, $type) use ($request, $tableName) {
            $success = match ($type) {
                'postgresql' => $this->postgresService->createRow($server, $db, $tableName, $request->input('data')),
                'mysql', 'mariadb' => $this->mysqlService->createRow($server, $db, $tableName, $request->input('data')),
                'mongodb' => $this->mongoService->createRow($server, $db, $tableName, $request->input('data')),
                default => false,
            };

            return $success ? $this->successResponse(message: 'Row created successfully') : $this->errorResponse('Failed to create row', 500);
        }, errorPrefix: 'Failed to create row');
    }
}
