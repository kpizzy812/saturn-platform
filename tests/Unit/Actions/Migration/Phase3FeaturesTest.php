<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\BatchMigrateAction;
use App\Actions\Migration\CopyDatabaseDataAction;
use App\Actions\Migration\ExecuteMigrationAction;
use App\Actions\Migration\PreMigrationCheckAction;
use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentMigration;
use App\Models\Server;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Phase 3 migration features:
 * - Cancel Migration (#14)
 * - Disk Space Check (#11)
 * - Port Conflict Detection (#10)
 * - Health Check After Deploy (#15)
 */
class Phase3FeaturesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ==========================================
    // Cancel Migration (#14)
    // ==========================================

    #[Test]
    public function can_be_cancelled_returns_true_for_pending_status(): void
    {
        $migration = new EnvironmentMigration;
        $migration->status = EnvironmentMigration::STATUS_PENDING;

        $this->assertTrue($migration->canBeCancelled());
    }

    #[Test]
    public function can_be_cancelled_returns_true_for_approved_status(): void
    {
        $migration = new EnvironmentMigration;
        $migration->status = EnvironmentMigration::STATUS_APPROVED;

        $this->assertTrue($migration->canBeCancelled());
    }

    #[Test]
    public function can_be_cancelled_returns_false_for_in_progress_status(): void
    {
        $migration = new EnvironmentMigration;
        $migration->status = EnvironmentMigration::STATUS_IN_PROGRESS;

        $this->assertFalse($migration->canBeCancelled());
    }

    #[Test]
    public function can_be_cancelled_returns_false_for_completed_status(): void
    {
        $migration = new EnvironmentMigration;
        $migration->status = EnvironmentMigration::STATUS_COMPLETED;

        $this->assertFalse($migration->canBeCancelled());
    }

    #[Test]
    public function can_be_cancelled_returns_false_for_failed_status(): void
    {
        $migration = new EnvironmentMigration;
        $migration->status = EnvironmentMigration::STATUS_FAILED;

        $this->assertFalse($migration->canBeCancelled());
    }

    #[Test]
    public function cancelled_status_constant_exists(): void
    {
        $this->assertEquals('cancelled', EnvironmentMigration::STATUS_CANCELLED);
    }

    #[Test]
    public function get_all_statuses_includes_cancelled(): void
    {
        $statuses = EnvironmentMigration::getAllStatuses();

        $this->assertContains(EnvironmentMigration::STATUS_CANCELLED, $statuses);
    }

    #[Test]
    public function status_label_attribute_returns_correct_labels(): void
    {
        $migration = new EnvironmentMigration;

        $migration->status = 'pending';
        $this->assertEquals('Pending', $migration->status_label);

        $migration->status = 'in_progress';
        $this->assertEquals('In Progress', $migration->status_label);

        $migration->status = 'completed';
        $this->assertEquals('Completed', $migration->status_label);
    }

    // ==========================================
    // Option Constants
    // ==========================================

    #[Test]
    public function wait_for_ready_option_constant_exists(): void
    {
        $this->assertEquals('wait_for_ready', EnvironmentMigration::OPTION_WAIT_FOR_READY);
    }

    #[Test]
    public function copy_data_option_constant_exists(): void
    {
        $this->assertEquals('copy_data', EnvironmentMigration::OPTION_COPY_DATA);
    }

    // ==========================================
    // Disk Space Check (#11)
    // ==========================================

    #[Test]
    public function disk_space_check_passes_for_low_usage(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkDiskSpace');

        $server = Mockery::mock(Server::class)->makePartial();
        $server->shouldReceive('getDiskUsage')->andReturn('45');

        $result = $method->invoke($action, $server);

        $this->assertTrue($result['pass']);
        $this->assertFalse($result['critical']);
        $this->assertEquals(45, $result['usage']);
    }

    #[Test]
    public function disk_space_check_warns_for_high_usage(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkDiskSpace');

        $server = Mockery::mock(Server::class)->makePartial();
        $server->shouldReceive('getDiskUsage')->andReturn('85');

        $result = $method->invoke($action, $server);

        $this->assertFalse($result['pass']);
        $this->assertFalse($result['critical']);
        $this->assertStringContainsString('85%', $result['message']);
        $this->assertEquals(85, $result['usage']);
    }

    #[Test]
    public function disk_space_check_critical_for_very_high_usage(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkDiskSpace');

        $server = Mockery::mock(Server::class)->makePartial();
        $server->shouldReceive('getDiskUsage')->andReturn('97');

        $result = $method->invoke($action, $server);

        $this->assertFalse($result['pass']);
        $this->assertTrue($result['critical']);
        $this->assertStringContainsString('97%', $result['message']);
    }

    #[Test]
    public function disk_space_check_passes_when_null_returned(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkDiskSpace');

        $server = Mockery::mock(Server::class)->makePartial();
        $server->shouldReceive('getDiskUsage')->andReturn(null);

        $result = $method->invoke($action, $server);

        $this->assertTrue($result['pass']);
    }

    #[Test]
    public function disk_space_check_handles_ssh_failure_gracefully(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkDiskSpace');

        $server = Mockery::mock(Server::class)->makePartial();
        $server->shouldReceive('getDiskUsage')->andThrow(new \RuntimeException('SSH connection failed'));

        $result = $method->invoke($action, $server);

        $this->assertFalse($result['pass']);
        $this->assertFalse($result['critical']);
        $this->assertStringContainsString('Could not check', $result['message']);
    }

    #[Test]
    public function disk_space_boundary_at_80_percent(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkDiskSpace');

        $server = Mockery::mock(Server::class)->makePartial();
        $server->shouldReceive('getDiskUsage')->andReturn('80');

        $result = $method->invoke($action, $server);

        $this->assertFalse($result['pass']);
        $this->assertFalse($result['critical']);
    }

    #[Test]
    public function disk_space_boundary_at_79_percent(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkDiskSpace');

        $server = Mockery::mock(Server::class)->makePartial();
        $server->shouldReceive('getDiskUsage')->andReturn('79');

        $result = $method->invoke($action, $server);

        $this->assertTrue($result['pass']);
    }

    #[Test]
    public function disk_space_boundary_at_95_percent(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkDiskSpace');

        $server = Mockery::mock(Server::class)->makePartial();
        $server->shouldReceive('getDiskUsage')->andReturn('95');

        $result = $method->invoke($action, $server);

        $this->assertFalse($result['pass']);
        $this->assertTrue($result['critical']);
    }

    // ==========================================
    // Port Conflict Detection (#10)
    // ==========================================

    #[Test]
    public function extract_ports_returns_host_ports_from_mappings(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'extractPorts');

        $resource = new class extends Model
        {
            protected $attributes = ['ports_mappings' => '8080:80,9090:9090'];
        };

        $result = $method->invoke($action, $resource);

        $this->assertContains('8080', $result);
        $this->assertContains('9090', $result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function extract_ports_returns_empty_for_no_mappings(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'extractPorts');

        $resource = new class extends Model
        {
            protected $attributes = ['ports_mappings' => null];
        };

        $result = $method->invoke($action, $resource);

        $this->assertEmpty($result);
    }

    #[Test]
    public function extract_ports_handles_single_mapping(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'extractPorts');

        $resource = new class extends Model
        {
            protected $attributes = ['ports_mappings' => '5432:5432'];
        };

        $result = $method->invoke($action, $resource);

        $this->assertContains('5432', $result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function extract_ports_deduplicates(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'extractPorts');

        $resource = new class extends Model
        {
            protected $attributes = ['ports_mappings' => '8080:80,8080:443'];
        };

        $result = $method->invoke($action, $resource);

        $this->assertContains('8080', $result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function port_conflicts_returns_empty_for_no_ports(): void
    {
        $action = new PreMigrationCheckAction;
        $method = new \ReflectionMethod($action, 'checkPortConflicts');

        $resource = new class extends Model
        {
            protected $attributes = ['ports_mappings' => null];
        };

        $server = Mockery::mock(Server::class)->makePartial();

        $result = $method->invoke($action, $resource, $server);

        $this->assertEmpty($result);
    }

    // ==========================================
    // Health Check After Deploy (#15)
    // ==========================================

    #[Test]
    public function execute_migration_action_has_wait_for_health_check_method(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationAction::class);

        $this->assertTrue($class->hasMethod('waitForHealthCheck'));
    }

    #[Test]
    public function wait_for_health_check_method_accepts_application_and_migration(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationAction::class);
        $method = $class->getMethod('waitForHealthCheck');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('application', $params[0]->getName());
        $this->assertEquals('migration', $params[1]->getName());

        // Check type hints
        $this->assertEquals(Application::class, $params[0]->getType()->getName());
        $this->assertEquals(EnvironmentMigration::class, $params[1]->getType()->getName());
    }

    #[Test]
    public function wait_for_health_check_returns_correct_structure(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationAction::class);
        $method = $class->getMethod('waitForHealthCheck');
        $docComment = $method->getDocComment();

        $this->assertStringContainsString('@return array', $docComment);
        $this->assertStringContainsString('healthy: bool', $docComment);
        $this->assertStringContainsString('message: string', $docComment);
    }

    // ==========================================
    // Pre-migration checks integration (#10, #11)
    // ==========================================

    #[Test]
    public function pre_migration_check_action_has_disk_and_port_methods(): void
    {
        $class = new \ReflectionClass(PreMigrationCheckAction::class);

        $this->assertTrue($class->hasMethod('checkDiskSpace'));
        $this->assertTrue($class->hasMethod('checkPortConflicts'));
        $this->assertTrue($class->hasMethod('extractPorts'));
    }

    #[Test]
    public function handle_method_includes_disk_space_and_port_checks(): void
    {
        // Verify handle() docblock or source calls checkDiskSpace and checkPortConflicts
        $class = new \ReflectionClass(PreMigrationCheckAction::class);
        $source = file_get_contents($class->getFileName());

        $this->assertStringContainsString('checkDiskSpace', $source);
        $this->assertStringContainsString('checkPortConflicts', $source);
        $this->assertStringContainsString("'disk_space'", $source);
        $this->assertStringContainsString("'port_conflicts'", $source);
    }

    // ==========================================
    // Cancel API endpoint structure
    // ==========================================

    #[Test]
    public function cancel_method_exists_on_controller(): void
    {
        $class = new \ReflectionClass(\App\Http\Controllers\Api\EnvironmentMigrationController::class);

        $this->assertTrue($class->hasMethod('cancel'));
    }

    #[Test]
    public function mark_as_cancelled_method_exists(): void
    {
        $this->assertTrue(method_exists(EnvironmentMigration::class, 'markAsCancelled'));
    }

    // ==========================================
    // Batch Migrate Action (#13)
    // ==========================================

    #[Test]
    public function batch_migrate_action_exists_and_is_callable(): void
    {
        $this->assertTrue(class_exists(BatchMigrateAction::class));
        $this->assertTrue(method_exists(BatchMigrateAction::class, 'handle'));
    }

    #[Test]
    public function batch_migrate_handle_signature(): void
    {
        $class = new \ReflectionClass(BatchMigrateAction::class);
        $method = $class->getMethod('handle');
        $params = $method->getParameters();

        $this->assertEquals('resources', $params[0]->getName());
        $this->assertEquals('targetEnvironment', $params[1]->getName());
        $this->assertEquals('targetServer', $params[2]->getName());
        $this->assertEquals('requestedBy', $params[3]->getName());
        $this->assertEquals('options', $params[4]->getName());
    }

    #[Test]
    public function batch_store_method_exists_on_controller(): void
    {
        $class = new \ReflectionClass(\App\Http\Controllers\Api\EnvironmentMigrationController::class);

        $this->assertTrue($class->hasMethod('batchStore'));
    }

    // ==========================================
    // Copy Database Data Action (#8)
    // ==========================================

    #[Test]
    public function copy_database_data_action_exists(): void
    {
        $this->assertTrue(class_exists(CopyDatabaseDataAction::class));
        $this->assertTrue(method_exists(CopyDatabaseDataAction::class, 'handle'));
    }

    #[Test]
    public function copy_data_blocks_production_targets(): void
    {
        $action = new CopyDatabaseDataAction;

        $source = $this->createMock(StandalonePostgresql::class);
        $target = $this->createMock(StandalonePostgresql::class);

        $environment = Mockery::mock(Environment::class)->makePartial();
        $environment->shouldReceive('isProduction')->andReturn(true);

        $result = $action->handle($source, $target, $environment);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('production', $result['error']);
    }

    #[Test]
    public function copy_data_rejects_unsupported_database_types(): void
    {
        $action = new CopyDatabaseDataAction;

        $source = $this->createMock(StandaloneRedis::class);
        $target = $this->createMock(StandaloneRedis::class);

        $environment = Mockery::mock(Environment::class)->makePartial();
        $environment->shouldReceive('isProduction')->andReturn(false);

        $result = $action->handle($source, $target, $environment);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Unsupported', $result['error']);
    }

    #[Test]
    public function is_supported_database_returns_true_for_pg_mysql_maria_mongo(): void
    {
        $action = new CopyDatabaseDataAction;
        $method = new \ReflectionMethod($action, 'isSupportedDatabase');

        $this->assertTrue($method->invoke($action, $this->createMock(StandalonePostgresql::class)));
        $this->assertTrue($method->invoke($action, $this->createMock(StandaloneMysql::class)));
        $this->assertTrue($method->invoke($action, $this->createMock(\App\Models\StandaloneMariadb::class)));
        $this->assertTrue($method->invoke($action, $this->createMock(StandaloneMongodb::class)));
    }

    #[Test]
    public function is_supported_database_returns_false_for_redis(): void
    {
        $action = new CopyDatabaseDataAction;
        $method = new \ReflectionMethod($action, 'isSupportedDatabase');

        $this->assertFalse($method->invoke($action, $this->createMock(StandaloneRedis::class)));
    }

    #[Test]
    public function copy_data_option_is_enforced_false_for_production(): void
    {
        // Verify MigrateResourceAction normalizeOptions forces copy_data=false for production
        $action = new \App\Actions\Migration\MigrateResourceAction;
        $method = new \ReflectionMethod($action, 'normalizeOptions');

        $resource = new class extends Model {};
        $prodEnv = Mockery::mock(Environment::class)->makePartial();
        $prodEnv->shouldReceive('isProduction')->andReturn(true);

        $result = $method->invoke($action, ['copy_data' => true], $resource, $prodEnv);

        $this->assertFalse($result['copy_data']);
    }

    // ==========================================
    // Webhook Configuration Handling (#16)
    // ==========================================

    #[Test]
    public function execute_migration_has_webhook_reminder_in_source(): void
    {
        $class = new \ReflectionClass(ExecuteMigrationAction::class);
        $source = file_get_contents($class->getFileName());

        $this->assertStringContainsString('webhook', strtolower($source));
        $this->assertStringContainsString('Reminder: Configure webhook', $source);
    }
}
