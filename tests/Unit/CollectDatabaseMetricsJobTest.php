<?php

namespace Tests\Unit;

use App\Jobs\CollectDatabaseMetricsJob;
use App\Models\Server;
use App\Models\StandalonePostgresql;
use Mockery;
use PHPUnit\Framework\TestCase;

class CollectDatabaseMetricsJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that job has correct configuration.
     */
    public function test_job_has_correct_configuration(): void
    {
        $server = Mockery::mock(Server::class);
        $job = new CollectDatabaseMetricsJob($server);

        $this->assertEquals(1, $job->tries);
        $this->assertEquals(60, $job->timeout);
    }

    /**
     * Test getDatabaseType returns correct type for PostgreSQL.
     */
    public function test_get_database_type_returns_postgresql(): void
    {
        $server = Mockery::mock(Server::class);
        $job = new CollectDatabaseMetricsJob($server);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('getDatabaseType');
        $method->setAccessible(true);

        $database = Mockery::mock(StandalonePostgresql::class);
        $result = $method->invoke($job, $database);

        $this->assertEquals('postgresql', $result);
    }

    /**
     * Test parseCpuPercent parses percentage string correctly.
     */
    public function test_parse_cpu_percent(): void
    {
        $server = Mockery::mock(Server::class);
        $job = new CollectDatabaseMetricsJob($server);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('parseCpuPercent');
        $method->setAccessible(true);

        $this->assertEquals(42.5, $method->invoke($job, '42.5%'));
        $this->assertEquals(0.0, $method->invoke($job, '0%'));
        $this->assertEquals(100.0, $method->invoke($job, '100%'));
        $this->assertEquals(0.12, $method->invoke($job, '0.12%'));
    }

    /**
     * Test convertToBytes handles various size formats.
     */
    public function test_convert_to_bytes(): void
    {
        $server = Mockery::mock(Server::class);
        $job = new CollectDatabaseMetricsJob($server);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('convertToBytes');
        $method->setAccessible(true);

        // Test basic units
        $this->assertEquals(1000, $method->invoke($job, '1KB'));
        $this->assertEquals(1024, $method->invoke($job, '1KiB'));
        $this->assertEquals(1000000, $method->invoke($job, '1MB'));
        $this->assertEquals(1048576, $method->invoke($job, '1MiB'));
        $this->assertEquals(1000000000, $method->invoke($job, '1GB'));
        $this->assertEquals(1073741824, $method->invoke($job, '1GiB'));

        // Test with decimals
        $this->assertEquals(1500000000, $method->invoke($job, '1.5GB'));

        // Test invalid input
        $this->assertEquals(0, $method->invoke($job, 'invalid'));
        $this->assertEquals(0, $method->invoke($job, ''));
    }

    /**
     * Test parseMemoryBytes extracts used memory from Docker stats format.
     */
    public function test_parse_memory_bytes(): void
    {
        $server = Mockery::mock(Server::class);
        $job = new CollectDatabaseMetricsJob($server);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('parseMemoryBytes');
        $method->setAccessible(true);

        // Docker stats format: "1.2GiB / 4GiB"
        $result = $method->invoke($job, '1.2GiB / 4GiB');
        $this->assertEqualsWithDelta(1288490189, $result, 1000); // ~1.2 GiB

        $result = $method->invoke($job, '512MiB / 2GiB');
        $this->assertEquals(536870912, $result); // 512 MiB
    }

    /**
     * Test parseMemoryLimit extracts limit from Docker stats format.
     */
    public function test_parse_memory_limit(): void
    {
        $server = Mockery::mock(Server::class);
        $job = new CollectDatabaseMetricsJob($server);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('parseMemoryLimit');
        $method->setAccessible(true);

        // Docker stats format: "1.2GiB / 4GiB"
        $result = $method->invoke($job, '1.2GiB / 4GiB');
        $this->assertEquals(4294967296, $result); // 4 GiB

        $result = $method->invoke($job, '512MiB / 2GiB');
        $this->assertEquals(2147483648, $result); // 2 GiB
    }

    /**
     * Test parseNetworkBytes extracts rx/tx bytes correctly.
     */
    public function test_parse_network_bytes(): void
    {
        $server = Mockery::mock(Server::class);
        $job = new CollectDatabaseMetricsJob($server);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('parseNetworkBytes');
        $method->setAccessible(true);

        // Docker stats format: "1.2MB / 500KB"
        $rxResult = $method->invoke($job, '1.2MB / 500KB', 'rx');
        $this->assertEquals(1200000, $rxResult); // 1.2 MB

        $txResult = $method->invoke($job, '1.2MB / 500KB', 'tx');
        $this->assertEquals(500000, $txResult); // 500 KB
    }

    /**
     * Test formatBytes converts bytes to human readable string.
     */
    public function test_format_bytes(): void
    {
        $server = Mockery::mock(Server::class);
        $job = new CollectDatabaseMetricsJob($server);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('formatBytes');
        $method->setAccessible(true);

        $this->assertEquals('0 B', $method->invoke($job, 0));
        $this->assertEquals('1 KB', $method->invoke($job, 1024));
        $this->assertEquals('1 MB', $method->invoke($job, 1048576));
        $this->assertEquals('1 GB', $method->invoke($job, 1073741824));
        $this->assertEquals('1.5 GB', $method->invoke($job, 1610612736));
    }
}
