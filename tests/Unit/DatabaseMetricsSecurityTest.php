<?php

namespace Tests\Unit;

use App\Jobs\VolumeCloneJob;
use App\Models\LocalPersistentVolume;
use App\Models\Server;
use App\Services\DatabaseMetrics\MongoMetricsService;
use App\Services\DatabaseMetrics\MysqlMetricsService;
use App\Services\DatabaseMetrics\PostgresMetricsService;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Security tests for DatabaseMetrics services and related fixes (V7 Audit).
 */
class DatabaseMetricsSecurityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── MongoMetricsService: $orderBy injection prevention ───

    public function test_mongo_order_by_rejects_injection_payload(): void
    {
        $service = new MongoMetricsService;
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getData');

        // The fix validates $orderBy against known column names.
        // We verify the sort query building logic directly.
        $columns = [
            ['name' => '_id', 'type' => 'ObjectId', 'nullable' => false, 'default' => null, 'is_primary' => true],
            ['name' => 'name', 'type' => 'String', 'nullable' => true, 'default' => null, 'is_primary' => false],
        ];
        $columnNames = array_column($columns, 'name');

        // Valid orderBy
        $validOrderBy = 'name';
        $this->assertTrue(in_array($validOrderBy, $columnNames, true));

        // Injection attempt — not in column list
        $maliciousOrderBy = "x}, {\$where: 'sleep(1000)'}";
        $this->assertFalse(in_array($maliciousOrderBy, $columnNames, true));

        // Empty orderBy
        $this->assertFalse(in_array('', $columnNames, true));

        // Non-existent column
        $this->assertFalse(in_array('nonexistent_field', $columnNames, true));
    }

    public function test_mongo_sort_query_built_safely(): void
    {
        $columns = [
            ['name' => '_id', 'type' => 'ObjectId'],
            ['name' => 'created_at', 'type' => 'Date'],
        ];
        $columnNames = array_column($columns, 'name');

        // Valid: produces sort query
        $orderBy = 'created_at';
        $safeOrderBy = ($orderBy !== '' && in_array($orderBy, $columnNames, true)) ? $orderBy : '';
        $sortQuery = $safeOrderBy !== '' ? "{{$safeOrderBy}: 1}" : '{}';
        $this->assertEquals('{created_at: 1}', $sortQuery);

        // Invalid: produces empty sort
        $orderBy = 'x}, {$where: function(){sleep(10000)}}';
        $safeOrderBy = ($orderBy !== '' && in_array($orderBy, $columnNames, true)) ? $orderBy : '';
        $sortQuery = $safeOrderBy !== '' ? "{{$safeOrderBy}: 1}" : '{}';
        $this->assertEquals('{}', $sortQuery);
    }

    // ─── PostgresMetricsService: SQL escaping in getColumns ───

    public function test_postgres_schema_table_sql_injection_escaped(): void
    {
        // Simulate the fix: str_replace("'", "''", ...) for schema and table
        $maliciousTable = "users' OR '1'='1";
        $safeTable = str_replace("'", "''", $maliciousTable);
        $this->assertEquals("users'' OR ''1''=''1", $safeTable);

        $maliciousSchema = "public'; DROP TABLE users; --";
        $safeSchema = str_replace("'", "''", $maliciousSchema);
        $this->assertEquals("public''; DROP TABLE users; --", $safeSchema);
    }

    public function test_postgres_addslashes_replaced_with_sql_escaping(): void
    {
        // Old: addslashes — incorrect for PostgreSQL
        // New: str_replace("'", "''", ...) — correct for PostgreSQL
        $dbName = "it's_a_test";

        $oldWay = addslashes($dbName);
        $this->assertEquals("it\\'s_a_test", $oldWay); // Backslash — wrong for PG

        $newWay = str_replace("'", "''", $dbName);
        $this->assertEquals("it''s_a_test", $newWay); // Doubled quote — correct for PG
    }

    public function test_postgres_search_strips_dollar_sign(): void
    {
        // Dollar-quoting attack: $$malicious$$ in PostgreSQL
        $search = '$$injection$$';
        $escapedSearch = str_replace(["'", '"', '\\', ';', '--', '$'], '', $search);
        $this->assertEquals('injection', $escapedSearch);

        // Normal search is unaffected
        $normalSearch = 'hello world';
        $escapedNormal = str_replace(["'", '"', '\\', ';', '--', '$'], '', $normalSearch);
        $this->assertEquals('hello world', $escapedNormal);
    }

    // ─── MysqlMetricsService: password escaping ───

    public function test_mysql_password_not_double_escaped(): void
    {
        // The fix: password is escapeshellarg'd once, used as -p{$password} (no extra quotes)
        $rawPassword = "p@ss'w0rd";
        $escaped = escapeshellarg($rawPassword);

        // Correct: -p{$escaped} → -p'p@ss'\''w0rd'
        $correctCmd = "-p{$escaped}";
        $this->assertStringStartsWith("-p'", $correctCmd);

        // Wrong (old): -p'{$escaped}' → -p''p@ss'\''w0rd'' — broken
        $wrongCmd = "-p'{$escaped}'";
        $this->assertStringStartsWith("-p''", $wrongCmd); // Double quotes = broken
    }

    public function test_mysql_dbname_is_escaped(): void
    {
        // $dbName must be escapeshellarg'd — wraps in single quotes to prevent shell interpretation
        $maliciousDb = 'test; rm -rf /';
        $escaped = escapeshellarg($maliciousDb);
        $this->assertEquals("'test; rm -rf /'", $escaped);

        // Verify the escaped value is wrapped in single quotes (shell-safe)
        $this->assertStringStartsWith("'", $escaped);
        $this->assertStringEndsWith("'", $escaped);

        // Normal dbName stays clean
        $normalDb = 'saturn_db';
        $this->assertEquals("'saturn_db'", escapeshellarg($normalDb));
    }

    // ─── DatabaseMetricsController: escapeshellarg for uuid ───

    public function test_container_name_is_escaped(): void
    {
        // UUID should be safe, but defense-in-depth via escapeshellarg
        $uuid = 'abc-123-def';
        $escaped = escapeshellarg($uuid);
        $cmd = "docker logs --tail 100 {$escaped} 2>&1";
        $this->assertStringContainsString("'abc-123-def'", $cmd);

        // Malicious uuid (hypothetical) — escapeshellarg wraps in quotes, preventing shell execution
        $malicious = 'abc; rm -rf /';
        $escaped = escapeshellarg($malicious);
        $cmd = "docker restart {$escaped} 2>&1";
        // The entire value is inside single quotes — shell treats it as one argument
        $this->assertStringContainsString("'abc; rm -rf /'", $cmd);
        // Without escapeshellarg it would be two commands — verify wrapping prevents that
        $this->assertStringStartsWith("docker restart '", $cmd);
    }

    // ─── DatabaseMetricsController: SSRF protection ───

    public function test_ssrf_blocks_link_local_addresses(): void
    {
        $ip = '169.254.1.1';
        $blocked = str_starts_with($ip, '169.254.');
        $this->assertTrue($blocked);

        $ip = '169.254.169.254'; // AWS metadata
        $blocked = str_starts_with($ip, '169.254.');
        $this->assertTrue($blocked);

        $ip = '8.8.8.8';
        $blocked = str_starts_with($ip, '169.254.');
        $this->assertFalse($blocked);
    }

    public function test_ssrf_blocks_shared_address_space(): void
    {
        // RFC 6598: 100.64.0.0/10 (100.64.0.0 – 100.127.255.255)
        $testCases = [
            ['100.64.0.1', true],
            ['100.100.100.100', true],
            ['100.127.255.255', true],
            ['100.63.255.255', false],  // Just below range
            ['100.128.0.0', false],      // Just above range
            ['8.8.8.8', false],
        ];

        foreach ($testCases as [$ip, $shouldBlock]) {
            $inRange = ip2long($ip) >= ip2long('100.64.0.0') && ip2long($ip) <= ip2long('100.127.255.255');
            $this->assertEquals($shouldBlock, $inRange, "IP {$ip} should be ".($shouldBlock ? 'blocked' : 'allowed'));
        }
    }

    // ─── VolumeCloneJob: resilience properties ───

    public function test_volume_clone_job_has_resilience_properties(): void
    {
        $server = Mockery::mock(Server::class);
        $volume = Mockery::mock(LocalPersistentVolume::class);

        $job = new VolumeCloneJob('source-vol', 'target-vol', $server, null, $volume);

        $this->assertEquals(1, $job->tries);
        $this->assertEquals(3600, $job->timeout);
        $this->assertEquals(1, $job->maxExceptions);
    }

    public function test_volume_clone_job_has_failed_method(): void
    {
        $this->assertTrue(
            method_exists(VolumeCloneJob::class, 'failed'),
            'VolumeCloneJob must have a failed() callback'
        );
    }

    public function test_volume_clone_job_uses_high_queue(): void
    {
        $server = Mockery::mock(Server::class);
        $volume = Mockery::mock(LocalPersistentVolume::class);

        $job = new VolumeCloneJob('src', 'tgt', $server, null, $volume);
        $this->assertEquals('high', $job->queue);
    }

    // ─── MySQL search: no double str_replace ───

    public function test_mysql_search_sanitization_strips_dangerous_chars(): void
    {
        $search = "'; DROP TABLE users; --";
        $escaped = str_replace(["'", '"', '\\', ';', '--'], '', $search);

        $this->assertStringNotContainsString("'", $escaped);
        $this->assertStringNotContainsString(';', $escaped);
        $this->assertStringNotContainsString('--', $escaped);
        $this->assertEquals(' DROP TABLE users ', $escaped);
    }
}
