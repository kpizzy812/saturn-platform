<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Actions\Migration\ExecuteMigrationAction;
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
    public function get_updatable_attributes_method_excludes_identity_fields(): void
    {
        $action = new ExecuteMigrationAction;
        $method = new \ReflectionMethod($action, 'getUpdatableAttributes');

        $attributes = [
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
        ];

        // Create a mock Model with these attributes
        $source = new class($attributes) extends \Illuminate\Database\Eloquent\Model
        {
            private array $attrs;

            public function __construct(array $attrs)
            {
                $this->attrs = $attrs;
            }

            public function getAttributes()
            {
                return $this->attrs;
            }
        };

        $result = $method->invoke($action, $source);

        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('uuid', $result);
        $this->assertArrayNotHasKey('created_at', $result);
        $this->assertArrayNotHasKey('updated_at', $result);
        $this->assertArrayNotHasKey('environment_id', $result);
        $this->assertArrayNotHasKey('destination_id', $result);
        $this->assertArrayNotHasKey('status', $result);
    }

    #[Test]
    public function sync_environment_variables_method_exists(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationAction::class);

        $this->assertTrue($class->hasMethod('syncEnvironmentVariables'));
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
