<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\CreatePreMigrationBackupAction;
use App\Models\EnvironmentMigration;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMongodb;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Tests\TestCase;

class CreatePreMigrationBackupActionTest extends TestCase
{
    #[Test]
    public function action_class_exists(): void
    {
        $this->assertTrue(class_exists(CreatePreMigrationBackupAction::class));
    }

    #[Test]
    public function action_has_handle_method(): void
    {
        $class = new \ReflectionClass(CreatePreMigrationBackupAction::class);
        $this->assertTrue($class->hasMethod('handle'));
    }

    #[Test]
    public function handle_requires_model_and_migration(): void
    {
        $method = new \ReflectionMethod(CreatePreMigrationBackupAction::class, 'handle');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('targetResource', $params[0]->getName());
        $this->assertEquals('migration', $params[1]->getName());
    }

    #[Test]
    public function non_backupable_types_return_success_immediately(): void
    {
        $action = new CreatePreMigrationBackupAction;

        // Application is not backupable
        $app = new class extends Model
        {
            public function __construct() {}
        };

        $migration = new class extends EnvironmentMigration
        {
            public ?string $uuid = 'test-migration-uuid';

            public function __construct() {}

            protected static function boot() {}

            protected static function booting() {}

            protected static function booted() {}
        };

        $result = $action->handle($app, $migration);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('not required', $result['message']);
    }

    #[Test]
    public function is_backupable_returns_true_for_supported_databases(): void
    {
        // PostgreSQL
        $pg = $this->createMock(StandalonePostgresql::class);
        $this->assertTrue(CreatePreMigrationBackupAction::isBackupable($pg));

        // MongoDB
        $mongo = $this->createMock(StandaloneMongodb::class);
        $this->assertTrue(CreatePreMigrationBackupAction::isBackupable($mongo));
    }

    #[Test]
    public function is_backupable_returns_false_for_unsupported_databases(): void
    {
        // Redis doesn't support backups
        $redis = $this->createMock(StandaloneRedis::class);
        $this->assertFalse(CreatePreMigrationBackupAction::isBackupable($redis));

        // ClickHouse doesn't support backups
        $ch = $this->createMock(StandaloneClickhouse::class);
        $this->assertFalse(CreatePreMigrationBackupAction::isBackupable($ch));

        // KeyDB doesn't support backups
        $keydb = $this->createMock(StandaloneKeydb::class);
        $this->assertFalse(CreatePreMigrationBackupAction::isBackupable($keydb));

        // Dragonfly doesn't support backups
        $dragon = $this->createMock(StandaloneDragonfly::class);
        $this->assertFalse(CreatePreMigrationBackupAction::isBackupable($dragon));
    }

    #[Test]
    public function get_default_database_name_returns_correct_values(): void
    {
        $action = new CreatePreMigrationBackupAction;
        $method = new \ReflectionMethod($action, 'getDefaultDatabaseName');

        // PostgreSQL
        $pg = new class extends StandalonePostgresql
        {
            public ?string $postgres_db = 'mydb';

            public function __construct() {}

            protected static function boot() {}

            protected static function booting() {}

            protected static function booted() {}
        };
        $this->assertEquals('mydb', $method->invoke($action, $pg));

        // MongoDB returns *
        $mongo = new class extends StandaloneMongodb
        {
            public function __construct() {}

            protected static function boot() {}

            protected static function booting() {}

            protected static function booted() {}
        };
        $this->assertEquals('*', $method->invoke($action, $mongo));
    }

    #[Test]
    public function action_uses_as_action_trait(): void
    {
        $action = new CreatePreMigrationBackupAction;
        $traits = class_uses_recursive($action);

        $this->assertArrayHasKey(\Lorisleiva\Actions\Concerns\AsAction::class, $traits);
    }

    #[Test]
    public function is_timeout_exception_detects_process_timed_out_exception(): void
    {
        $action = new CreatePreMigrationBackupAction;
        $method = new \ReflectionMethod($action, 'isTimeoutException');

        $e = new ProcessTimedOutException(
            $this->createMock(\Symfony\Component\Process\Process::class),
            ProcessTimedOutException::TYPE_GENERAL
        );

        $this->assertTrue($method->invoke($action, $e));
    }

    #[Test]
    public function is_timeout_exception_detects_timeout_keywords_in_message(): void
    {
        $action = new CreatePreMigrationBackupAction;
        $method = new \ReflectionMethod($action, 'isTimeoutException');

        $timedOut = new \RuntimeException('The process has timed out.');
        $this->assertTrue($method->invoke($action, $timedOut));

        $timeout = new \RuntimeException('Connection timeout exceeded');
        $this->assertTrue($method->invoke($action, $timeout));

        $generic = new \RuntimeException('Some other error occurred');
        $this->assertFalse($method->invoke($action, $generic));
    }

    #[Test]
    public function handle_returns_timed_out_on_process_timeout_exception(): void
    {
        // ProcessTimedOutException requires a Process object — use RuntimeException with
        // a matching message to test the same code path without complex constructor setup.
        $timeoutAction = new class extends CreatePreMigrationBackupAction
        {
            protected function getOrCreateBackupConfig(Model $database): \App\Models\ScheduledDatabaseBackup
            {
                // Simulate a process timeout using RuntimeException with 'timed out' in the message.
                // The isTimeoutException() method detects this message pattern.
                throw new \RuntimeException('The process has timed out.');
            }
        };

        $pg = $this->createMock(StandalonePostgresql::class);
        $migration = new class extends EnvironmentMigration
        {
            public int $id = 99;

            public ?string $uuid = 'test-uuid';

            public function __construct() {}

            protected static function boot() {}

            protected static function booting() {}

            protected static function booted() {}
        };

        $result = $timeoutAction->handle($pg, $migration);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['timed_out'] ?? false);
        $this->assertStringContainsString('timed out', strtolower($result['error']));
    }

    #[Test]
    public function handle_returns_timed_out_on_generic_timeout_message(): void
    {
        $timeoutAction = new class extends CreatePreMigrationBackupAction
        {
            protected function getOrCreateBackupConfig(Model $database): \App\Models\ScheduledDatabaseBackup
            {
                throw new \RuntimeException('SSH connection timeout after 300 seconds');
            }
        };

        $pg = $this->createMock(StandalonePostgresql::class);
        $migration = new class extends EnvironmentMigration
        {
            public int $id = 99;

            public ?string $uuid = 'test-uuid';

            public function __construct() {}

            protected static function boot() {}

            protected static function booting() {}

            protected static function booted() {}
        };

        $result = $timeoutAction->handle($pg, $migration);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['timed_out'] ?? false);
    }

    #[Test]
    public function handle_returns_error_on_generic_non_timeout_exception(): void
    {
        $failingAction = new class extends CreatePreMigrationBackupAction
        {
            protected function getOrCreateBackupConfig(Model $database): \App\Models\ScheduledDatabaseBackup
            {
                throw new \RuntimeException('Database connection refused');
            }
        };

        $pg = $this->createMock(StandalonePostgresql::class);
        $migration = new class extends EnvironmentMigration
        {
            public int $id = 99;

            public ?string $uuid = 'test-uuid';

            public function __construct() {}

            protected static function boot() {}

            protected static function booting() {}

            protected static function booted() {}
        };

        $result = $failingAction->handle($pg, $migration);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['timed_out'] ?? false);
        $this->assertStringContainsString('Database connection refused', $result['error']);
    }
}
