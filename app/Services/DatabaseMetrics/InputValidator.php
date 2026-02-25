<?php

namespace App\Services\DatabaseMetrics;

use InvalidArgumentException;

/**
 * Centralized input validation for DatabaseMetrics services.
 *
 * All user-controlled identifiers (table names, column names, extension names,
 * operation keywords, order directions) MUST be validated through this class
 * before being used in SQL queries or shell commands.
 */
class InputValidator
{
    /**
     * Allowed SQL maintenance operations for PostgreSQL.
     */
    public const POSTGRES_MAINTENANCE_OPS = ['VACUUM', 'ANALYZE', 'VACUUM ANALYZE', 'REINDEX'];

    /**
     * Allowed ORDER BY directions.
     */
    public const ORDER_DIRECTIONS = ['ASC', 'DESC'];

    /**
     * Validate a column name: must start with letter/underscore, contain only alphanumeric/underscore.
     */
    public static function isValidColumnName(string $column): bool
    {
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column);
    }

    /**
     * Validate a table name: allows schema.table format with alphanumeric, underscore, dot, hyphen.
     * Maximum length 128 characters.
     */
    public static function isValidTableName(string $tableName): bool
    {
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_.\-]{0,127}$/', $tableName);
    }

    /**
     * Validate a MongoDB/NoSQL field name: alphanumeric, underscore, dot.
     */
    public static function isValidFieldName(string $name): bool
    {
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $name);
    }

    /**
     * Validate a PostgreSQL extension name: lowercase alphanumeric and underscore.
     */
    public static function isValidExtensionName(string $name): bool
    {
        return (bool) preg_match('/^[a-z_][a-z0-9_]*$/i', $name);
    }

    /**
     * Validate a database username: starts with letter/underscore, alphanumeric/underscore only.
     */
    public static function isValidUsername(string $username): bool
    {
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $username);
    }

    /**
     * Validate and normalize ORDER BY direction. Returns 'ASC' or 'DESC'.
     *
     * @throws InvalidArgumentException if direction is not valid
     */
    public static function validateOrderDirection(string $direction): string
    {
        $normalized = strtoupper(trim($direction));
        if (! in_array($normalized, self::ORDER_DIRECTIONS, true)) {
            throw new InvalidArgumentException("Invalid ORDER BY direction: {$direction}");
        }

        return $normalized;
    }

    /**
     * Safely normalize ORDER BY direction, returning 'ASC' as fallback for invalid input.
     */
    public static function safeOrderDirection(string $direction): string
    {
        $normalized = strtoupper(trim($direction));

        return in_array($normalized, self::ORDER_DIRECTIONS, true) ? $normalized : 'ASC';
    }

    /**
     * Validate a PostgreSQL maintenance operation against the whitelist.
     *
     * @throws InvalidArgumentException if operation is not in the whitelist
     */
    public static function validateMaintenanceOperation(string $operation): string
    {
        $normalized = strtoupper(trim($operation));
        if (! in_array($normalized, self::POSTGRES_MAINTENANCE_OPS, true)) {
            throw new InvalidArgumentException("Invalid maintenance operation: {$operation}");
        }

        return $normalized;
    }

    /**
     * Sanitize a search string by removing SQL/NoSQL special characters.
     * Strips: single/double quotes, backslashes, semicolons, comments, dollar signs, null bytes.
     */
    public static function sanitizeSearch(string $search): string
    {
        return str_replace(["'", '"', '\\', ';', '--', '$', "\x00"], '', $search);
    }

    /**
     * Sanitize a search string for MongoDB regex context.
     * Strips characters that could break regex or inject JS.
     */
    public static function sanitizeMongoSearch(string $search): string
    {
        return preg_replace('/[\/\\\\.*+?|()\\[\\]{}^$;`"\']/', '', $search) ?? '';
    }

    /**
     * Validate a Redis key pattern: only safe glob characters allowed.
     */
    public static function isValidRedisPattern(string $pattern): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_:.*?\[\]-]+$/', $pattern);
    }

    /**
     * Validate a MongoDB collection name.
     */
    public static function isValidCollectionName(string $name): bool
    {
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_.\-]{0,127}$/', $name);
    }

    /**
     * Validate a MongoDB ObjectId: must be exactly 24 hex characters.
     */
    public static function isValidObjectId(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{24}$/i', $id);
    }
}
