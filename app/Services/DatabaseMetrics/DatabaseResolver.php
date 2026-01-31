<?php

namespace App\Services\DatabaseMetrics;

use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;

/**
 * Service for finding databases by UUID across all database types.
 */
class DatabaseResolver
{
    /**
     * Database type constants.
     */
    public const TYPE_POSTGRESQL = 'postgresql';

    public const TYPE_MYSQL = 'mysql';

    public const TYPE_MARIADB = 'mariadb';

    public const TYPE_MONGODB = 'mongodb';

    public const TYPE_REDIS = 'redis';

    public const TYPE_KEYDB = 'keydb';

    public const TYPE_DRAGONFLY = 'dragonfly';

    public const TYPE_CLICKHOUSE = 'clickhouse';

    /**
     * Find database by UUID across all database types.
     *
     * @return array{0: mixed, 1: string|null} [database, type]
     */
    public function findByUuid(string $uuid): array
    {
        $database = StandalonePostgresql::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, self::TYPE_POSTGRESQL];
        }

        $database = StandaloneMysql::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, self::TYPE_MYSQL];
        }

        $database = StandaloneMariadb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, self::TYPE_MARIADB];
        }

        $database = StandaloneMongodb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, self::TYPE_MONGODB];
        }

        $database = StandaloneRedis::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, self::TYPE_REDIS];
        }

        $database = StandaloneKeydb::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, self::TYPE_KEYDB];
        }

        $database = StandaloneDragonfly::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, self::TYPE_DRAGONFLY];
        }

        $database = StandaloneClickhouse::ownedByCurrentTeam()->where('uuid', $uuid)->first();
        if ($database) {
            return [$database, self::TYPE_CLICKHOUSE];
        }

        return [null, null];
    }

    /**
     * Check if type is SQL-capable database.
     */
    public function isSqlCapable(string $type): bool
    {
        return in_array($type, [
            self::TYPE_POSTGRESQL,
            self::TYPE_MYSQL,
            self::TYPE_MARIADB,
            self::TYPE_CLICKHOUSE,
        ]);
    }

    /**
     * Check if type is Redis-like database.
     */
    public function isRedisLike(string $type): bool
    {
        return in_array($type, [
            self::TYPE_REDIS,
            self::TYPE_KEYDB,
            self::TYPE_DRAGONFLY,
        ]);
    }

    /**
     * Check if type is MySQL-like database.
     */
    public function isMysqlLike(string $type): bool
    {
        return in_array($type, [
            self::TYPE_MYSQL,
            self::TYPE_MARIADB,
        ]);
    }
}
