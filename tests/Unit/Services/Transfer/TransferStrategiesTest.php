<?php

namespace Tests\Unit\Services\Transfer;

use App\Services\Transfer\Strategies\ClickhouseTransferStrategy;
use App\Services\Transfer\Strategies\MariadbTransferStrategy;
use App\Services\Transfer\Strategies\MongodbTransferStrategy;
use App\Services\Transfer\Strategies\MysqlTransferStrategy;
use App\Services\Transfer\Strategies\PostgresqlTransferStrategy;
use App\Services\Transfer\Strategies\RedisTransferStrategy;
use Mockery;
use Tests\TestCase;

class TransferStrategiesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function postgresql_strategy_returns_correct_database_type(): void
    {
        $strategy = new PostgresqlTransferStrategy;

        $this->assertEquals('postgresql', $strategy->getDatabaseType());
    }

    /** @test */
    public function postgresql_strategy_returns_correct_dump_extension(): void
    {
        $strategy = new PostgresqlTransferStrategy;

        $this->assertEquals('.dump', $strategy->getDumpExtension());
    }

    /** @test */
    public function postgresql_strategy_supports_partial_transfer(): void
    {
        $strategy = new PostgresqlTransferStrategy;

        $this->assertTrue($strategy->supportsPartialTransfer());
    }

    /** @test */
    public function mysql_strategy_returns_correct_database_type(): void
    {
        $strategy = new MysqlTransferStrategy;

        $this->assertEquals('mysql', $strategy->getDatabaseType());
    }

    /** @test */
    public function mysql_strategy_returns_correct_dump_extension(): void
    {
        $strategy = new MysqlTransferStrategy;

        $this->assertEquals('.sql', $strategy->getDumpExtension());
    }

    /** @test */
    public function mysql_strategy_supports_partial_transfer(): void
    {
        $strategy = new MysqlTransferStrategy;

        $this->assertTrue($strategy->supportsPartialTransfer());
    }

    /** @test */
    public function mariadb_strategy_returns_correct_database_type(): void
    {
        $strategy = new MariadbTransferStrategy;

        $this->assertEquals('mariadb', $strategy->getDatabaseType());
    }

    /** @test */
    public function mariadb_strategy_returns_correct_dump_extension(): void
    {
        $strategy = new MariadbTransferStrategy;

        $this->assertEquals('.sql', $strategy->getDumpExtension());
    }

    /** @test */
    public function mariadb_strategy_supports_partial_transfer(): void
    {
        $strategy = new MariadbTransferStrategy;

        $this->assertTrue($strategy->supportsPartialTransfer());
    }

    /** @test */
    public function mongodb_strategy_returns_correct_database_type(): void
    {
        $strategy = new MongodbTransferStrategy;

        $this->assertEquals('mongodb', $strategy->getDatabaseType());
    }

    /** @test */
    public function mongodb_strategy_returns_correct_dump_extension(): void
    {
        $strategy = new MongodbTransferStrategy;

        $this->assertEquals('.archive.gz', $strategy->getDumpExtension());
    }

    /** @test */
    public function mongodb_strategy_supports_partial_transfer(): void
    {
        $strategy = new MongodbTransferStrategy;

        $this->assertTrue($strategy->supportsPartialTransfer());
    }

    /** @test */
    public function redis_strategy_returns_correct_database_type(): void
    {
        $strategy = new RedisTransferStrategy;

        $this->assertEquals('redis', $strategy->getDatabaseType());
    }

    /** @test */
    public function redis_strategy_returns_correct_dump_extension(): void
    {
        $strategy = new RedisTransferStrategy;

        $this->assertEquals('.rdb', $strategy->getDumpExtension());
    }

    /** @test */
    public function redis_strategy_supports_partial_transfer(): void
    {
        $strategy = new RedisTransferStrategy;

        $this->assertTrue($strategy->supportsPartialTransfer());
    }

    /** @test */
    public function clickhouse_strategy_returns_correct_database_type(): void
    {
        $strategy = new ClickhouseTransferStrategy;

        $this->assertEquals('clickhouse', $strategy->getDatabaseType());
    }

    /** @test */
    public function clickhouse_strategy_returns_correct_dump_extension(): void
    {
        $strategy = new ClickhouseTransferStrategy;

        $this->assertEquals('.sql.gz', $strategy->getDumpExtension());
    }

    /** @test */
    public function clickhouse_strategy_supports_partial_transfer(): void
    {
        $strategy = new ClickhouseTransferStrategy;

        $this->assertTrue($strategy->supportsPartialTransfer());
    }
}
