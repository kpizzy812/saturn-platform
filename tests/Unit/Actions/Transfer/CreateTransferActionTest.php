<?php

namespace Tests\Unit\Actions\Transfer;

use App\Actions\Transfer\CreateTransferAction;
use App\Models\Environment;
use App\Models\Server;
use App\Models\StandalonePostgresql;
use Mockery;
use Tests\TestCase;

class CreateTransferActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_validates_transfer_mode(): void
    {
        $database = Mockery::mock(StandalonePostgresql::class);
        $database->shouldReceive('getAttribute')->with('database_type')->andReturn('postgresql');
        $database->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $database->shouldReceive('getMorphClass')->andReturn(StandalonePostgresql::class);

        $environment = Mockery::mock(Environment::class);
        $environment->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $server = Mockery::mock(Server::class);
        $server->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $server->shouldReceive('isFunctional')->andReturn(true);

        $action = new CreateTransferAction;

        // Invalid mode should throw exception
        $this->expectException(\InvalidArgumentException::class);

        $action->execute(
            sourceDatabase: $database,
            targetEnvironment: $environment,
            targetServer: $server,
            transferMode: 'invalid_mode'
        );
    }

    /** @test */
    public function it_validates_partial_transfer_requires_options(): void
    {
        $database = Mockery::mock(StandalonePostgresql::class);
        $database->shouldReceive('getAttribute')->with('database_type')->andReturn('postgresql');
        $database->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $database->shouldReceive('getMorphClass')->andReturn(StandalonePostgresql::class);

        $environment = Mockery::mock(Environment::class);
        $environment->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $server = Mockery::mock(Server::class);
        $server->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $server->shouldReceive('isFunctional')->andReturn(true);

        $action = new CreateTransferAction;

        // Partial mode without options should fail
        $result = $action->execute(
            sourceDatabase: $database,
            targetEnvironment: $environment,
            targetServer: $server,
            transferMode: 'partial',
            transferOptions: null
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('tables', $result['error']);
    }

    /** @test */
    public function it_validates_data_only_requires_existing_target(): void
    {
        $database = Mockery::mock(StandalonePostgresql::class);
        $database->shouldReceive('getAttribute')->with('database_type')->andReturn('postgresql');
        $database->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $database->shouldReceive('getMorphClass')->andReturn(StandalonePostgresql::class);

        $environment = Mockery::mock(Environment::class);
        $environment->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $server = Mockery::mock(Server::class);
        $server->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $server->shouldReceive('isFunctional')->andReturn(true);

        $action = new CreateTransferAction;

        // Data only mode without existing target should fail
        $result = $action->execute(
            sourceDatabase: $database,
            targetEnvironment: $environment,
            targetServer: $server,
            transferMode: 'data_only',
            existingTargetUuid: null
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('target', strtolower($result['error']));
    }

    /** @test */
    public function it_validates_server_is_functional(): void
    {
        $database = Mockery::mock(StandalonePostgresql::class);
        $database->shouldReceive('getAttribute')->with('database_type')->andReturn('postgresql');
        $database->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $database->shouldReceive('getMorphClass')->andReturn(StandalonePostgresql::class);

        $environment = Mockery::mock(Environment::class);
        $environment->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $server = Mockery::mock(Server::class);
        $server->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $server->shouldReceive('isFunctional')->andReturn(false);

        $action = new CreateTransferAction;

        $result = $action->execute(
            sourceDatabase: $database,
            targetEnvironment: $environment,
            targetServer: $server,
            transferMode: 'clone'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('functional', strtolower($result['error']));
    }
}
