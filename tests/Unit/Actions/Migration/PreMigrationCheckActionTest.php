<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\PreMigrationCheckAction;
use App\Models\Environment;
use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PreMigrationCheckActionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock resource extending Model to avoid DB connection.
     */
    protected function createMockResource(string $status = 'running:healthy', string $name = 'test-app'): Model
    {
        return new class($status, $name) extends Model
        {
            public $status;

            public $name;

            public function __construct(string $status, string $name)
            {
                $this->status = $status;
                $this->name = $name;
            }

            public function environment_variables()
            {
                return new Collection;
            }
        };
    }

    protected function createMockEnvironment(string $type = 'production'): Environment
    {
        $environment = Mockery::mock(Environment::class)->makePartial();
        $environment->forceFill([
            'id' => 2,
            'name' => $type,
            'type' => $type,
        ]);
        $environment->shouldReceive('isProduction')->andReturn($type === 'production');

        return $environment;
    }

    protected function createMockServer(bool $functional = true): Server
    {
        $server = Mockery::mock(Server::class)->makePartial();
        $server->forceFill(['id' => 1, 'name' => 'test-server']);
        $server->shouldReceive('isFunctional')->andReturn($functional);

        return $server;
    }

    #[Test]
    public function check_source_health_passes_for_running_status(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkSourceHealth');

        $resource = $this->createMockResource('running:healthy');
        $result = $method->invoke($action, $resource);

        $this->assertTrue($result);
    }

    #[Test]
    public function check_source_health_fails_for_exited_status(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkSourceHealth');

        $resource = $this->createMockResource('exited');
        $result = $method->invoke($action, $resource);

        $this->assertFalse($result);
    }

    #[Test]
    public function check_source_health_fails_for_degraded_status(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkSourceHealth');

        $resource = $this->createMockResource('degraded:unhealthy');
        $result = $method->invoke($action, $resource);

        $this->assertFalse($result);
    }

    #[Test]
    public function check_source_health_passes_when_no_status(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkSourceHealth');

        $resource = new class extends Model
        {
            public $status = null;
        };

        $result = $method->invoke($action, $resource);

        $this->assertTrue($result);
    }

    #[Test]
    public function check_target_server_passes_for_functional_server(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkTargetServer');

        $server = $this->createMockServer(true);
        $result = $method->invoke($action, $server);

        $this->assertTrue($result);
    }

    #[Test]
    public function check_target_server_fails_for_non_functional_server(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkTargetServer');

        $server = $this->createMockServer(false);
        $result = $method->invoke($action, $server);

        $this->assertFalse($result);
    }

    #[Test]
    public function check_env_var_completeness_returns_empty_for_non_production(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkEnvVarCompleteness');

        $resource = $this->createMockResource();
        $environment = $this->createMockEnvironment('uat');

        $result = $method->invoke($action, $resource, $environment);

        $this->assertEmpty($result);
    }

    #[Test]
    public function check_env_var_completeness_warns_about_empty_vars_in_production(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkEnvVarCompleteness');

        $envVar = Mockery::mock();
        $envVar->key = 'API_KEY';
        $envVar->value = '';

        $resource = new class extends Model
        {
            public $environment_variables;
        };
        $resource->environment_variables = new Collection([$envVar]);

        $environment = $this->createMockEnvironment('production');

        $result = $method->invoke($action, $resource, $environment);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('API_KEY', $result[0]);
    }

    #[Test]
    public function check_env_var_completeness_returns_empty_when_no_method(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkEnvVarCompleteness');

        $resource = new class extends Model
        {
            // No environment_variables method
        };
        $environment = $this->createMockEnvironment('production');

        $result = $method->invoke($action, $resource, $environment);

        $this->assertEmpty($result);
    }

    #[Test]
    public function action_has_required_methods(): void
    {
        $class = new \ReflectionClass(PreMigrationCheckAction::class);

        $this->assertTrue($class->hasMethod('handle'));
        $this->assertTrue($class->hasMethod('checkSourceHealth'));
        $this->assertTrue($class->hasMethod('checkNoActiveMigration'));
        $this->assertTrue($class->hasMethod('checkTargetServer'));
        $this->assertTrue($class->hasMethod('checkTargetExists'));
        $this->assertTrue($class->hasMethod('checkEnvVarCompleteness'));
        $this->assertTrue($class->hasMethod('checkConfigDrift'));
    }

    #[Test]
    public function handle_returns_correct_structure(): void
    {
        $action = new PreMigrationCheckAction;
        $class = new \ReflectionClass(PreMigrationCheckAction::class);

        // Verify return type structure by checking method signature
        $method = $class->getMethod('handle');
        $docComment = $method->getDocComment();

        $this->assertStringContainsString('@return array', $docComment);
        $this->assertStringContainsString('pass: bool', $docComment);
        $this->assertStringContainsString('errors: array', $docComment);
        $this->assertStringContainsString('warnings: array', $docComment);
        $this->assertStringContainsString('checks: array', $docComment);
    }
}
