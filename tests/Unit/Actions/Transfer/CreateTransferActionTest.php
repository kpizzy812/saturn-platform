<?php

namespace Tests\Unit\Actions\Transfer;

use App\Actions\Transfer\CreateTransferAction;
use App\Models\Environment;
use App\Models\Server;
use App\Models\StandalonePostgresql;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\TestCase;

class CreateTransferActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock user that passes authorization.
     */
    protected function createMockUser(): User
    {
        $team = Mockery::mock(\App\Models\Team::class);
        $team->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $user = Mockery::mock(User::class);
        $user->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $user->shouldReceive('getAuthIdentifier')->andReturn(1);
        $user->shouldReceive('currentTeam')->andReturn($team);

        // Allow all gate checks
        Gate::shouldReceive('forUser')->andReturnSelf();
        Gate::shouldReceive('denies')->andReturn(false);

        return $user;
    }

    /**
     * Add team() method mock to database.
     */
    protected function addTeamMock($database): void
    {
        $team = Mockery::mock(\App\Models\Team::class);
        $team->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $database->shouldReceive('team')->andReturn($team);
    }

    /**
     * Create a mock server.
     */
    protected function createMockServer(bool $isFunctional = true): Server
    {
        $server = Mockery::mock(Server::class)->shouldIgnoreMissing();
        $server->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $server->shouldReceive('isFunctional')->andReturn($isFunctional);

        return $server;
    }

    /**
     * Create a mock database.
     */
    protected function createMockDatabase(): StandalonePostgresql
    {
        $database = Mockery::mock(StandalonePostgresql::class)->shouldIgnoreMissing();
        $database->shouldReceive('getAttribute')->with('database_type')->andReturn('postgresql');
        $database->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $database->shouldReceive('getMorphClass')->andReturn(StandalonePostgresql::class);

        return $database;
    }

    /**
     * Create a mock environment.
     */
    protected function createMockEnvironment(): Environment
    {
        $environment = Mockery::mock(Environment::class)->shouldIgnoreMissing();
        $environment->shouldReceive('getAttribute')->with('id')->andReturn(1);

        return $environment;
    }

    /** @test */
    public function it_requires_user_authentication(): void
    {
        $database = $this->createMockDatabase();
        $environment = $this->createMockEnvironment();
        $server = $this->createMockServer();

        $action = new CreateTransferAction;

        // Without user should fail with auth error
        $result = $action->execute(
            sourceDatabase: $database,
            targetEnvironment: $environment,
            targetServer: $server,
            transferMode: 'clone'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('authentication', strtolower($result['error']));
    }

    /** @test */
    public function it_validates_transfer_mode(): void
    {
        $database = $this->createMockDatabase();
        $environment = $this->createMockEnvironment();
        $server = $this->createMockServer();
        $user = $this->createMockUser();

        $action = new CreateTransferAction;

        // Invalid mode should return error (not exception since auth check happens first)
        $result = $action->execute(
            sourceDatabase: $database,
            targetEnvironment: $environment,
            targetServer: $server,
            transferMode: 'invalid_mode',
            user: $user
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('invalid', strtolower($result['error']));
    }

    /** @test */
    public function it_validates_partial_transfer_requires_options(): void
    {
        $database = $this->createMockDatabase();
        $this->addTeamMock($database);
        $environment = $this->createMockEnvironment();
        $server = $this->createMockServer();
        $user = $this->createMockUser();

        $action = new CreateTransferAction;

        // Partial mode without options should fail
        $result = $action->execute(
            sourceDatabase: $database,
            targetEnvironment: $environment,
            targetServer: $server,
            transferMode: 'partial',
            transferOptions: null,
            user: $user
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('options', strtolower($result['error']));
    }

    /** @test */
    public function it_validates_data_only_requires_existing_target(): void
    {
        $database = $this->createMockDatabase();
        $this->addTeamMock($database);
        $environment = $this->createMockEnvironment();
        $server = $this->createMockServer();
        $user = $this->createMockUser();

        $action = new CreateTransferAction;

        // Data only mode without existing target should fail
        $result = $action->execute(
            sourceDatabase: $database,
            targetEnvironment: $environment,
            targetServer: $server,
            transferMode: 'data_only',
            existingTargetUuid: null,
            user: $user
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('target', strtolower($result['error']));
    }

    /** @test */
    public function it_validates_server_is_functional(): void
    {
        $database = $this->createMockDatabase();
        $this->addTeamMock($database);
        $environment = $this->createMockEnvironment();
        $server = $this->createMockServer(isFunctional: false);
        $user = $this->createMockUser();

        $action = new CreateTransferAction;

        $result = $action->execute(
            sourceDatabase: $database,
            targetEnvironment: $environment,
            targetServer: $server,
            transferMode: 'clone',
            user: $user
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('functional', strtolower($result['error']));
    }
}
