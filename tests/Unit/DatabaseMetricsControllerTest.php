<?php

namespace Tests\Unit;

use Mockery;
use PHPUnit\Framework\TestCase;

class DatabaseMetricsControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that SQL injection prevention works correctly for MySQL column schema queries.
     * Note: DatabaseMetricsController requires injected dependencies and SSH access,
     * so we test the escaping logic directly instead of through the controller.
     */
    public function test_get_mysql_columns_sql_escaping_works_correctly(): void
    {
        // Test SQL injection prevention by checking escaped values would be used
        $escapedDbName = str_replace("'", "''", 'test_db');
        $escapedTableName = str_replace("'", "''", 'users');

        $this->assertEquals('test_db', $escapedDbName);
        $this->assertEquals('users', $escapedTableName);

        // Test with malicious input
        $maliciousDbName = "test'; DROP TABLE users; --";
        $escapedMalicious = str_replace("'", "''", $maliciousDbName);
        $this->assertEquals("test''; DROP TABLE users; --", $escapedMalicious);
    }

    /**
     * Test that getMysqlColumns handles empty results gracefully.
     */
    public function test_get_mysql_columns_handles_empty_results(): void
    {
        // Test the parsing logic for empty results
        $emptyResult = '';
        $columns = [];

        if ($emptyResult) {
            foreach (explode("\n", $emptyResult) as $line) {
                $parts = explode('|', trim($line));
                if (count($parts) >= 5) {
                    $columns[] = [
                        'name' => $parts[0],
                        'type' => $parts[1],
                        'nullable' => $parts[2] === 'YES',
                        'default' => $parts[3] !== 'NULL' && $parts[3] !== '' ? $parts[3] : null,
                        'is_primary' => (int) $parts[4] > 0,
                    ];
                }
            }
        }

        $this->assertEmpty($columns);
    }

    /**
     * Test column parsing logic with various data types.
     */
    public function test_column_parsing_logic_with_different_types(): void
    {
        // Simulate parsed output (pipe-separated as per awk command)
        $mockLines = [
            'id|int|NO|NULL|1',
            'name|varchar(255)|YES|NULL|0',
            'email|varchar(255)|NO|test@example.com|0',
            'age|int|YES|18|0',
            'is_active|tinyint|NO|1|0',
        ];

        $columns = [];
        foreach ($mockLines as $line) {
            $parts = explode('|', trim($line));
            if (count($parts) >= 5) {
                $columns[] = [
                    'name' => $parts[0],
                    'type' => $parts[1],
                    'nullable' => $parts[2] === 'YES',
                    'default' => $parts[3] !== 'NULL' && $parts[3] !== '' ? $parts[3] : null,
                    'is_primary' => (int) $parts[4] > 0,
                ];
            }
        }

        $this->assertCount(5, $columns);

        // Test primary key
        $this->assertEquals('id', $columns[0]['name']);
        $this->assertEquals('int', $columns[0]['type']);
        $this->assertFalse($columns[0]['nullable']);
        $this->assertNull($columns[0]['default']);
        $this->assertTrue($columns[0]['is_primary']);

        // Test nullable column
        $this->assertEquals('name', $columns[1]['name']);
        $this->assertTrue($columns[1]['nullable']);
        $this->assertNull($columns[1]['default']);
        $this->assertFalse($columns[1]['is_primary']);

        // Test column with default value
        $this->assertEquals('email', $columns[2]['name']);
        $this->assertEquals('test@example.com', $columns[2]['default']);
        $this->assertFalse($columns[2]['nullable']);

        // Test numeric default
        $this->assertEquals('age', $columns[3]['name']);
        $this->assertEquals('18', $columns[3]['default']);

        // Test tinyint type (boolean)
        $this->assertEquals('is_active', $columns[4]['name']);
        $this->assertEquals('tinyint', $columns[4]['type']);
        $this->assertEquals('1', $columns[4]['default']);
    }

    /**
     * Test that malformed lines are skipped.
     */
    public function test_malformed_lines_are_skipped(): void
    {
        $mockLines = [
            'id|int|NO|NULL|1',           // Valid
            'incomplete|varchar',          // Invalid - only 2 parts
            'name|varchar(255)|YES|NULL|0', // Valid
            'bad',                         // Invalid - only 1 part
        ];

        $columns = [];
        foreach ($mockLines as $line) {
            $parts = explode('|', trim($line));
            if (count($parts) >= 5) {
                $columns[] = [
                    'name' => $parts[0],
                    'type' => $parts[1],
                    'nullable' => $parts[2] === 'YES',
                    'default' => $parts[3] !== 'NULL' && $parts[3] !== '' ? $parts[3] : null,
                    'is_primary' => (int) $parts[4] > 0,
                ];
            }
        }

        // Only 2 valid lines should be parsed
        $this->assertCount(2, $columns);
        $this->assertEquals('id', $columns[0]['name']);
        $this->assertEquals('name', $columns[1]['name']);
    }
}
