<?php

namespace Tests\Unit\Actions;

use App\Actions\EnvDiff\CompareEnvironmentsAction;
use App\Models\Environment;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class CompareEnvironmentsActionTest extends TestCase
{
    private CompareEnvironmentsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CompareEnvironmentsAction;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_empty_when_no_resources()
    {
        $source = $this->createMockEnvironment([]);
        $target = $this->createMockEnvironment([]);

        $result = $this->action->handle($source, $target);

        $this->assertArrayHasKey('resources', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertEmpty($result['resources']);
        $this->assertEquals(0, $result['summary']['total_resources']);
    }

    /** @test */
    public function it_detects_added_keys_in_source()
    {
        $sourceApp = $this->createMockResource('my-app', [
            ['key' => 'DB_HOST', 'value' => 'localhost'],
            ['key' => 'API_KEY', 'value' => 'secret123'],
        ]);
        $targetApp = $this->createMockResource('my-app', [
            ['key' => 'DB_HOST', 'value' => 'localhost'],
        ]);

        $source = $this->createMockEnvironment(['applications' => [$sourceApp]], 'dev');
        $target = $this->createMockEnvironment(['applications' => [$targetApp]], 'prod');

        $result = $this->action->handle($source, $target);

        $this->assertCount(1, $result['resources']);
        $resource = $result['resources'][0];
        $this->assertEquals('my-app', $resource['name']);
        $this->assertTrue($resource['matched']);
        $this->assertContains('API_KEY', $resource['diff']['added']);
        $this->assertEquals(1, $result['summary']['total_added']);
    }

    /** @test */
    public function it_detects_removed_keys_in_target()
    {
        $sourceApp = $this->createMockResource('my-app', [
            ['key' => 'DB_HOST', 'value' => 'localhost'],
        ]);
        $targetApp = $this->createMockResource('my-app', [
            ['key' => 'DB_HOST', 'value' => 'localhost'],
            ['key' => 'LEGACY_KEY', 'value' => 'old_value'],
        ]);

        $source = $this->createMockEnvironment(['applications' => [$sourceApp]], 'dev');
        $target = $this->createMockEnvironment(['applications' => [$targetApp]], 'prod');

        $result = $this->action->handle($source, $target);

        $resource = $result['resources'][0];
        $this->assertContains('LEGACY_KEY', $resource['diff']['removed']);
        $this->assertEquals(1, $result['summary']['total_removed']);
    }

    /** @test */
    public function it_detects_changed_values()
    {
        $sourceApp = $this->createMockResource('my-app', [
            ['key' => 'DB_HOST', 'value' => 'dev-db.local'],
        ]);
        $targetApp = $this->createMockResource('my-app', [
            ['key' => 'DB_HOST', 'value' => 'prod-db.local'],
        ]);

        $source = $this->createMockEnvironment(['applications' => [$sourceApp]], 'dev');
        $target = $this->createMockEnvironment(['applications' => [$targetApp]], 'prod');

        $result = $this->action->handle($source, $target);

        $resource = $result['resources'][0];
        $this->assertContains('DB_HOST', $resource['diff']['changed']);
        $this->assertEquals(1, $result['summary']['total_changed']);
    }

    /** @test */
    public function it_detects_unchanged_keys()
    {
        $sourceApp = $this->createMockResource('my-app', [
            ['key' => 'APP_NAME', 'value' => 'Saturn'],
        ]);
        $targetApp = $this->createMockResource('my-app', [
            ['key' => 'APP_NAME', 'value' => 'Saturn'],
        ]);

        $source = $this->createMockEnvironment(['applications' => [$sourceApp]], 'dev');
        $target = $this->createMockEnvironment(['applications' => [$targetApp]], 'prod');

        $result = $this->action->handle($source, $target);

        $resource = $result['resources'][0];
        $this->assertContains('APP_NAME', $resource['diff']['unchanged']);
        $this->assertEquals(1, $result['summary']['total_unchanged']);
    }

    /** @test */
    public function it_handles_unmatched_resources()
    {
        $sourceApp = $this->createMockResource('api-app', [
            ['key' => 'PORT', 'value' => '3000'],
        ]);

        $source = $this->createMockEnvironment(['applications' => [$sourceApp]], 'dev');
        $target = $this->createMockEnvironment([], 'prod');

        $result = $this->action->handle($source, $target);

        $this->assertCount(1, $result['resources']);
        $resource = $result['resources'][0];
        $this->assertFalse($resource['matched']);
        $this->assertEquals('source', $resource['only_in']);
        $this->assertEquals(1, $result['summary']['unmatched_resources']);
    }

    /** @test */
    public function it_handles_resources_only_in_target()
    {
        $targetApp = $this->createMockResource('legacy-app', [
            ['key' => 'DB_URL', 'value' => 'postgres://...'],
        ]);

        $source = $this->createMockEnvironment([], 'dev');
        $target = $this->createMockEnvironment(['applications' => [$targetApp]], 'prod');

        $result = $this->action->handle($source, $target);

        $this->assertCount(1, $result['resources']);
        $resource = $result['resources'][0];
        $this->assertFalse($resource['matched']);
        $this->assertEquals('target', $resource['only_in']);
    }

    /** @test */
    public function it_filters_by_resource_type()
    {
        $sourceApp = $this->createMockResource('my-app', [
            ['key' => 'APP_KEY', 'value' => 'key1'],
        ]);

        $source = $this->createMockEnvironment(['applications' => [$sourceApp]], 'dev');
        $target = $this->createMockEnvironment([], 'prod');

        // Filter by 'service' should exclude applications
        $result = $this->action->handle($source, $target, 'service');
        $this->assertEmpty($result['resources']);

        // Filter by 'application' should include
        $result = $this->action->handle($source, $target, 'application');
        $this->assertCount(1, $result['resources']);
    }

    /** @test */
    public function it_normalizes_database_types()
    {
        $sourceDb = $this->createMockResource('my-db', [
            ['key' => 'PG_USER', 'value' => 'admin'],
        ]);

        $source = $this->createMockEnvironment(['postgresqls' => [$sourceDb]], 'dev');
        $target = $this->createMockEnvironment([], 'prod');

        $result = $this->action->handle($source, $target);

        $this->assertEquals('database', $result['resources'][0]['type']);
    }

    /**
     * Create a mock environment with specified resources.
     */
    private function createMockEnvironment(array $resources = [], string $name = 'test-env'): Environment
    {
        $env = Mockery::mock(Environment::class)->makePartial();
        $env->name = $name;

        $allRelations = [
            'applications', 'services', 'postgresqls', 'mysqls',
            'mariadbs', 'mongodbs', 'redis', 'keydbs', 'dragonflies', 'clickhouses',
        ];

        foreach ($allRelations as $relation) {
            $items = $resources[$relation] ?? [];
            $collection = new Collection($items);

            $query = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
            $query->shouldReceive('with')->andReturnSelf();
            $query->shouldReceive('get')->andReturn($collection);

            $env->shouldReceive($relation)->andReturn($query);
        }

        return $env;
    }

    /**
     * Create a mock resource with env vars.
     */
    private function createMockResource(string $name, array $envVars = []): object
    {
        $envVarCollection = new Collection(
            array_map(fn ($v) => (object) $v, $envVars)
        );

        $resource = Mockery::mock(\Illuminate\Database\Eloquent\Model::class)->makePartial();
        $resource->shouldReceive('getAttribute')->andReturnUsing(function ($key) use ($name, $envVarCollection) {
            return match ($key) {
                'name' => $name,
                'environment_variables' => $envVarCollection,
                default => null,
            };
        });

        return $resource;
    }
}
