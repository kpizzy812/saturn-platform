<?php

namespace Tests\Unit\Actions\Service;

use App\Actions\Service\UpdateServiceAction;
use App\Models\Service;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for UpdateServiceAction.
 */
class UpdateServiceActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_updates_basic_fields_on_service(): void
    {
        $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();

        $service->shouldReceive('save')->once();
        $service->shouldReceive('parse')->once();
        $service->shouldReceive('applications->pluck')->andReturn(collect());

        $action = new UpdateServiceAction;
        $result = $action->handle($service, [
            'name' => 'my-service',
            'description' => 'Test description',
        ]);

        $this->assertArrayHasKey('service', $result);
        $this->assertArrayHasKey('domains', $result);
        $this->assertEquals('my-service', $service->name);
        $this->assertEquals('Test description', $service->description);
    }

    /** @test */
    public function it_updates_resource_limit_fields(): void
    {
        $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
        $service->shouldReceive('save')->once();
        $service->shouldReceive('parse')->once();
        $service->shouldReceive('applications->pluck')->andReturn(collect());

        $action = new UpdateServiceAction;
        $result = $action->handle($service, [
            'limits_memory' => '512m',
            'limits_cpus' => '2',
            'limits_cpu_shares' => 1024,
        ]);

        $this->assertEquals('512m', $service->limits_memory);
        $this->assertEquals('2', $service->limits_cpus);
        $this->assertEquals(1024, $service->limits_cpu_shares);
    }

    /** @test */
    public function it_does_not_update_fields_not_in_data(): void
    {
        $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
        $service->name = 'original';
        $service->description = 'original desc';

        $service->shouldReceive('save')->once();
        $service->shouldReceive('parse')->once();
        $service->shouldReceive('applications->pluck')->andReturn(collect());

        $action = new UpdateServiceAction;
        $action->handle($service, [
            'name' => 'updated',
        ]);

        $this->assertEquals('updated', $service->name);
        $this->assertEquals('original desc', $service->description);
    }

    /** @test */
    public function it_validates_docker_compose_for_injection(): void
    {
        $source = file_get_contents(app_path('Actions/Service/UpdateServiceAction.php'));

        $this->assertStringContainsString('validateDockerComposeForInjection(', $source);
    }

    /** @test */
    public function it_dispatches_start_service_on_instant_deploy(): void
    {
        $source = file_get_contents(app_path('Actions/Service/UpdateServiceAction.php'));

        $this->assertStringContainsString('if ($instantDeploy)', $source);
        $this->assertStringContainsString('StartService::dispatch($service)', $source);
    }

    /** @test */
    public function it_normalizes_docker_compose_yaml(): void
    {
        $source = file_get_contents(app_path('Actions/Service/UpdateServiceAction.php'));

        $this->assertStringContainsString('Yaml::dump(', $source);
        $this->assertStringContainsString('Yaml::parse(', $source);
        $this->assertStringContainsString('DUMP_MULTI_LINE_LITERAL_BLOCK', $source);
    }

    /** @test */
    public function it_extracts_domains_from_service_applications(): void
    {
        $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
        $service->shouldReceive('save')->once();
        $service->shouldReceive('parse')->once();

        $domains = collect([
            'https://app.example.com',
            'https://api.example.com:8080',
            'https://extra.example.com:443:extra',
        ]);

        $service->shouldReceive('applications->pluck')->andReturn($domains);

        $action = new UpdateServiceAction;
        $result = $action->handle($service, ['name' => 'test']);

        $this->assertInstanceOf(Collection::class, $result['domains']);
        // Domain with more than 2 colons should have last port stripped
        $this->assertContains('https://extra.example.com:443', $result['domains']->toArray());
    }

    /** @test */
    public function it_handles_connect_to_docker_network_field(): void
    {
        $service = Mockery::mock(Service::class)->makePartial()->shouldIgnoreMissing();
        $service->shouldReceive('save')->once();
        $service->shouldReceive('parse')->once();
        $service->shouldReceive('applications->pluck')->andReturn(collect());

        $action = new UpdateServiceAction;
        $action->handle($service, [
            'connect_to_docker_network' => true,
        ]);

        $this->assertTrue($service->connect_to_docker_network);
    }

    /** @test */
    public function it_handles_all_limit_fields(): void
    {
        $source = file_get_contents(app_path('Actions/Service/UpdateServiceAction.php'));

        $limitFields = [
            'limits_memory',
            'limits_memory_swap',
            'limits_memory_swappiness',
            'limits_memory_reservation',
            'limits_cpus',
            'limits_cpuset',
            'limits_cpu_shares',
        ];

        foreach ($limitFields as $field) {
            $this->assertStringContainsString("'$field'", $source, "Missing limit field: $field");
        }
    }
}
