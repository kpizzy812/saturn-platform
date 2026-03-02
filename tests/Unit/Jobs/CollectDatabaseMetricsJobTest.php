<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CollectDatabaseMetricsJob;
use App\Models\Server;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Laravel\Horizon\Contracts\Silenced;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for CollectDatabaseMetricsJob.
 *
 * The job collects container stats (CPU, memory, network) and database-specific
 * metrics for all 8 supported database types via SSH.
 *
 * SSH-dependent paths are exercised in Feature tests. These unit tests cover:
 * - Job configuration and interfaces
 * - Pure metric-parsing methods (reflection-based invocation)
 * - Database type detection logic
 * - bytes formatting helpers
 */
class CollectDatabaseMetricsJobTest extends TestCase
{
    private Server $server;

    private CollectDatabaseMetricsJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        // Minimal mock server — no SSH connection is made in unit tests
        $this->server = Mockery::mock(Server::class)->makePartial();
        $this->server->shouldReceive('getAttribute')->with('id')->andReturn(99);
        $this->server->shouldReceive('getAttribute')->with('name')->andReturn('test-server');
        $this->server->shouldReceive('getAttribute')->with('uuid')->andReturn('server-uuid-test');

        $this->job = new CollectDatabaseMetricsJob($this->server);
    }

    // =========================================================================
    // Job configuration
    // =========================================================================

    public function test_job_implements_should_queue(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(CollectDatabaseMetricsJob::class));
    }

    public function test_job_implements_should_be_encrypted(): void
    {
        $this->assertContains(ShouldBeEncrypted::class, class_implements(CollectDatabaseMetricsJob::class));
    }

    public function test_job_implements_silenced(): void
    {
        // Silenced suppresses the job from Horizon's UI to reduce noise
        $this->assertContains(Silenced::class, class_implements(CollectDatabaseMetricsJob::class));
    }

    public function test_job_has_tries_1(): void
    {
        $reflection = new \ReflectionClass(CollectDatabaseMetricsJob::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(1, $defaults['tries']);
    }

    public function test_job_has_60_second_timeout(): void
    {
        $reflection = new \ReflectionClass(CollectDatabaseMetricsJob::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(60, $defaults['timeout']);
    }

    // =========================================================================
    // Middleware — per-server WithoutOverlapping
    // =========================================================================

    public function test_middleware_returns_single_without_overlapping(): void
    {
        $middleware = $this->job->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
    }

    public function test_middleware_lock_key_includes_server_uuid(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        // Each server must get its own lock — otherwise all servers share one slot
        $this->assertStringContainsString('collect-db-metrics-', $source);
        $this->assertStringContainsString('$this->server->uuid', $source);
    }

    public function test_middleware_expires_after_60_seconds(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        $this->assertStringContainsString('->expireAfter(60)', $source);
    }

    public function test_middleware_uses_dont_release(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        $this->assertStringContainsString('->dontRelease()', $source);
    }

    // =========================================================================
    // failed() handler
    // =========================================================================

    public function test_failed_method_logs_error_with_server_context(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        $this->assertStringContainsString('Log::error(', $source);
        $this->assertStringContainsString('CollectDatabaseMetricsJob permanently failed', $source);
        $this->assertStringContainsString('server_id', $source);
        $this->assertStringContainsString('server_name', $source);
    }

    // =========================================================================
    // getDatabaseType() — all 8 types + default
    // =========================================================================

    /** @dataProvider databaseTypeProvider */
    public function test_get_database_type_returns_correct_string(object $dbModel, string $expectedType): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'getDatabaseType');

        $result = $method->invoke($this->job, $dbModel);

        $this->assertEquals($expectedType, $result);
    }

    public function test_get_database_type_returns_unknown_for_unrecognized_type(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'getDatabaseType');

        $unknownDb = new class {};
        $result = $method->invoke($this->job, $unknownDb);

        $this->assertEquals('unknown', $result);
    }

    public static function databaseTypeProvider(): array
    {
        return [
            'postgresql' => [Mockery::mock(StandalonePostgresql::class)->makePartial(), 'postgresql'],
            'mysql' => [Mockery::mock(StandaloneMysql::class)->makePartial(), 'mysql'],
            'mariadb' => [Mockery::mock(StandaloneMariadb::class)->makePartial(), 'mariadb'],
            'mongodb' => [Mockery::mock(StandaloneMongodb::class)->makePartial(), 'mongodb'],
            'redis' => [Mockery::mock(StandaloneRedis::class)->makePartial(), 'redis'],
            'keydb' => [Mockery::mock(StandaloneKeydb::class)->makePartial(), 'keydb'],
            'dragonfly' => [Mockery::mock(StandaloneDragonfly::class)->makePartial(), 'dragonfly'],
            'clickhouse' => [Mockery::mock(StandaloneClickhouse::class)->makePartial(), 'clickhouse'],
        ];
    }

    // =========================================================================
    // parseCpuPercent()
    // =========================================================================

    /** @dataProvider cpuPercentProvider */
    public function test_parse_cpu_percent(string $input, float $expected): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'parseCpuPercent');

        $this->assertEquals($expected, $method->invoke($this->job, $input));
    }

    public static function cpuPercentProvider(): array
    {
        return [
            'normal value' => ['12.50%', 12.5],
            'zero' => ['0.00%', 0.0],
            'hundred' => ['100.00%', 100.0],
            'fractional' => ['0.01%', 0.01],
            'no fraction' => ['5%', 5.0],
        ];
    }

    // =========================================================================
    // parseMemoryBytes() — takes first part of "used / limit"
    // =========================================================================

    public function test_parse_memory_bytes_takes_used_part(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'parseMemoryBytes');

        // "512MiB / 2GiB" → 512 * 1024 * 1024
        $result = $method->invoke($this->job, '512MiB / 2GiB');

        $this->assertEquals(512 * 1024 * 1024, $result);
    }

    public function test_parse_memory_bytes_handles_bytes_unit(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'parseMemoryBytes');

        $result = $method->invoke($this->job, '1024B / 2GiB');

        $this->assertEquals(1024, $result);
    }

    // =========================================================================
    // parseMemoryLimit() — takes second part of "used / limit"
    // =========================================================================

    public function test_parse_memory_limit_takes_limit_part(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'parseMemoryLimit');

        // "512MiB / 4GiB" → 4 * 1024^3
        $result = $method->invoke($this->job, '512MiB / 4GiB');

        $this->assertEquals(4 * 1024 * 1024 * 1024, $result);
    }

    public function test_parse_memory_limit_handles_missing_second_part(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'parseMemoryLimit');

        // Gracefully handle malformed input
        $result = $method->invoke($this->job, '0B');

        $this->assertEquals(0, $result);
    }

    // =========================================================================
    // parseNetworkBytes() — rx is first, tx is second
    // =========================================================================

    public function test_parse_network_bytes_rx_takes_first_part(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'parseNetworkBytes');

        // "1.5MB / 300KB" — rx = 1.5MB
        $result = $method->invoke($this->job, '1.5MB / 300KB', 'rx');

        $this->assertEquals((int) (1.5 * 1000 * 1000), $result);
    }

    public function test_parse_network_bytes_tx_takes_second_part(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'parseNetworkBytes');

        // "1.5MB / 300KB" — tx = 300KB
        $result = $method->invoke($this->job, '1.5MB / 300KB', 'tx');

        $this->assertEquals(300 * 1000, $result);
    }

    public function test_parse_network_bytes_invalid_direction_returns_0(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'parseNetworkBytes');

        $result = $method->invoke($this->job, '1MB / 1MB', 'invalid');

        $this->assertEquals(0, $result);
    }

    // =========================================================================
    // convertToBytes() — all 9 supported units
    // =========================================================================

    /** @dataProvider convertToBytesProvider */
    public function test_convert_to_bytes(string $input, int $expected): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'convertToBytes');

        $this->assertEquals($expected, $method->invoke($this->job, $input));
    }

    public static function convertToBytesProvider(): array
    {
        return [
            'B' => ['100B',   100],
            'KB' => ['2KB',    2000],
            'KiB' => ['2KiB',   2048],
            'MB' => ['3MB',    3000000],
            'MiB' => ['1MiB',   1048576],
            'GB' => ['1GB',    1000000000],
            'GiB' => ['1GiB',   1073741824],
            'TB' => ['1TB',    1000000000000],
            'TiB' => ['1TiB',   1099511627776],
        ];
    }

    public function test_convert_to_bytes_returns_0_for_unknown_unit(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'convertToBytes');

        $this->assertEquals(0, $method->invoke($this->job, '5XB'));
    }

    public function test_convert_to_bytes_handles_decimal_values(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'convertToBytes');

        // 1.5 KiB = 1536 bytes
        $result = $method->invoke($this->job, '1.5KiB');

        $this->assertEquals(1536, $result);
    }

    public function test_convert_to_bytes_is_case_insensitive(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'convertToBytes');

        $lower = $method->invoke($this->job, '1mib');
        $upper = $method->invoke($this->job, '1MiB');

        $this->assertEquals($upper, $lower);
    }

    // =========================================================================
    // formatBytes() — int → human readable
    // =========================================================================

    /** @dataProvider formatBytesProvider */
    public function test_format_bytes(int $input, string $expected): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'formatBytes');

        $this->assertEquals($expected, $method->invoke($this->job, $input));
    }

    public static function formatBytesProvider(): array
    {
        return [
            'zero bytes' => [0,          '0 B'],
            '512 bytes' => [512,         '512 B'],
            '1 KB' => [1024,        '1 KB'],
            '1.5 KB' => [1536,        '1.5 KB'],
            '1 MB' => [1048576,     '1 MB'],
            '1 GB' => [1073741824,  '1 GB'],
            '2.5 GB' => [2684354560,  '2.5 GB'],
        ];
    }

    // =========================================================================
    // handle() — source-level: early exit + per-DB error isolation
    // =========================================================================

    public function test_handle_returns_early_when_server_not_functional(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        $this->assertStringContainsString('isFunctional()', $source,
            'handle() must exit early if server is not functional to avoid SSH errors');
    }

    public function test_handle_continues_after_single_db_failure(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        // Each database must be wrapped in try-catch so one failure doesn't abort all
        $this->assertStringContainsString('catch (\Exception $e)', $source);
        $this->assertStringContainsString('Log::warning(', $source);
        $this->assertStringContainsString('continue', $source);
    }

    // =========================================================================
    // getDatabaseSpecificMetrics() routing — all 8 types have handlers
    // =========================================================================

    public function test_get_database_specific_metrics_routes_postgresql(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        $this->assertStringContainsString("'postgresql' => \$this->collectPostgresMetrics(", $source);
    }

    public function test_get_database_specific_metrics_routes_mysql_and_mariadb(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        // mysql and mariadb share the same collection method
        $this->assertStringContainsString("'mysql', 'mariadb' => \$this->collectMysqlMetrics(", $source);
    }

    public function test_get_database_specific_metrics_routes_redis_family(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        // Redis, KeyDB, Dragonfly share the same INFO-based collection
        $this->assertStringContainsString("'redis', 'keydb', 'dragonfly' => \$this->collectRedisMetrics(", $source);
    }

    public function test_get_database_specific_metrics_routes_mongodb(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        $this->assertStringContainsString("'mongodb' => \$this->collectMongoMetrics(", $source);
    }

    public function test_get_database_specific_metrics_routes_clickhouse(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        $this->assertStringContainsString("'clickhouse' => \$this->collectClickhouseMetrics(", $source);
    }

    public function test_get_database_specific_metrics_returns_empty_for_unknown_type(): void
    {
        $method = new \ReflectionMethod(CollectDatabaseMetricsJob::class, 'getDatabaseSpecificMetrics');
        $db = Mockery::mock(StandalonePostgresql::class)->makePartial();

        $result = $method->invoke($this->job, $db, 'unknown');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // Security: escapeshellarg in all docker exec commands
    // =========================================================================

    public function test_container_stats_command_uses_escapeshellarg(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        // Container name must be escaped before passing to docker stats
        $this->assertStringContainsString('escapeshellarg($containerName)', $source);
    }

    public function test_postgres_metrics_uses_escapeshellarg_for_user_and_db(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        // Both user and db name must be escaped in psql commands
        $occurrences = substr_count($source, 'escapeshellarg(');
        $this->assertGreaterThan(5, $occurrences,
            'Multiple escapeshellarg calls expected across all DB metric collectors');
    }

    public function test_postgres_db_size_uses_sql_escaping_for_db_name(): void
    {
        $source = file_get_contents(app_path('Jobs/CollectDatabaseMetricsJob.php'));

        // SQL injection prevention: single-quotes inside db name must be doubled
        $this->assertStringContainsString("str_replace(\"'\", \"''\",", $source);
    }
}
