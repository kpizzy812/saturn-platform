<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Actions\Migration\ExecuteMigrationAction;
use App\Models\Application;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecuteMigrationActionTest extends TestCase
{
    #[Test]
    public function action_class_exists_and_is_callable(): void
    {
        $action = new ExecuteMigrationAction;

        $this->assertTrue(method_exists($action, 'handle'));
    }

    #[Test]
    public function action_uses_resource_config_fields_trait(): void
    {
        $action = new ExecuteMigrationAction;
        $traits = class_uses_recursive($action);

        $this->assertArrayHasKey(ResourceConfigFields::class, $traits);
    }

    #[Test]
    public function action_has_clone_methods(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationAction::class);

        $this->assertTrue($class->hasMethod('cloneResource'));
        $this->assertTrue($class->hasMethod('cloneApplication'));
        $this->assertTrue($class->hasMethod('cloneService'));
        $this->assertTrue($class->hasMethod('cloneDatabase'));
    }

    #[Test]
    public function action_has_update_methods(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationAction::class);

        $this->assertTrue($class->hasMethod('updateExistingResource'));
        $this->assertTrue($class->hasMethod('updateConfigOnly'));
        $this->assertTrue($class->hasMethod('updateFullResource'));
    }

    #[Test]
    public function get_updatable_attributes_uses_whitelist(): void
    {
        $action = new ExecuteMigrationAction;
        $method = new \ReflectionMethod($action, 'getUpdatableAttributes');

        // Create a mock Application-like model to hit whitelist
        $app = new class extends Application
        {
            private array $attrs = [];

            public function __construct()
            {
                // Don't call parent to avoid DB connection
                $this->attrs = [
                    'id' => 1,
                    'uuid' => 'test-uuid',
                    'name' => 'test-app',
                    'status' => 'running',
                    'created_at' => '2026-01-01',
                    'updated_at' => '2026-01-01',
                    'environment_id' => 1,
                    'destination_id' => 1,
                    'git_branch' => 'main',
                    'build_pack' => 'nixpacks',
                    'is_superadmin' => true,
                ];
            }

            protected static function boot() {}

            protected static function booting() {}

            protected static function booted() {}

            public function getAttributes()
            {
                return $this->attrs;
            }
        };

        $result = $method->invoke($action, $app);

        // Whitelist approach: only allowed config fields should be present
        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('uuid', $result);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayNotHasKey('status', $result);
        $this->assertArrayNotHasKey('created_at', $result);
        $this->assertArrayNotHasKey('updated_at', $result);
        $this->assertArrayNotHasKey('environment_id', $result);
        $this->assertArrayNotHasKey('destination_id', $result);
        $this->assertArrayNotHasKey('is_superadmin', $result);

        // Whitelisted fields should be present
        $this->assertArrayHasKey('git_branch', $result);
        $this->assertArrayHasKey('build_pack', $result);
    }

    #[Test]
    public function get_updatable_attributes_returns_empty_for_unknown_model(): void
    {
        $action = new ExecuteMigrationAction;
        $method = new \ReflectionMethod($action, 'getUpdatableAttributes');

        // Plain Model returns empty whitelist
        $model = new class extends \Illuminate\Database\Eloquent\Model
        {
            public function __construct()
            {
                // No parent call
            }

            public function getAttributes()
            {
                return ['id' => 1, 'name' => 'test', 'secret_field' => 'value'];
            }
        };

        $result = $method->invoke($action, $model);

        $this->assertEmpty($result);
    }

    #[Test]
    public function sync_environment_variables_accepts_overwrite_parameter(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationAction::class);
        $method = $class->getMethod('syncEnvironmentVariables');
        $params = $method->getParameters();

        $this->assertCount(3, $params);
        $this->assertEquals('source', $params[0]->getName());
        $this->assertEquals('target', $params[1]->getName());
        $this->assertEquals('overwriteValues', $params[2]->getName());
        $this->assertTrue($params[2]->isDefaultValueAvailable());
        $this->assertFalse($params[2]->getDefaultValue());
    }

    #[Test]
    public function action_has_volume_sync_methods(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationAction::class);

        $this->assertTrue($class->hasMethod('syncVolumeConfigurations'));
    }

    #[Test]
    public function action_has_helper_methods(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationAction::class);

        $this->assertTrue($class->hasMethod('findExistingTarget'));
        $this->assertTrue($class->hasMethod('getResourceConfig'));
    }
}
