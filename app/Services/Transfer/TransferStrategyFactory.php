<?php

namespace App\Services\Transfer;

use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use App\Services\Transfer\Strategies\ClickhouseTransferStrategy;
use App\Services\Transfer\Strategies\MariadbTransferStrategy;
use App\Services\Transfer\Strategies\MongodbTransferStrategy;
use App\Services\Transfer\Strategies\MysqlTransferStrategy;
use App\Services\Transfer\Strategies\PostgresqlTransferStrategy;
use App\Services\Transfer\Strategies\RedisTransferStrategy;

/**
 * Factory for creating transfer strategy instances.
 */
class TransferStrategyFactory
{
    /**
     * Get the appropriate transfer strategy for a database model.
     *
     * @param  mixed  $database  The database model instance
     *
     * @throws \InvalidArgumentException If database type is not supported
     */
    public static function getStrategy(mixed $database): ?TransferStrategyInterface
    {
        return match (true) {
            $database instanceof StandalonePostgresql => new PostgresqlTransferStrategy,
            $database instanceof StandaloneMysql => new MysqlTransferStrategy,
            $database instanceof StandaloneMariadb => new MariadbTransferStrategy,
            $database instanceof StandaloneMongodb => new MongodbTransferStrategy,
            $database instanceof StandaloneRedis => new RedisTransferStrategy,
            $database instanceof StandaloneKeydb => new RedisTransferStrategy,
            $database instanceof StandaloneDragonfly => new RedisTransferStrategy,
            $database instanceof StandaloneClickhouse => new ClickhouseTransferStrategy,
            default => throw new \InvalidArgumentException(
                'Unsupported database type: '.get_class($database)
            ),
        };
    }

    /**
     * Get strategy by database type string.
     *
     * @param  string  $type  Database type (e.g., 'standalone-postgresql', 'standalone-mysql')
     */
    public static function getStrategyByType(string $type): ?TransferStrategyInterface
    {
        return match ($type) {
            'standalone-postgresql' => new PostgresqlTransferStrategy,
            'standalone-mysql' => new MysqlTransferStrategy,
            'standalone-mariadb' => new MariadbTransferStrategy,
            'standalone-mongodb' => new MongodbTransferStrategy,
            'standalone-redis' => new RedisTransferStrategy,
            'standalone-keydb' => new RedisTransferStrategy,
            'standalone-dragonfly' => new RedisTransferStrategy,
            'standalone-clickhouse' => new ClickhouseTransferStrategy,
            default => null,
        };
    }

    /**
     * Check if a database type supports transfer.
     *
     * @param  mixed  $database  The database model instance
     */
    public static function supportsTransfer(mixed $database): bool
    {
        return $database instanceof StandalonePostgresql
            || $database instanceof StandaloneMysql
            || $database instanceof StandaloneMariadb
            || $database instanceof StandaloneMongodb
            || $database instanceof StandaloneRedis
            || $database instanceof StandaloneKeydb
            || $database instanceof StandaloneDragonfly
            || $database instanceof StandaloneClickhouse;
    }

    /**
     * Get list of supported database types.
     *
     * @return array<string, string> Map of class name to display name
     */
    public static function getSupportedTypes(): array
    {
        return [
            StandalonePostgresql::class => 'PostgreSQL',
            StandaloneMysql::class => 'MySQL',
            StandaloneMariadb::class => 'MariaDB',
            StandaloneMongodb::class => 'MongoDB',
            StandaloneRedis::class => 'Redis',
            StandaloneKeydb::class => 'KeyDB',
            StandaloneDragonfly::class => 'Dragonfly',
            StandaloneClickhouse::class => 'ClickHouse',
        ];
    }
}
