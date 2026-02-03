<?php

namespace Tests\Unit\Services\Transfer;

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
use App\Services\Transfer\TransferStrategyFactory;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class TransferStrategyFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_postgresql_strategy(): void
    {
        $database = Mockery::mock(StandalonePostgresql::class);

        $strategy = TransferStrategyFactory::getStrategy($database);

        $this->assertInstanceOf(PostgresqlTransferStrategy::class, $strategy);
    }

    /** @test */
    public function it_creates_mysql_strategy(): void
    {
        $database = Mockery::mock(StandaloneMysql::class);

        $strategy = TransferStrategyFactory::getStrategy($database);

        $this->assertInstanceOf(MysqlTransferStrategy::class, $strategy);
    }

    /** @test */
    public function it_creates_mariadb_strategy(): void
    {
        $database = Mockery::mock(StandaloneMariadb::class);

        $strategy = TransferStrategyFactory::getStrategy($database);

        $this->assertInstanceOf(MariadbTransferStrategy::class, $strategy);
    }

    /** @test */
    public function it_creates_mongodb_strategy(): void
    {
        $database = Mockery::mock(StandaloneMongodb::class);

        $strategy = TransferStrategyFactory::getStrategy($database);

        $this->assertInstanceOf(MongodbTransferStrategy::class, $strategy);
    }

    /** @test */
    public function it_creates_redis_strategy(): void
    {
        $database = Mockery::mock(StandaloneRedis::class);

        $strategy = TransferStrategyFactory::getStrategy($database);

        $this->assertInstanceOf(RedisTransferStrategy::class, $strategy);
    }

    /** @test */
    public function it_creates_keydb_strategy(): void
    {
        $database = Mockery::mock(StandaloneKeydb::class);

        $strategy = TransferStrategyFactory::getStrategy($database);

        // KeyDB uses Redis strategy
        $this->assertInstanceOf(RedisTransferStrategy::class, $strategy);
    }

    /** @test */
    public function it_creates_dragonfly_strategy(): void
    {
        $database = Mockery::mock(StandaloneDragonfly::class);

        $strategy = TransferStrategyFactory::getStrategy($database);

        // Dragonfly uses Redis strategy
        $this->assertInstanceOf(RedisTransferStrategy::class, $strategy);
    }

    /** @test */
    public function it_creates_clickhouse_strategy(): void
    {
        $database = Mockery::mock(StandaloneClickhouse::class);

        $strategy = TransferStrategyFactory::getStrategy($database);

        $this->assertInstanceOf(ClickhouseTransferStrategy::class, $strategy);
    }

    /** @test */
    public function it_throws_exception_for_unsupported_database_type(): void
    {
        // Create a mock that is not a recognized database type
        $database = new class
        {
            public function getName(): string
            {
                return 'UnsupportedDatabase';
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported database type');

        TransferStrategyFactory::getStrategy($database);
    }

    /** @test */
    public function it_checks_if_database_supports_transfer(): void
    {
        $postgres = Mockery::mock(StandalonePostgresql::class);
        $this->assertTrue(TransferStrategyFactory::supportsTransfer($postgres));

        $unsupported = new class {};
        $this->assertFalse(TransferStrategyFactory::supportsTransfer($unsupported));
    }

    /** @test */
    public function it_returns_supported_types(): void
    {
        $types = TransferStrategyFactory::getSupportedTypes();

        $this->assertArrayHasKey(StandalonePostgresql::class, $types);
        $this->assertArrayHasKey(StandaloneMysql::class, $types);
        $this->assertArrayHasKey(StandaloneMongodb::class, $types);
        $this->assertArrayHasKey(StandaloneRedis::class, $types);
        $this->assertArrayHasKey(StandaloneClickhouse::class, $types);
    }

    /** @test */
    public function it_gets_strategy_by_type_string(): void
    {
        $strategy = TransferStrategyFactory::getStrategyByType('standalone-postgresql');
        $this->assertInstanceOf(PostgresqlTransferStrategy::class, $strategy);

        $strategy = TransferStrategyFactory::getStrategyByType('standalone-mysql');
        $this->assertInstanceOf(MysqlTransferStrategy::class, $strategy);

        $strategy = TransferStrategyFactory::getStrategyByType('unknown');
        $this->assertNull($strategy);
    }
}
