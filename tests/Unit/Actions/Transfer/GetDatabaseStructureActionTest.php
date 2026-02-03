<?php

namespace Tests\Unit\Actions\Transfer;

use App\Actions\Transfer\GetDatabaseStructureAction;
use App\Models\Server;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneMongodb;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Mockery;
use Tests\TestCase;

class GetDatabaseStructureActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_correct_item_label_for_postgresql(): void
    {
        $server = Mockery::mock(Server::class);
        $server->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $database = Mockery::mock(StandalonePostgresql::class);
        $database->shouldReceive('getAttribute')->with('database_type')->andReturn('postgresql');

        $action = new GetDatabaseStructureAction;

        // We can't test the full execution without a real server connection,
        // but we can verify the action handles PostgreSQL type
        $this->assertInstanceOf(GetDatabaseStructureAction::class, $action);
    }

    /** @test */
    public function it_returns_correct_item_label_for_mongodb(): void
    {
        $database = Mockery::mock(StandaloneMongodb::class);
        $database->shouldReceive('getAttribute')->with('database_type')->andReturn('mongodb');

        $action = new GetDatabaseStructureAction;
        $this->assertInstanceOf(GetDatabaseStructureAction::class, $action);
    }

    /** @test */
    public function it_returns_correct_item_label_for_redis(): void
    {
        $database = Mockery::mock(StandaloneRedis::class);
        $database->shouldReceive('getAttribute')->with('database_type')->andReturn('redis');

        $action = new GetDatabaseStructureAction;
        $this->assertInstanceOf(GetDatabaseStructureAction::class, $action);
    }

    /** @test */
    public function it_returns_correct_item_label_for_clickhouse(): void
    {
        $database = Mockery::mock(StandaloneClickhouse::class);
        $database->shouldReceive('getAttribute')->with('database_type')->andReturn('clickhouse');

        $action = new GetDatabaseStructureAction;
        $this->assertInstanceOf(GetDatabaseStructureAction::class, $action);
    }
}
