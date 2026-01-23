<?php

namespace Tests\Unit;

use App\Models\Service;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceResourceLimitsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function service_has_default_resource_limits(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->limits_memory = '0';
        $service->limits_memory_swap = '0';
        $service->limits_memory_swappiness = 60;
        $service->limits_memory_reservation = '0';
        $service->limits_cpus = '0';
        $service->limits_cpuset = null;
        $service->limits_cpu_shares = 1024;

        $limits = $service->getLimits();

        $this->assertEquals('0', $limits['limits_memory']);
        $this->assertEquals('0', $limits['limits_memory_swap']);
        $this->assertEquals(60, $limits['limits_memory_swappiness']);
        $this->assertEquals('0', $limits['limits_memory_reservation']);
        $this->assertEquals('0', $limits['limits_cpus']);
        $this->assertNull($limits['limits_cpuset']);
        $this->assertEquals(1024, $limits['limits_cpu_shares']);
    }

    #[Test]
    public function service_has_no_resource_limits_with_default_values(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->limits_memory = '0';
        $service->limits_cpus = '0';
        $service->limits_memory_swap = '0';
        $service->limits_memory_reservation = '0';

        $this->assertFalse($service->hasResourceLimits());
    }

    #[Test]
    public function service_has_resource_limits_when_memory_is_set(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->limits_memory = '512m';
        $service->limits_cpus = '0';
        $service->limits_memory_swap = '0';
        $service->limits_memory_reservation = '0';

        $this->assertTrue($service->hasResourceLimits());
    }

    #[Test]
    public function service_has_resource_limits_when_cpu_is_set(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->limits_memory = '0';
        $service->limits_cpus = '0.5';
        $service->limits_memory_swap = '0';
        $service->limits_memory_reservation = '0';

        $this->assertTrue($service->hasResourceLimits());
    }

    #[Test]
    public function service_has_resource_limits_when_memory_swap_is_set(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->limits_memory = '0';
        $service->limits_cpus = '0';
        $service->limits_memory_swap = '1g';
        $service->limits_memory_reservation = '0';

        $this->assertTrue($service->hasResourceLimits());
    }

    #[Test]
    public function service_has_resource_limits_when_memory_reservation_is_set(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->limits_memory = '0';
        $service->limits_cpus = '0';
        $service->limits_memory_swap = '0';
        $service->limits_memory_reservation = '256m';

        $this->assertTrue($service->hasResourceLimits());
    }

    #[Test]
    public function get_limits_returns_all_configured_values(): void
    {
        $service = Mockery::mock(Service::class)->makePartial();
        $service->limits_memory = '1g';
        $service->limits_memory_swap = '2g';
        $service->limits_memory_swappiness = 30;
        $service->limits_memory_reservation = '512m';
        $service->limits_cpus = '2';
        $service->limits_cpuset = '0,1';
        $service->limits_cpu_shares = 2048;

        $limits = $service->getLimits();

        $this->assertEquals('1g', $limits['limits_memory']);
        $this->assertEquals('2g', $limits['limits_memory_swap']);
        $this->assertEquals(30, $limits['limits_memory_swappiness']);
        $this->assertEquals('512m', $limits['limits_memory_reservation']);
        $this->assertEquals('2', $limits['limits_cpus']);
        $this->assertEquals('0,1', $limits['limits_cpuset']);
        $this->assertEquals(2048, $limits['limits_cpu_shares']);
    }
}
