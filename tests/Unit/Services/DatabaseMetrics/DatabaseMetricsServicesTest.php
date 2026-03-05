<?php

namespace Tests\Unit\Services\DatabaseMetrics;

use Tests\TestCase;

/**
 * Unit tests for DatabaseMetrics Services:
 * PostgresMetricsService, MysqlMetricsService, RedisMetricsService.
 */
class DatabaseMetricsServicesTest extends TestCase
{
    // =========================================================================
    // PostgresMetricsService
    // =========================================================================

    /** @test */
    public function postgres_metrics_collects_connection_metrics(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/PostgresMetricsService.php'));

        $this->assertStringContainsString("'activeConnections'", $source);
        $this->assertStringContainsString("'maxConnections'", $source);
        $this->assertStringContainsString("'databaseSize'", $source);
    }

    /** @test */
    public function postgres_metrics_queries_system_views(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/PostgresMetricsService.php'));

        $this->assertStringContainsString('pg_stat_activity', $source);
        $this->assertStringContainsString('pg_database_size', $source);
        $this->assertStringContainsString('SHOW max_connections', $source);
    }

    /** @test */
    public function postgres_metrics_manages_extensions(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/PostgresMetricsService.php'));

        $this->assertStringContainsString('getExtensions', $source);
        $this->assertStringContainsString('toggleExtension', $source);
        $this->assertStringContainsString('pg_extension', $source);
    }

    /** @test */
    public function postgres_metrics_manages_users(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/PostgresMetricsService.php'));

        $this->assertStringContainsString('CREATE ROLE', $source);
        $this->assertStringContainsString('GRANT CONNECT ON DATABASE', $source);
        $this->assertStringContainsString('REVOKE ALL PRIVILEGES', $source);
        $this->assertStringContainsString('pg_roles', $source);
    }

    /** @test */
    public function postgres_metrics_runs_maintenance(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/PostgresMetricsService.php'));

        $this->assertStringContainsString('VACUUM', $source);
        $this->assertStringContainsString('ANALYZE', $source);
    }

    /** @test */
    public function postgres_metrics_kills_connections(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/PostgresMetricsService.php'));

        $this->assertStringContainsString('pg_terminate_backend', $source);
    }

    /** @test */
    public function postgres_metrics_queries_tables(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/PostgresMetricsService.php'));

        $this->assertStringContainsString('pg_stat_user_tables', $source);
        $this->assertStringContainsString('information_schema.columns', $source);
    }

    /** @test */
    public function postgres_metrics_supports_crud_operations(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/PostgresMetricsService.php'));

        $this->assertStringContainsString('updateRow', $source);
        $this->assertStringContainsString('deleteRow', $source);
        $this->assertStringContainsString('createRow', $source);
    }

    // =========================================================================
    // MysqlMetricsService
    // =========================================================================

    /** @test */
    public function mysql_metrics_collects_status_variables(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/MysqlMetricsService.php'));

        $this->assertStringContainsString('Threads_connected', $source);
        $this->assertStringContainsString('max_connections', $source);
        $this->assertStringContainsString('Slow_queries', $source);
    }

    /** @test */
    public function mysql_metrics_checks_settings(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/MysqlMetricsService.php'));

        $this->assertStringContainsString('slow_query_log', $source);
        $this->assertStringContainsString('log_bin', $source);
        $this->assertStringContainsString('innodb_buffer_pool_size', $source);
        $this->assertStringContainsString('wait_timeout', $source);
    }

    /** @test */
    public function mysql_metrics_manages_users(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/MysqlMetricsService.php'));

        $this->assertStringContainsString('CREATE USER', $source);
        $this->assertStringContainsString('GRANT ALL PRIVILEGES', $source);
    }

    /** @test */
    public function mysql_metrics_queries_information_schema(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/MysqlMetricsService.php'));

        $this->assertStringContainsString('INFORMATION_SCHEMA.PROCESSLIST', $source);
        $this->assertStringContainsString('INFORMATION_SCHEMA.TABLES', $source);
        $this->assertStringContainsString('INFORMATION_SCHEMA.COLUMNS', $source);
    }

    /** @test */
    public function mysql_metrics_kills_connections(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/MysqlMetricsService.php'));

        $this->assertStringContainsString('killConnection', $source);
    }

    /** @test */
    public function mysql_metrics_supports_crud_operations(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/MysqlMetricsService.php'));

        $this->assertStringContainsString('updateRow', $source);
        $this->assertStringContainsString('deleteRow', $source);
        $this->assertStringContainsString('createRow', $source);
    }

    // =========================================================================
    // RedisMetricsService
    // =========================================================================

    /** @test */
    public function redis_metrics_parses_info_output(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/RedisMetricsService.php'));

        $this->assertStringContainsString('used_memory_human:', $source);
        $this->assertStringContainsString('db0:keys=', $source);
        $this->assertStringContainsString('instantaneous_ops_per_sec:', $source);
    }

    /** @test */
    public function redis_metrics_tracks_cache_hit_rate(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/RedisMetricsService.php'));

        $this->assertStringContainsString('keyspace_hits:', $source);
        $this->assertStringContainsString('keyspace_misses:', $source);
    }

    /** @test */
    public function redis_metrics_uses_redis_cli(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/RedisMetricsService.php'));

        $this->assertStringContainsString('redis-cli', $source);
        $this->assertStringContainsString('INFO', $source);
        $this->assertStringContainsString('--no-auth-warning', $source);
    }

    /** @test */
    public function redis_metrics_manages_keys(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/RedisMetricsService.php'));

        $this->assertStringContainsString('getKeys', $source);
        $this->assertStringContainsString('deleteKey', $source);
        $this->assertStringContainsString('TTL', $source);
        $this->assertStringContainsString('TYPE', $source);
        $this->assertStringContainsString('MEMORY USAGE', $source);
    }

    /** @test */
    public function redis_metrics_retrieves_values_by_type(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/RedisMetricsService.php'));

        $this->assertStringContainsString('LRANGE', $source);
        $this->assertStringContainsString('HGETALL', $source);
        $this->assertStringContainsString('ZRANGE', $source);
    }

    /** @test */
    public function redis_metrics_supports_flush(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/RedisMetricsService.php'));

        $this->assertStringContainsString('FLUSHDB', $source);
        $this->assertStringContainsString('FLUSHALL', $source);
    }

    /** @test */
    public function redis_metrics_gets_persistence_settings(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/RedisMetricsService.php'));

        $this->assertStringContainsString('getPersistenceSettings', $source);
        $this->assertStringContainsString('CONFIG GET', $source);
    }

    /** @test */
    public function redis_metrics_gets_memory_info(): void
    {
        $source = file_get_contents(app_path('Services/DatabaseMetrics/RedisMetricsService.php'));

        $this->assertStringContainsString('getMemoryInfo', $source);
    }
}
