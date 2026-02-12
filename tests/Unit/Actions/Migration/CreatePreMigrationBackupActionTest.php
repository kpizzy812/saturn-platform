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
}
