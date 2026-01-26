<?php

namespace Tests\Unit;

use App\Models\Service;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceHealthcheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function get_healthcheck_config_returns_defaults_when_no_docker_compose(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = '';

        $config = $service->getHealthcheckConfig();

        $this->assertTrue($config['enabled']);
        $this->assertEquals('http', $config['type']);
        $this->assertEquals('curl -f http://localhost/ || exit 1', $config['test']);
        $this->assertEquals(30, $config['interval']);
        $this->assertEquals(10, $config['timeout']);
        $this->assertEquals(3, $config['retries']);
        $this->assertEquals(30, $config['start_period']);
        $this->assertNull($config['service_name']);
    }

    #[Test]
    public function get_healthcheck_config_parses_http_healthcheck(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
    healthcheck:
      test: ["CMD-SHELL", "curl -f http://localhost:8080/health || exit 1"]
      interval: 15s
      timeout: 5s
      retries: 5
      start_period: 10s
YAML;

        $config = $service->getHealthcheckConfig();

        $this->assertTrue($config['enabled']);
        $this->assertEquals('http', $config['type']);
        $this->assertEquals('curl -f http://localhost:8080/health || exit 1', $config['test']);
        $this->assertEquals(15, $config['interval']);
        $this->assertEquals(5, $config['timeout']);
        $this->assertEquals(5, $config['retries']);
        $this->assertEquals(10, $config['start_period']);
        $this->assertEquals('web', $config['service_name']);
    }

    #[Test]
    public function get_healthcheck_config_parses_tcp_healthcheck(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = <<<'YAML'
services:
  db:
    image: postgres
    healthcheck:
      test: ["CMD-SHELL", "nc -z localhost 5432 || exit 1"]
      interval: 30s
      timeout: 10s
      retries: 3
YAML;

        $config = $service->getHealthcheckConfig();

        $this->assertTrue($config['enabled']);
        $this->assertEquals('tcp', $config['type']);
        $this->assertStringContainsString('nc', $config['test']);
        $this->assertEquals('db', $config['service_name']);
    }

    #[Test]
    public function get_healthcheck_config_detects_disabled_healthcheck(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = <<<'YAML'
services:
  app:
    image: myapp
    healthcheck:
      disable: true
YAML;

        $config = $service->getHealthcheckConfig();

        $this->assertFalse($config['enabled']);
        $this->assertEquals('app', $config['service_name']);
    }

    #[Test]
    public function get_services_healthcheck_status_returns_status_for_all_services(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 30s
  db:
    image: postgres
  cache:
    image: redis
    healthcheck:
      disable: true
YAML;

        $status = $service->getServicesHealthcheckStatus();

        $this->assertCount(3, $status);
        $this->assertTrue($status['web']['has_healthcheck']);
        $this->assertFalse($status['db']['has_healthcheck']);
        $this->assertFalse($status['cache']['has_healthcheck']); // disabled = no healthcheck
    }

    #[Test]
    public function set_healthcheck_config_updates_docker_compose(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
YAML;

        // Mock the save method
        $service->shouldReceive('save')->once()->andReturn(true);

        $result = $service->setHealthcheckConfig([
            'enabled' => true,
            'test' => 'curl -f http://localhost:3000/api/health || exit 1',
            'interval' => 20,
            'timeout' => 5,
            'retries' => 3,
            'start_period' => 15,
        ]);

        $this->assertTrue($result);
        $this->assertStringContainsString('healthcheck', $service->docker_compose_raw);
        $this->assertStringContainsString('curl -f http://localhost:3000/api/health', $service->docker_compose_raw);
        $this->assertStringContainsString('20s', $service->docker_compose_raw);
    }

    #[Test]
    public function set_healthcheck_config_disables_healthcheck(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
YAML;

        // Mock the save method
        $service->shouldReceive('save')->once()->andReturn(true);

        $result = $service->setHealthcheckConfig([
            'enabled' => false,
        ]);

        $this->assertTrue($result);
        $this->assertStringContainsString('disable: true', $service->docker_compose_raw);
    }

    #[Test]
    public function set_healthcheck_config_targets_specific_service(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
  api:
    image: node
YAML;

        // Mock the save method
        $service->shouldReceive('save')->once()->andReturn(true);

        $result = $service->setHealthcheckConfig([
            'enabled' => true,
            'test' => 'curl -f http://localhost:4000/health',
            'interval' => 30,
            'timeout' => 10,
            'retries' => 3,
            'start_period' => 30,
            'service_name' => 'api',
        ]);

        $this->assertTrue($result);

        // Parse the updated compose to verify
        $parsed = \Symfony\Component\Yaml\Yaml::parse($service->docker_compose_raw);
        $this->assertArrayHasKey('healthcheck', $parsed['services']['api']);
        $this->assertArrayNotHasKey('healthcheck', $parsed['services']['web']);
    }

    #[Test]
    public function set_healthcheck_config_returns_false_when_no_docker_compose(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = '';

        $result = $service->setHealthcheckConfig([
            'enabled' => true,
            'test' => 'curl -f http://localhost/',
        ]);

        $this->assertFalse($result);
    }

    #[Test]
    public function set_healthcheck_config_returns_false_for_invalid_service_name(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
YAML;

        $result = $service->setHealthcheckConfig([
            'enabled' => true,
            'test' => 'curl -f http://localhost/',
            'service_name' => 'nonexistent',
        ]);

        $this->assertFalse($result);
    }

    #[Test]
    public function parse_docker_duration_handles_various_formats(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/"]
      interval: 1m
      timeout: 30s
      start_period: 2h
YAML;

        $config = $service->getHealthcheckConfig();

        $this->assertEquals(60, $config['interval']); // 1m = 60s
        $this->assertEquals(30, $config['timeout']); // 30s = 30s
        $this->assertEquals(7200, $config['start_period']); // 2h = 7200s
    }

    #[Test]
    public function get_healthcheck_config_parses_cmd_array_format(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->docker_compose_raw = <<<'YAML'
services:
  web:
    image: nginx
    healthcheck:
      test: ["CMD", "wget", "-q", "--spider", "http://localhost/"]
YAML;

        $config = $service->getHealthcheckConfig();

        $this->assertEquals('wget -q --spider http://localhost/', $config['test']);
        $this->assertEquals('http', $config['type']); // wget detected as http
    }
}
