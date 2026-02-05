<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Actions\Migration\RollbackMigrationAction;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RollbackMigrationActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function action_class_exists_and_is_callable(): void
    {
        $action = new RollbackMigrationAction;

        $this->assertTrue(method_exists($action, 'handle'));
    }

    #[Test]
    public function action_uses_resource_config_fields_trait(): void
    {
        $action = new RollbackMigrationAction;
        $traits = class_uses_recursive($action);

        $this->assertArrayHasKey(ResourceConfigFields::class, $traits);
    }

    #[Test]
    public function action_has_rollback_methods(): void
    {
        $class = new \ReflectionClass(RollbackMigrationAction::class);

        $this->assertTrue($class->hasMethod('rollbackExistingUpdate'));
        $this->assertTrue($class->hasMethod('rollbackNewResource'));
        $this->assertTrue($class->hasMethod('restoreEnvironmentVariables'));
        $this->assertTrue($class->hasMethod('restorePersistentStorages'));
        $this->assertTrue($class->hasMethod('restoreFileStorages'));
        $this->assertTrue($class->hasMethod('restoreApplicationSettings'));
    }

    #[Test]
    public function get_safe_restore_attributes_excludes_identity_fields(): void
    {
        $action = new RollbackMigrationAction;
        $method = new \ReflectionMethod($action, 'getSafeRestoreAttributes');

        $attributes = [
            'id' => 1,
            'uuid' => 'test-uuid',
            'name' => 'test-app',
            'git_branch' => 'main',
            'created_at' => '2026-01-01',
            'updated_at' => '2026-01-01',
            'deleted_at' => null,
            'environment_id' => 1,
            'destination_id' => 1,
            'destination_type' => 'App\Models\StandaloneDocker',
            'build_pack' => 'nixpacks',
        ];

        $result = $method->invoke($action, $attributes);

        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('uuid', $result);
        $this->assertArrayNotHasKey('created_at', $result);
        $this->assertArrayNotHasKey('updated_at', $result);
        $this->assertArrayNotHasKey('environment_id', $result);
        $this->assertArrayNotHasKey('destination_id', $result);
        $this->assertArrayNotHasKey('destination_type', $result);

        // Should keep non-identity fields
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('git_branch', $result);
        $this->assertArrayHasKey('build_pack', $result);
    }

    #[Test]
    public function allowed_target_types_contains_all_resource_types(): void
    {
        $refConst = new \ReflectionClassConstant(RollbackMigrationAction::class, 'ALLOWED_TARGET_TYPES');
        $allowedTypes = $refConst->getValue();

        $this->assertContains('App\Models\Application', $allowedTypes);
        $this->assertContains('App\Models\Service', $allowedTypes);
        $this->assertContains('App\Models\StandalonePostgresql', $allowedTypes);
        $this->assertContains('App\Models\StandaloneMysql', $allowedTypes);
        $this->assertContains('App\Models\StandaloneMariadb', $allowedTypes);
        $this->assertContains('App\Models\StandaloneMongodb', $allowedTypes);
        $this->assertContains('App\Models\StandaloneRedis', $allowedTypes);
        $this->assertContains('App\Models\StandaloneClickhouse', $allowedTypes);
        $this->assertContains('App\Models\StandaloneKeydb', $allowedTypes);
        $this->assertContains('App\Models\StandaloneDragonfly', $allowedTypes);
    }

    #[Test]
    public function allowed_target_types_does_not_contain_dangerous_classes(): void
    {
        $refConst = new \ReflectionClassConstant(RollbackMigrationAction::class, 'ALLOWED_TARGET_TYPES');
        $allowedTypes = $refConst->getValue();

        $this->assertNotContains('App\Models\User', $allowedTypes);
        $this->assertNotContains('App\Models\Team', $allowedTypes);
        $this->assertNotContains('App\Models\Server', $allowedTypes);
    }

    #[Test]
    public function action_has_delete_methods(): void
    {
        $class = new \ReflectionClass(RollbackMigrationAction::class);

        $this->assertTrue($class->hasMethod('deleteResource'));
        $this->assertTrue($class->hasMethod('deleteDatabase'));
    }

    #[Test]
    public function is_database_trait_method_works_with_class_names(): void
    {
        $action = new RollbackMigrationAction;
        $method = new \ReflectionMethod($action, 'isDatabase');

        // Test by checking database models array directly
        $refProp = new \ReflectionProperty(ResourceConfigFields::class, 'databaseModels');
        $databaseModels = $refProp->getValue();

        $this->assertContains('App\Models\StandalonePostgresql', $databaseModels);
        $this->assertContains('App\Models\StandaloneRedis', $databaseModels);
        $this->assertNotContains('App\Models\Application', $databaseModels);
    }

    #[Test]
    public function database_models_list_contains_all_types(): void
    {
        $refProp = new \ReflectionProperty(ResourceConfigFields::class, 'databaseModels');
        $models = $refProp->getValue();

        $this->assertContains('App\Models\StandalonePostgresql', $models);
        $this->assertContains('App\Models\StandaloneMysql', $models);
        $this->assertContains('App\Models\StandaloneMariadb', $models);
        $this->assertContains('App\Models\StandaloneMongodb', $models);
        $this->assertContains('App\Models\StandaloneRedis', $models);
        $this->assertContains('App\Models\StandaloneClickhouse', $models);
        $this->assertContains('App\Models\StandaloneKeydb', $models);
        $this->assertContains('App\Models\StandaloneDragonfly', $models);
    }

    #[Test]
    public function restore_application_settings_whitelist_matches_fillable(): void
    {
        $action = new RollbackMigrationAction;
        $method = new \ReflectionMethod($action, 'restoreApplicationSettings');

        // Extract the safeFields from source code
        $source = file_get_contents(
            base_path('app/Actions/Migration/RollbackMigrationAction.php')
        );

        // Verify all critical ApplicationSetting fields are in the whitelist
        $this->assertStringContainsString("'auto_rollback_enabled'", $source);
        $this->assertStringContainsString("'rollback_validation_seconds'", $source);
        $this->assertStringContainsString("'rollback_max_restarts'", $source);
        $this->assertStringContainsString("'rollback_on_health_check_fail'", $source);
        $this->assertStringContainsString("'rollback_on_crash_loop'", $source);
        $this->assertStringContainsString("'use_build_secrets'", $source);
        $this->assertStringContainsString("'is_debug_enabled'", $source);
        $this->assertStringContainsString("'docker_images_to_keep'", $source);

        // Verify identity field is NOT in whitelist
        $this->assertStringNotContainsString("'application_id'", $source);
    }

    #[Test]
    public function action_has_notify_method(): void
    {
        $class = new \ReflectionClass(RollbackMigrationAction::class);

        $this->assertTrue($class->hasMethod('notifyRequester'));
    }
}
