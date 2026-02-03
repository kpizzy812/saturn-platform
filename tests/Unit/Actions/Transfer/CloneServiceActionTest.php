<?php

namespace Tests\Unit\Actions\Transfer;

use App\Actions\Transfer\CloneServiceAction;
use App\Models\Environment;
use App\Models\Server;
use App\Models\Service;
use Mockery;
use Tests\TestCase;

class CloneServiceActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_be_instantiated(): void
    {
        $action = new CloneServiceAction;

        $this->assertInstanceOf(CloneServiceAction::class, $action);
    }

    /** @test */
    public function it_validates_server_is_functional(): void
    {
        $service = Mockery::mock(Service::class);
        $environment = Mockery::mock(Environment::class);

        $server = Mockery::mock(Server::class);
        $server->shouldReceive('isFunctional')->andReturn(false);

        $action = new CloneServiceAction;

        $result = $action->handle(
            sourceService: $service,
            targetEnvironment: $environment,
            targetServer: $server
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('functional', strtolower($result['error']));
    }

    /** @test */
    public function it_validates_server_has_destinations(): void
    {
        $service = Mockery::mock(Service::class);
        $environment = Mockery::mock(Environment::class);

        $server = Mockery::mock(Server::class);
        $server->shouldReceive('isFunctional')->andReturn(true);
        $server->shouldReceive('destinations')->andReturn(collect([]));

        $action = new CloneServiceAction;

        $result = $action->handle(
            sourceService: $service,
            targetEnvironment: $environment,
            targetServer: $server
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('destination', strtolower($result['error']));
    }

    /** @test */
    public function it_accepts_custom_name_option(): void
    {
        $action = new CloneServiceAction;

        // The action should accept newName option in the options array
        $this->assertTrue(method_exists($action, 'handle'));

        // Verify handle method signature accepts options array
        $reflection = new \ReflectionMethod($action, 'handle');
        $parameters = $reflection->getParameters();

        $this->assertCount(4, $parameters);
        $this->assertEquals('options', $parameters[3]->getName());
    }

    /** @test */
    public function it_has_correct_default_options(): void
    {
        $service = Mockery::mock(Service::class);
        $environment = Mockery::mock(Environment::class);

        $server = Mockery::mock(Server::class);
        $server->shouldReceive('isFunctional')->andReturn(false);

        $action = new CloneServiceAction;

        // Empty options should be merged with defaults
        $result = $action->handle(
            sourceService: $service,
            targetEnvironment: $environment,
            targetServer: $server,
            options: []
        );

        // Should fail on server validation but not on missing options
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('functional', strtolower($result['error']));
    }
}
