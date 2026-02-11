<?php

namespace Tests\Unit\Services\Database;

use App\Services\Database\ConnectionStringParser;
use InvalidArgumentException;
use Tests\TestCase;

class ConnectionStringParserTest extends TestCase
{
    private ConnectionStringParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ConnectionStringParser;
    }

    // --- Parsing PostgreSQL ---

    public function test_parse_postgresql_connection_string(): void
    {
        $result = $this->parser->parse('postgresql://user:password@host.example.com:5432/mydb');

        $this->assertEquals('postgresql', $result['type']);
        $this->assertEquals('host.example.com', $result['host']);
        $this->assertEquals(5432, $result['port']);
        $this->assertEquals('user', $result['username']);
        $this->assertEquals('password', $result['password']);
        $this->assertEquals('mydb', $result['database']);
    }

    public function test_parse_postgres_scheme_alias(): void
    {
        $result = $this->parser->parse('postgres://admin:secret@db.railway.app:5432/railway');

        $this->assertEquals('postgresql', $result['type']);
        $this->assertEquals('db.railway.app', $result['host']);
        $this->assertEquals('admin', $result['username']);
        $this->assertEquals('secret', $result['password']);
        $this->assertEquals('railway', $result['database']);
    }

    public function test_parse_postgresql_with_default_port(): void
    {
        $result = $this->parser->parse('postgresql://user:pass@host/db');

        $this->assertEquals(5432, $result['port']);
    }

    // --- Parsing MySQL ---

    public function test_parse_mysql_connection_string(): void
    {
        $result = $this->parser->parse('mysql://root:secret123@mysql.host.com:3306/appdb');

        $this->assertEquals('mysql', $result['type']);
        $this->assertEquals('mysql.host.com', $result['host']);
        $this->assertEquals(3306, $result['port']);
        $this->assertEquals('root', $result['username']);
        $this->assertEquals('secret123', $result['password']);
        $this->assertEquals('appdb', $result['database']);
    }

    public function test_parse_mariadb_connection_string(): void
    {
        $result = $this->parser->parse('mariadb://admin:pass@maria-host:3307/mydb');

        $this->assertEquals('mariadb', $result['type']);
        $this->assertEquals('maria-host', $result['host']);
        $this->assertEquals(3307, $result['port']);
    }

    // --- Parsing MongoDB ---

    public function test_parse_mongodb_connection_string(): void
    {
        $result = $this->parser->parse('mongodb://admin:pass@mongo.host:27017/testdb');

        $this->assertEquals('mongodb', $result['type']);
        $this->assertEquals('mongo.host', $result['host']);
        $this->assertEquals(27017, $result['port']);
        $this->assertEquals('admin', $result['username']);
        $this->assertEquals('pass', $result['password']);
        $this->assertEquals('testdb', $result['database']);
    }

    public function test_parse_mongodb_srv_connection_string(): void
    {
        $result = $this->parser->parse('mongodb+srv://admin:pass@cluster0.abc123.mongodb.net/mydb');

        $this->assertEquals('mongodb', $result['type']);
        $this->assertEquals('cluster0.abc123.mongodb.net', $result['host']);
        $this->assertEquals('admin', $result['username']);
        $this->assertEquals('pass', $result['password']);
        $this->assertEquals('mydb', $result['database']);
    }

    // --- Parsing Redis ---

    public function test_parse_redis_connection_string(): void
    {
        $result = $this->parser->parse('redis://default:mypassword@redis-host:6379');

        $this->assertEquals('redis', $result['type']);
        $this->assertEquals('redis-host', $result['host']);
        $this->assertEquals(6379, $result['port']);
        $this->assertEquals('default', $result['username']);
        $this->assertEquals('mypassword', $result['password']);
    }

    public function test_parse_rediss_connection_string(): void
    {
        $result = $this->parser->parse('rediss://user:pass@redis-host:6380');

        $this->assertEquals('redis', $result['type']);
        $this->assertEquals(6380, $result['port']);
    }

    // --- Special Characters ---

    public function test_parse_url_encoded_password(): void
    {
        // Password is "p@ss:word/with#special" → URL encoded
        $result = $this->parser->parse('postgresql://user:p%40ss%3Aword%2Fwith%23special@host:5432/db');

        $this->assertEquals('p@ss:word/with#special', $result['password']);
    }

    public function test_parse_url_encoded_username(): void
    {
        $result = $this->parser->parse('mysql://admin%40company:pass@host:3306/db');

        $this->assertEquals('admin@company', $result['username']);
    }

    // --- Query String Options ---

    public function test_parse_connection_string_with_options(): void
    {
        $result = $this->parser->parse('postgresql://user:pass@host:5432/db?sslmode=require&connect_timeout=10');

        $this->assertEquals('require', $result['options']['sslmode']);
        $this->assertEquals('10', $result['options']['connect_timeout']);
    }

    // --- Invalid Inputs ---

    public function test_parse_empty_string_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->parser->parse('');
    }

    public function test_parse_invalid_format_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse('not-a-valid-connection-string');
    }

    public function test_parse_unsupported_scheme_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported scheme');

        $this->parser->parse('ftp://user:pass@host:21/dir');
    }

    public function test_parse_missing_host_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // parse_url returns false for this format, so it throws format error
        $this->parser->parse('postgresql://user:pass@/db');
    }

    // --- Compatibility ---

    public function test_postgresql_compatible_with_postgresql(): void
    {
        $this->assertTrue($this->parser->validateCompatibility('postgresql', 'postgresql'));
    }

    public function test_postgresql_not_compatible_with_mysql(): void
    {
        $this->assertFalse($this->parser->validateCompatibility('postgresql', 'mysql'));
    }

    public function test_mysql_compatible_with_mariadb(): void
    {
        $this->assertTrue($this->parser->validateCompatibility('mysql', 'mariadb'));
    }

    public function test_mariadb_compatible_with_mysql(): void
    {
        $this->assertTrue($this->parser->validateCompatibility('mariadb', 'mysql'));
    }

    public function test_mongodb_only_compatible_with_mongodb(): void
    {
        $this->assertTrue($this->parser->validateCompatibility('mongodb', 'mongodb'));
        $this->assertFalse($this->parser->validateCompatibility('mongodb', 'postgresql'));
        $this->assertFalse($this->parser->validateCompatibility('mongodb', 'redis'));
    }

    public function test_redis_compatible_with_keydb_and_dragonfly(): void
    {
        $this->assertTrue($this->parser->validateCompatibility('redis', 'redis'));
        $this->assertTrue($this->parser->validateCompatibility('redis', 'keydb'));
        $this->assertTrue($this->parser->validateCompatibility('redis', 'dragonfly'));
    }

    public function test_unknown_type_not_compatible(): void
    {
        $this->assertFalse($this->parser->validateCompatibility('unknown', 'postgresql'));
    }

    // --- Dump Commands ---

    public function test_build_pg_dump_command(): void
    {
        $parsed = $this->parser->parse('postgresql://user:pass@host:5432/mydb');
        $command = $this->parser->buildDumpCommand($parsed, '/tmp/dump.sql');

        $this->assertStringContainsString('PGPASSWORD=', $command);
        $this->assertStringContainsString('pg_dump', $command);
        $this->assertStringContainsString('-h', $command);
        $this->assertStringContainsString('-p', $command);
        $this->assertStringContainsString('-U', $command);
        $this->assertStringContainsString('-Fc', $command);
        $this->assertStringContainsString('/tmp/dump.sql', $command);
    }

    public function test_build_mysqldump_command(): void
    {
        $parsed = $this->parser->parse('mysql://root:secret@host:3306/appdb');
        $command = $this->parser->buildDumpCommand($parsed, '/tmp/dump.sql');

        $this->assertStringContainsString('mysqldump', $command);
        $this->assertStringContainsString('-h', $command);
        $this->assertStringContainsString('-P', $command);
        $this->assertStringContainsString('-u', $command);
        $this->assertStringContainsString('--single-transaction', $command);
        $this->assertStringContainsString('/tmp/dump.sql', $command);
    }

    public function test_build_mongodump_command(): void
    {
        $parsed = $this->parser->parse('mongodb://admin:pass@host:27017/testdb');
        $command = $this->parser->buildDumpCommand($parsed, '/tmp/dump.archive');

        $this->assertStringContainsString('mongodump', $command);
        $this->assertStringContainsString('--host=', $command);
        $this->assertStringContainsString('--port=', $command);
        $this->assertStringContainsString('--username=', $command);
        $this->assertStringContainsString('--archive=', $command);
    }

    public function test_build_redis_dump_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Redis does not support remote dump');

        $parsed = $this->parser->parse('redis://user:pass@host:6379');
        $this->parser->buildDumpCommand($parsed, '/tmp/dump.rdb');
    }

    // --- Dump Commands use escapeshellarg ---

    public function test_dump_command_escapes_values(): void
    {
        $parsed = $this->parser->parse('postgresql://user:p%40ss%3Bword@host:5432/db');
        $command = $this->parser->buildDumpCommand($parsed, '/tmp/dump.sql');

        // escapeshellarg wraps in single quotes
        $this->assertStringContainsString("'host'", $command);
        $this->assertStringContainsString("'5432'", $command);
        $this->assertStringContainsString("'user'", $command);
    }

    // --- Docker Images ---

    public function test_get_dump_docker_image(): void
    {
        $this->assertStringContainsString('postgres', $this->parser->getDumpDockerImage('postgresql'));
        $this->assertStringContainsString('mysql', $this->parser->getDumpDockerImage('mysql'));
        $this->assertStringContainsString('mariadb', $this->parser->getDumpDockerImage('mariadb'));
        $this->assertStringContainsString('mongo', $this->parser->getDumpDockerImage('mongodb'));
    }

    public function test_get_dump_docker_image_unknown_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->parser->getDumpDockerImage('unknown');
    }

    // --- Safe String ---

    public function test_to_safe_string_masks_password(): void
    {
        $parsed = $this->parser->parse('postgresql://admin:supersecret@host:5432/db');
        $safe = $this->parser->toSafeString($parsed);

        $this->assertStringContainsString('admin:***@', $safe);
        $this->assertStringNotContainsString('supersecret', $safe);
        $this->assertStringContainsString('host:5432/db', $safe);
    }

    // --- Dump Extensions ---

    public function test_get_dump_extension(): void
    {
        $this->assertEquals('sql', $this->parser->getDumpExtension('postgresql'));
        $this->assertEquals('sql', $this->parser->getDumpExtension('mysql'));
        $this->assertEquals('sql', $this->parser->getDumpExtension('mariadb'));
        $this->assertEquals('archive', $this->parser->getDumpExtension('mongodb'));
    }

    // --- Whitespace handling ---

    public function test_parse_trims_whitespace(): void
    {
        $result = $this->parser->parse('  postgresql://user:pass@host:5432/db  ');

        $this->assertEquals('postgresql', $result['type']);
        $this->assertEquals('host', $result['host']);
    }
}
