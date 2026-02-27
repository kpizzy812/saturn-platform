<?php

namespace Tests\Unit\Traits\Deployment;

use App\Traits\Deployment\HandlesCanaryDeployment;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HandlesCanaryDeploymentTest extends TestCase
{
    #[Test]
    public function trait_exists(): void
    {
        $this->assertTrue(trait_exists(HandlesCanaryDeployment::class));
    }

    #[Test]
    public function trait_has_required_methods(): void
    {
        $reflection = new \ReflectionClass(HandlesCanaryDeployment::class);
        $traitMethods = array_map(
            fn (\ReflectionMethod $m) => $m->getName(),
            $reflection->getMethods()
        );

        $this->assertContains('initiate_canary', $traitMethods);
        $this->assertContains('update_canary_traffic', $traitMethods);
        $this->assertContains('promote_canary', $traitMethods);
        $this->assertContains('rollback_canary', $traitMethods);
        $this->assertContains('capture_stable_container_for_canary', $traitMethods);
    }

    #[Test]
    public function calc_stable_weight_returns_correct_complement(): void
    {
        $instance = new class
        {
            use HandlesCanaryDeployment;

            private $application;

            private $application_deployment_queue;

            private $server;

            private $container_name;

            private ?string $stableContainerName = null;

            public function test_calc_stable(int $canary): int
            {
                return $this->calcStableWeight($canary);
            }
        };

        $this->assertEquals(90, $instance->test_calc_stable(10));
        $this->assertEquals(75, $instance->test_calc_stable(25));
        $this->assertEquals(50, $instance->test_calc_stable(50));
        $this->assertEquals(0, $instance->test_calc_stable(100));
        // Never goes negative
        $this->assertEquals(0, $instance->test_calc_stable(110));
    }

    #[Test]
    public function generate_canary_traefik_yaml_contains_required_sections(): void
    {
        $appUuid = 'test-app-uuid';

        $instance = new class($appUuid)
        {
            use HandlesCanaryDeployment;

            private $application_deployment_queue;

            private $server;

            private $container_name;

            private ?string $stableContainerName = null;

            public function __construct(private string $appUuid) {}

            protected $application;

            public function boot(): void
            {
                $app = new \stdClass;
                $app->uuid = $this->appUuid;
                $app->fqdn = 'app.example.com';
                $app->ports_exposes = '3000';
                $this->application = $app;
            }

            public function test_generate_yaml(int $canary, int $stable, string $canaryContainer, string $stableContainer): string
            {
                $this->boot();

                return $this->generate_canary_traefik_yaml($canary, $stable, $canaryContainer, $stableContainer);
            }
        };

        $yaml = $instance->test_generate_yaml(25, 75, 'myapp-canary', 'myapp-stable');

        $this->assertStringContainsString('weighted', $yaml);
        $this->assertStringContainsString('myapp-canary', $yaml);
        $this->assertStringContainsString('myapp-stable', $yaml);
        $this->assertStringContainsString('25', $yaml);
        $this->assertStringContainsString('75', $yaml);
        $this->assertStringContainsString('app.example.com', $yaml);
        $this->assertStringContainsString('3000', $yaml);
        $this->assertStringContainsString('loadBalancer', $yaml);
    }

    #[Test]
    public function get_canary_fqdn_strips_protocol_prefix(): void
    {
        $instance = new class
        {
            use HandlesCanaryDeployment;

            private $application_deployment_queue;

            private $server;

            private $container_name;

            private ?string $stableContainerName = null;

            protected $application;

            public function test_get_fqdn(string $fqdn): string
            {
                $app = new \stdClass;
                $app->fqdn = $fqdn;
                $app->uuid = 'test-uuid';
                $this->application = $app;

                return $this->get_canary_fqdn();
            }
        };

        $this->assertEquals('app.example.com', $instance->test_get_fqdn('https://app.example.com'));
        $this->assertEquals('app.example.com', $instance->test_get_fqdn('http://app.example.com'));
        $this->assertEquals('app.example.com', $instance->test_get_fqdn('app.example.com'));
        // Comma-separated: use first
        $this->assertEquals('app.example.com', $instance->test_get_fqdn('https://app.example.com,https://www.example.com'));
    }

    #[Test]
    public function get_canary_app_port_returns_first_exposed_port(): void
    {
        $instance = new class
        {
            use HandlesCanaryDeployment;

            private $application_deployment_queue;

            private $server;

            private $container_name;

            private ?string $stableContainerName = null;

            protected $application;

            public function test_get_port(?string $ports): int
            {
                $app = new \stdClass;
                $app->ports_exposes = $ports;
                $this->application = $app;

                return $this->get_canary_app_port();
            }
        };

        $this->assertEquals(3000, $instance->test_get_port('3000'));
        $this->assertEquals(8080, $instance->test_get_port('8080,9090'));
        $this->assertEquals(80, $instance->test_get_port(null));
        $this->assertEquals(80, $instance->test_get_port(''));
    }
}
