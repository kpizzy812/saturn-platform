<?php

namespace Tests\Unit\Actions\Migration;

use App\Actions\Migration\RotateCredentialsAction;
use App\Models\Application;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RotateCredentialsActionTest extends TestCase
{
    #[Test]
    public function action_class_exists(): void
    {
        $this->assertTrue(class_exists(RotateCredentialsAction::class));
    }

    #[Test]
    public function action_has_handle_method(): void
    {
        $class = new \ReflectionClass(RotateCredentialsAction::class);
        $this->assertTrue($class->hasMethod('handle'));
    }

    #[Test]
    public function handle_requires_model_and_environment(): void
    {
        $method = new \ReflectionMethod(RotateCredentialsAction::class, 'handle');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertEquals('database', $params[0]->getName());
        $this->assertEquals('targetEnv', $params[1]->getName());
    }

    #[Test]
    public function supports_rotation_for_postgresql(): void
    {
        $pg = $this->createMock(StandalonePostgresql::class);
        $this->assertTrue(RotateCredentialsAction::supportsRotation($pg));
    }

    #[Test]
    public function supports_rotation_for_mysql(): void
    {
        $mysql = $this->createMock(StandaloneMysql::class);
        $this->assertTrue(RotateCredentialsAction::supportsRotation($mysql));
    }

    #[Test]
    public function supports_rotation_for_mariadb(): void
    {
        $maria = $this->createMock(StandaloneMariadb::class);
        $this->assertTrue(RotateCredentialsAction::supportsRotation($maria));
    }

    #[Test]
    public function supports_rotation_for_mongodb(): void
    {
        $mongo = $this->createMock(StandaloneMongodb::class);
        $this->assertTrue(RotateCredentialsAction::supportsRotation($mongo));
    }

    #[Test]
    public function does_not_support_rotation_for_redis(): void
    {
        $redis = $this->createMock(StandaloneRedis::class);
        $this->assertFalse(RotateCredentialsAction::supportsRotation($redis));
    }

    #[Test]
    public function does_not_support_rotation_for_keydb(): void
    {
        $keydb = $this->createMock(StandaloneKeydb::class);
        $this->assertFalse(RotateCredentialsAction::supportsRotation($keydb));
    }

    #[Test]
    public function does_not_support_rotation_for_dragonfly(): void
    {
        $dragon = $this->createMock(StandaloneDragonfly::class);
        $this->assertFalse(RotateCredentialsAction::supportsRotation($dragon));
    }

    #[Test]
    public function does_not_support_rotation_for_clickhouse(): void
    {
        $ch = $this->createMock(StandaloneClickhouse::class);
        $this->assertFalse(RotateCredentialsAction::supportsRotation($ch));
    }

    #[Test]
    public function does_not_support_rotation_for_applications(): void
    {
        $app = $this->createMock(Application::class);
        $this->assertFalse(RotateCredentialsAction::supportsRotation($app));
    }

    #[Test]
    public function credential_fields_cover_all_password_types(): void
    {
        // PostgreSQL
        $pg = $this->createMock(StandalonePostgresql::class);
        $fields = RotateCredentialsAction::getCredentialFields($pg);
        $this->assertNotNull($fields);
        $this->assertEquals('postgres_password', $fields['password']);

        // MySQL
        $mysql = $this->createMock(StandaloneMysql::class);
        $fields = RotateCredentialsAction::getCredentialFields($mysql);
        $this->assertNotNull($fields);
        $this->assertEquals('mysql_password', $fields['password']);
        $this->assertEquals('mysql_root_password', $fields['root_password']);

        // MariaDB
        $maria = $this->createMock(StandaloneMariadb::class);
        $fields = RotateCredentialsAction::getCredentialFields($maria);
        $this->assertNotNull($fields);
        $this->assertEquals('mariadb_password', $fields['password']);
        $this->assertEquals('mariadb_root_password', $fields['root_password']);

        // MongoDB
        $mongo = $this->createMock(StandaloneMongodb::class);
        $fields = RotateCredentialsAction::getCredentialFields($mongo);
        $this->assertNotNull($fields);
        $this->assertEquals('mongo_initdb_root_password', $fields['password']);
    }

    #[Test]
    public function action_uses_as_action_trait(): void
    {
        $action = new RotateCredentialsAction;
        $traits = class_uses_recursive($action);

        $this->assertArrayHasKey(\Lorisleiva\Actions\Concerns\AsAction::class, $traits);
    }
}
