<?php

namespace Tests\Unit\Actions\Service;

use App\Actions\Service\CreateCustomServiceAction;
use App\Actions\Service\CreateOneClickServiceAction;
use App\Models\Environment;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandaloneDocker;
use Mockery;
use Tests\TestCase;

class CreateServiceActionsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_error_for_invalid_one_click_service_type(): void
    {
        $server = Mockery::mock(Server::class);
        $environment = Mockery::mock(Environment::class);
        $destination = Mockery::mock(StandaloneDocker::class);

        $action = new CreateOneClickServiceAction;
        $result = $action->handle(
            type: 'invalid-service-type-that-does-not-exist',
            server: $server,
            environment: $environment,
            destination: $destination,
            instantDeploy: false
        );

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Service type not found.', $result['error']);
        $this->assertArrayHasKey('valid_types', $result);
    }

    /** @test */
    public function it_validates_docker_compose_injection_in_custom_service(): void
    {
        $server = Mockery::mock(Server::class);
        $environment = Mockery::mock(Environment::class);
        $destination = Mockery::mock(StandaloneDocker::class);

        // Docker compose with command injection attempt
        $maliciousCompose = <<<'YAML'
version: "3.8"
services:
  web:
    image: nginx
    command: "$(rm -rf /)"
YAML;

        $action = new CreateCustomServiceAction;

        $this->expectException(\Exception::class);

        $action->handle(
            dockerComposeRaw: $maliciousCompose,
            server: $server,
            environment: $environment,
            destination: $destination
        );
    }

    /** @test */
    public function it_returns_valid_service_types_in_error_response(): void
    {
        $server = Mockery::mock(Server::class);
        $environment = Mockery::mock(Environment::class);
        $destination = Mockery::mock(StandaloneDocker::class);

        $action = new CreateOneClickServiceAction;
        $result = $action->handle(
            type: 'nonexistent',
            server: $server,
            environment: $environment,
            destination: $destination,
            instantDeploy: false
        );

        $this->assertArrayHasKey('valid_types', $result);
        // Check some known service types exist
        $validTypes = $result['valid_types'];
        $this->assertTrue(
            $validTypes->contains('wordpress-with-mysql') ||
            $validTypes->contains('ghost') ||
            $validTypes->contains('uptime-kuma')
        );
    }
}
