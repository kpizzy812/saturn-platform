<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\MigrationDiffAction;
use App\Models\Application;
use App\Models\Environment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrationDiffActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock application using anonymous class extending Application.
     * Using Application so that getConfigFields() returns proper whitelist via instanceof.
     */
    protected function createMockApp(array $attributes = [], ?Collection $envVars = null, ?Collection $storages = null): Model
    {
        return new class($attributes, $envVars, $storages) extends Application
        {
            public $id;

            public $name;

            public $git_repository;

            public $git_branch;

            public $build_pack;

            private Collection $envVarsCollection;

            private Collection $storagesCollection;

            private Collection $fileStoragesCollection;

            public function __construct(array $attributes, ?Collection $envVars, ?Collection $storages)
            {
                // Don't call parent::__construct to avoid DB connection
                $this->id = $attributes['id'] ?? 1;
                $this->name = $attributes['name'] ?? 'test-app';
                $this->git_repository = $attributes['git_repository'] ?? 'https://github.com/test/repo';
                $this->git_branch = $attributes['git_branch'] ?? 'main';
                $this->build_pack = $attributes['build_pack'] ?? 'nixpacks';
                $this->envVarsCollection = $envVars ?? new Collection;
                $this->storagesCollection = $storages ?? new Collection;
                $this->fileStoragesCollection = new Collection;
            }

            // Provide methods (not just properties) so method_exists() works
            public function environment_variables()
            {
                return $this->envVarsCollection;
            }

            public function persistentStorages()
            {
                return $this->storagesCollection;
            }

            public function fileStorages()
            {
                return $this->fileStoragesCollection;
            }

            // Override getAttribute to return public properties
            public function getAttribute($key)
            {
                if (property_exists($this, $key)) {
                    return $this->$key;
                }

                return null;
            }

            // Override __get to handle relationship-style property access
            public function __get($key)
            {
                if ($key === 'environment_variables') {
                    return $this->environment_variables();
                }
                if ($key === 'persistentStorages') {
                    return $this->persistentStorages();
                }
                if ($key === 'fileStorages') {
                    return $this->fileStorages();
                }
                if (property_exists($this, $key)) {
                    return $this->$key;
                }

                return parent::__get($key);
            }

            // Prevent Eloquent boot
            protected static function boot() {}

            protected static function booting() {}

            protected static function booted() {}
        };
    }

    protected function createMockEnvironment(): Environment
    {
        $env = Mockery::mock(Environment::class)->makePartial();
        $env->forceFill(['id' => 2, 'name' => 'uat', 'type' => 'uat']);

        return $env;
    }

    #[Test]
    public function action_class_exists_and_is_callable(): void
    {
        $action = new MigrationDiffAction;

        $this->assertTrue(method_exists($action, 'handle'));
    }

    #[Test]
    public function action_has_required_methods(): void
    {
        $class = new \ReflectionClass(MigrationDiffAction::class);

        $this->assertTrue($class->hasMethod('handle'));
        $this->assertTrue($class->hasMethod('generateCloneSummary'));
        $this->assertTrue($class->hasMethod('generatePromoteDiff'));
        $this->assertTrue($class->hasMethod('generateUpdateDiff'));
        $this->assertTrue($class->hasMethod('diffAttributes'));
        $this->assertTrue($class->hasMethod('diffSafeAttributes'));
        $this->assertTrue($class->hasMethod('diffEnvVars'));
        $this->assertTrue($class->hasMethod('diffVolumes'));
    }

    #[Test]
    public function generate_clone_summary_counts_resources(): void
    {
        $action = new MigrationDiffAction;
        $method = new \ReflectionMethod($action, 'generateCloneSummary');

        $envVar1 = Mockery::mock();
        $envVar1->key = 'DB_HOST';

        $storage = Mockery::mock();
        $storage->mount_path = '/data';

        $app = $this->createMockApp(
            [],
            new Collection([$envVar1]),
            new Collection([$storage])
        );

        $result = $method->invoke($action, $app, []);

        // generateCloneSummary returns nested structure: result['summary']['action']
        $this->assertEquals('create_new', $result['summary']['action']);
        $this->assertEquals('test-app', $result['summary']['resource_name']);
        $this->assertEquals(1, $result['summary']['env_vars_count']);
        $this->assertEquals(1, $result['summary']['persistent_volumes_count']);
    }

    #[Test]
    public function diff_attributes_detects_changes(): void
    {
        $action = new MigrationDiffAction;
        $method = new \ReflectionMethod($action, 'diffAttributes');

        $source = $this->createMockApp(['git_branch' => 'release/v2']);
        $target = $this->createMockApp(['git_branch' => 'release/v1']);

        $result = $method->invoke($action, $source, $target);

        $this->assertArrayHasKey('git_branch', $result);
        $this->assertEquals('release/v1', $result['git_branch']['from']);
        $this->assertEquals('release/v2', $result['git_branch']['to']);
    }

    #[Test]
    public function diff_env_vars_detects_added_removed_changed(): void
    {
        $action = new MigrationDiffAction;
        $method = new \ReflectionMethod($action, 'diffEnvVars');

        $envVar1Source = Mockery::mock();
        $envVar1Source->key = 'DB_HOST';
        $envVar1Source->value = 'new-host';

        $envVar2Source = Mockery::mock();
        $envVar2Source->key = 'NEW_VAR';
        $envVar2Source->value = 'value';

        $source = $this->createMockApp([], new Collection([$envVar1Source, $envVar2Source]));

        $envVar1Target = Mockery::mock();
        $envVar1Target->key = 'DB_HOST';
        $envVar1Target->value = 'old-host';

        $envVar3Target = Mockery::mock();
        $envVar3Target->key = 'OLD_VAR';
        $envVar3Target->value = 'value';

        $target = $this->createMockApp([], new Collection([$envVar1Target, $envVar3Target]));

        $result = $method->invoke($action, $source, $target);

        $this->assertContains('NEW_VAR', $result['added']);
        $this->assertContains('OLD_VAR', $result['removed']);
        $this->assertContains('DB_HOST', $result['changed']);
    }

    #[Test]
    public function diff_volumes_detects_added_removed(): void
    {
        $action = new MigrationDiffAction;
        $method = new \ReflectionMethod($action, 'diffVolumes');

        $storage1Source = Mockery::mock();
        $storage1Source->mount_path = '/data';

        $storage2Source = Mockery::mock();
        $storage2Source->mount_path = '/logs';

        $source = $this->createMockApp([], null, new Collection([$storage1Source, $storage2Source]));

        $storage1Target = Mockery::mock();
        $storage1Target->mount_path = '/data';

        $storage3Target = Mockery::mock();
        $storage3Target->mount_path = '/cache';

        $target = $this->createMockApp([], null, new Collection([$storage1Target, $storage3Target]));

        $result = $method->invoke($action, $source, $target);

        $this->assertContains('/logs', $result['added']);
        $this->assertContains('/cache', $result['removed']);
    }

    #[Test]
    public function action_handles_empty_collections(): void
    {
        $action = new MigrationDiffAction;
        $method = new \ReflectionMethod($action, 'diffEnvVars');

        $source = $this->createMockApp([], new Collection);
        $target = $this->createMockApp([], new Collection);

        $result = $method->invoke($action, $source, $target);

        $this->assertEmpty($result['added']);
        $this->assertEmpty($result['removed']);
        $this->assertEmpty($result['changed']);
    }

    #[Test]
    public function mask_value_hides_sensitive_data(): void
    {
        $action = new MigrationDiffAction;
        $method = new \ReflectionMethod($action, 'maskValue');

        $result = $method->invoke($action, 'postgresql://user:secret@host:5432/db');

        $this->assertStringNotContainsString('secret', $result);
        $this->assertStringContainsString('****', $result);
    }
}
