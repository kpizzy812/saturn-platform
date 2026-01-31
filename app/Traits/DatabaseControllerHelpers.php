<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Helper methods for database controller operations.
 * Reduces boilerplate for common patterns like validation, error handling.
 */
trait DatabaseControllerHelpers
{
    /**
     * Execute a database operation with standard validation and error handling.
     *
     * @param  string  $uuid  Database UUID
     * @param  callable  $operation  Function receiving ($database, $server, $type)
     * @param  array|string|null  $requiredTypes  Required database type(s), null for any
     * @param  string  $errorPrefix  Prefix for error messages
     */
    protected function withDatabase(
        string $uuid,
        callable $operation,
        array|string|null $requiredTypes = null,
        string $errorPrefix = 'Operation failed'
    ): JsonResponse {
        [$database, $type] = $this->findDatabase($uuid);

        if (! $database) {
            return response()->json(['success' => false, 'available' => false, 'error' => 'Database not found'], 404);
        }

        // Check required types
        if ($requiredTypes !== null) {
            $allowedTypes = is_array($requiredTypes) ? $requiredTypes : [$requiredTypes];
            if (! in_array($type, $allowedTypes)) {
                $typeNames = implode('/', array_map('ucfirst', $allowedTypes));

                return response()->json([
                    'success' => false,
                    'available' => false,
                    'error' => "{$typeNames} database not found",
                ], 404);
            }
        }

        $server = $database->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return response()->json(['success' => false, 'available' => false, 'error' => 'Server not reachable'], 503);
        }

        try {
            return $operation($database, $server, $type);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'available' => false,
                'error' => "{$errorPrefix}: ".$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a success JSON response with 'available' flag.
     */
    protected function availableResponse(array $data): JsonResponse
    {
        return response()->json(array_merge(['available' => true], $data));
    }

    /**
     * Create a success JSON response with 'success' flag.
     */
    protected function successResponse(array $data = [], ?string $message = null): JsonResponse
    {
        $response = ['success' => true];
        if ($message) {
            $response['message'] = $message;
        }

        return response()->json(array_merge($response, $data));
    }

    /**
     * Create an error JSON response.
     */
    protected function errorResponse(string $error, int $status = 400): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $error], $status);
    }

    /**
     * Get Redis-like database types.
     */
    protected function getRedisTypes(): array
    {
        return ['redis', 'keydb', 'dragonfly'];
    }

    /**
     * Get SQL-capable database types.
     */
    protected function getSqlTypes(): array
    {
        return ['postgresql', 'mysql', 'mariadb', 'clickhouse'];
    }

    /**
     * Get MySQL-like database types.
     */
    protected function getMysqlTypes(): array
    {
        return ['mysql', 'mariadb'];
    }
}
