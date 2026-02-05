<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Actions\Migration\MigrateResourceAction;
use App\Models\Environment;
use App\Models\EnvironmentMigration;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrateResourceActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function action_exists_and_is_callable(): void
    {
        $action = new MigrateResourceAction;

        $this->assertTrue(method_exists($action, 'handle'));
    }

    #[Test]
    public function action_uses_resource_config_fields_trait(): void
    {
        $action = new MigrateResourceAction;
        $traits = class_uses_recursive($action);

        $this->assertArrayHasKey(ResourceConfigFields::class, $traits);
    }

    #[Test]
    public function action_has_required_methods(): void
    {
        $class = new \ReflectionClass(MigrateResourceAction::class);

        $this->assertTrue($class->hasMethod('handle'));
        $this->assertTrue($class->hasMethod('getResourceEnvironment'));
        $this->assertTrue($class->hasMethod('normalizeOptions'));
        $this->assertTrue($class->hasMethod('createRollbackSnapshot'));
        $this->assertTrue($class->hasMethod('getResourceConfig'));
        $this->assertTrue($class->hasMethod('findExistingTarget'));
        $this->assertTrue($class->hasMethod('notifyApprovers'));
    }

    #[Test]
    public function normalize_options_sets_defaults(): void
    {
        $action = new MigrateResourceAction;
        $method = new \ReflectionMethod($action, 'normalizeOptions');

        // Use anonymous Model subclass instead of stdClass
        $app = new class extends Model
        {
            protected $table = 'applications';
        };

        $env = Mockery::mock(Environment::class)->makePartial();
        $env->forceFill(['type' => 'uat']);
        $env->shouldReceive('isProduction')->andReturn(false);

        $result = $method->invoke($action, [], $app, $env);

        $this->assertTrue($result[EnvironmentMigration::OPTION_COPY_ENV_VARS]);
        $this->assertTrue($result[EnvironmentMigration::OPTION_COPY_VOLUMES]);
        $this->assertFalse($result[EnvironmentMigration::OPTION_UPDATE_EXISTING]);
        $this->assertFalse($result[EnvironmentMigration::OPTION_CONFIG_ONLY]);
    }

    #[Test]
    public function normalize_options_detects_database_for_production(): void
    {
        $action = new MigrateResourceAction;
        $method = new \ReflectionMethod($action, 'normalizeOptions');

        // Verify isDatabase uses static $databaseModels array
        $refProp = new \ReflectionProperty(ResourceConfigFields::class, 'databaseModels');
        $databaseModels = $refProp->getValue();
        $this->assertContains('App\Models\StandalonePostgresql', $databaseModels);

        // For normalizeOptions, a non-database Model + production should still have defaults
        $app = new class extends Model
        {
            protected $table = 'applications';
        };

        $env = Mockery::mock(Environment::class)->makePartial();
        $env->forceFill(['type' => 'production']);
        $env->shouldReceive('isProduction')->andReturn(true);

        $result = $method->invoke($action, [], $app, $env);

        // Non-database resource in production: config_only should be false
        $this->assertFalse($result[EnvironmentMigration::OPTION_CONFIG_ONLY]);
    }

    #[Test]
    public function is_database_checks_class_name_in_array(): void
    {
        // Test the trait's static array directly
        $refProp = new \ReflectionProperty(ResourceConfigFields::class, 'databaseModels');
        $databaseModels = $refProp->getValue();

        $this->assertContains('App\Models\StandalonePostgresql', $databaseModels);
        $this->assertContains('App\Models\StandaloneRedis', $databaseModels);
        $this->assertNotContains('App\Models\Application', $databaseModels);
    }

    #[Test]
    public function get_database_relation_method_returns_correct_mapping(): void
    {
        $action = new MigrateResourceAction;
        $method = new \ReflectionMethod($action, 'getDatabaseRelationMethod');

        $this->assertEquals('postgresqls', $method->invoke($action, 'App\Models\StandalonePostgresql'));
        $this->assertEquals('mysqls', $method->invoke($action, 'App\Models\StandaloneMysql'));
        $this->assertEquals('redis', $method->invoke($action, 'App\Models\StandaloneRedis'));
        $this->assertEquals('dragonflies', $method->invoke($action, 'App\Models\StandaloneDragonfly'));
        $this->assertNull($method->invoke($action, 'App\Models\Application'));
    }

    #[Test]
    public function get_resource_environment_returns_null_when_no_method(): void
    {
        $action = new MigrateResourceAction;
        $method = new \ReflectionMethod($action, 'getResourceEnvironment');

        // Use anonymous Model subclass without environment() method
        $resource = new class extends Model
        {
            protected $table = 'test';
        };

        $result = $method->invoke($action, $resource);

        $this->assertNull($result);
    }

    #[Test]
    public function get_resource_config_returns_expected_structure(): void
    {
        $action = new MigrateResourceAction;
        $method = new \ReflectionMethod($action, 'getResourceConfig');

        // Use a mock with toArray method
        $app = Mockery::mock('Illuminate\Database\Eloquent\Model');
        $app->shouldReceive('getAttribute')->with('id')->andReturn(42);
        $app->shouldReceive('toArray')->andReturn([
            'id' => 42,
            'name' => 'test-app',
            'git_branch' => 'main',
        ]);
        $app->shouldReceive('getMorphClass')->andReturn('App\Models\Application');

        $result = $method->invoke($action, $app);

        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('attributes', $result);
        $this->assertEquals(42, $result['id']);
    }

    #[Test]
    public function create_rollback_snapshot_always_captures_existing_target(): void
    {
        // Verify the snapshot creation does NOT conditionally check update_existing only
        $sourceCode = file_get_contents(
            base_path('app/Actions/Migration/MigrateResourceAction.php')
        );

        // Should find existing target unconditionally (no update_existing check)
        $this->assertStringContainsString(
            '$existingTarget = $this->findExistingTarget($resource, $targetEnv);',
            $sourceCode
        );

        // Should snapshot when target exists (not just when update_existing is true)
        $this->assertStringContainsString('if ($existingTarget)', $sourceCode);

        // Should include application_settings in snapshot
        $this->assertStringContainsString('application_settings', $sourceCode);
    }

    #[Test]
    public function create_rollback_snapshot_method_exists(): void
    {
        $class = new \ReflectionClass(MigrateResourceAction::class);

        $this->assertTrue($class->hasMethod('createRollbackSnapshot'));
    }

    #[Test]
    public function find_existing_target_method_exists(): void
    {
        $class = new \ReflectionClass(MigrateResourceAction::class);

        $this->assertTrue($class->hasMethod('findExistingTarget'));
    }
}
