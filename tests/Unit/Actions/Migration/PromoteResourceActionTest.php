<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\Concerns\ResourceConfigFields;
use App\Actions\Migration\PromoteResourceAction;
use App\Models\Application;
use App\Models\Service;
use App\Models\StandalonePostgresql;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromoteResourceActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function action_uses_resource_config_fields_trait(): void
    {
        $action = new PromoteResourceAction;
        $traits = class_uses_recursive($action);

        $this->assertArrayHasKey(ResourceConfigFields::class, $traits);
    }

    #[Test]
    public function get_config_fields_for_application_includes_expected_fields(): void
    {
        $action = new PromoteResourceAction;
        $method = new \ReflectionMethod($action, 'getConfigFields');

        // Create anonymous class extending Application
        $app = new class extends Application
        {
            // Empty - just need the type
        };

        $result = $method->invoke($action, $app);

        $this->assertContains('git_repository', $result);
        $this->assertContains('git_branch', $result);
        $this->assertContains('build_pack', $result);
        $this->assertContains('health_check_enabled', $result);
        $this->assertContains('limits_memory', $result);
        $this->assertContains('ports_exposes', $result);

        // Should NOT contain identity/status fields
        $this->assertNotContains('id', $result);
        $this->assertNotContains('uuid', $result);
        $this->assertNotContains('name', $result);
        $this->assertNotContains('status', $result);
    }

    #[Test]
    public function get_config_fields_for_service_includes_expected_fields(): void
    {
        $action = new PromoteResourceAction;
        $method = new \ReflectionMethod($action, 'getConfigFields');

        $service = new class extends Service
        {
            // Empty - just need the type
        };

        $result = $method->invoke($action, $service);

        $this->assertContains('docker_compose_raw', $result);
        $this->assertContains('limits_memory', $result);
        $this->assertNotContains('id', $result);
    }

    #[Test]
    public function get_config_fields_for_database_includes_expected_fields(): void
    {
        $action = new PromoteResourceAction;
        $method = new \ReflectionMethod($action, 'getConfigFields');

        // Create a mock that will pass isDatabase() check
        $db = Mockery::mock(StandalonePostgresql::class)->makePartial();

        $result = $method->invoke($action, $db);

        $this->assertContains('image', $result);
        $this->assertContains('is_public', $result);
        $this->assertContains('limits_memory', $result);
    }

    #[Test]
    public function is_connection_variable_detects_patterns(): void
    {
        $action = new PromoteResourceAction;
        $method = new \ReflectionMethod($action, 'isConnectionVariable');

        $this->assertTrue($method->invoke($action, 'DATABASE_URL'));
        $this->assertTrue($method->invoke($action, 'DB_HOST'));
        $this->assertTrue($method->invoke($action, 'REDIS_URL'));
        $this->assertTrue($method->invoke($action, 'MONGO_URL'));

        $this->assertFalse($method->invoke($action, 'APP_NAME'));
        $this->assertFalse($method->invoke($action, 'LOG_LEVEL'));
        $this->assertFalse($method->invoke($action, 'API_KEY'));
    }

    #[Test]
    public function mask_sensitive_value_hides_passwords(): void
    {
        $action = new PromoteResourceAction;
        $method = new \ReflectionMethod($action, 'maskSensitiveValue');

        $result = $method->invoke($action, 'postgresql://user:secret@host:5432/db');

        $this->assertStringNotContainsString('secret', $result);
        $this->assertStringContainsString('****', $result);
        $this->assertStringContainsString('host', $result);
    }

    #[Test]
    public function trigger_deployment_method_accepts_migration(): void
    {
        $action = new PromoteResourceAction;
        $method = new \ReflectionMethod($action, 'triggerDeployment');

        // Verify the method signature accepts Model and EnvironmentMigration
        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('resource', $params[0]->getName());
        $this->assertEquals('migration', $params[1]->getName());
    }

    #[Test]
    public function action_has_required_methods(): void
    {
        $class = new \ReflectionClass(PromoteResourceAction::class);

        $this->assertTrue($class->hasMethod('handle'));
        $this->assertTrue($class->hasMethod('findTargetResource'));
        $this->assertTrue($class->hasMethod('updateConfiguration'));
        $this->assertTrue($class->hasMethod('rewireConnections'));
        $this->assertTrue($class->hasMethod('triggerDeployment'));
        $this->assertTrue($class->hasMethod('updateApplicationSettings'));
    }

    #[Test]
    public function action_has_connection_rewiring_methods(): void
    {
        $class = new \ReflectionClass(PromoteResourceAction::class);

        $this->assertTrue($class->hasMethod('rewireConnections'));
        $this->assertTrue($class->hasMethod('tryRewireVariable'));
        $this->assertTrue($class->hasMethod('isConnectionVariable'));
        $this->assertTrue($class->hasMethod('maskSensitiveValue'));
        $this->assertTrue($class->hasMethod('findReferencedResource'));
        $this->assertTrue($class->hasMethod('generateConnectionString'));
    }
}
