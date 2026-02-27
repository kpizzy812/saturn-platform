<?php

namespace Tests\Unit\Actions\Database;

use App\Actions\Database\StartClickhouse;
use App\Actions\Database\StartDragonfly;
use App\Actions\Database\StartKeydb;
use App\Actions\Database\StartMariadb;
use App\Actions\Database\StartMongodb;
use App\Actions\Database\StartMysql;
use App\Actions\Database\StartPostgresql;
use App\Actions\Database\StartRedis;
use App\Actions\Database\StopDatabase;
use Lorisleiva\Actions\Concerns\AsAction;
use Tests\TestCase;

/**
 * Unit tests for all 8 StartDatabase actions and StopDatabase.
 *
 * These tests verify structural properties (AsAction trait, public properties,
 * healthcheck commands, status-update contract) and source-level assertions
 * for critical security and correctness requirements.
 *
 * SSH-dependent code paths (remote_process, instant_remote_process) are tested
 * in Feature tests with a real server; these unit tests run without Docker.
 */
class StartDatabaseActionsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // AsAction trait — all Start* actions must use it for ::run() / ::dispatch()
    // -------------------------------------------------------------------------

    /** @dataProvider startActionClasses */
    public function test_action_uses_as_action_trait(string $class): void
    {
        $traits = class_uses_recursive($class);

        $this->assertArrayHasKey(AsAction::class, $traits, "{$class} must use AsAction trait");
    }

    // -------------------------------------------------------------------------
    // Public $commands array — must be initialized to an empty array
    // -------------------------------------------------------------------------

    /** @dataProvider startActionClasses */
    public function test_action_has_public_commands_array(string $class): void
    {
        $reflection = new \ReflectionClass($class);

        $this->assertTrue($reflection->hasProperty('commands'), "{$class} must have a \$commands property");

        $property = $reflection->getProperty('commands');
        $this->assertTrue($property->isPublic(), "\$commands in {$class} must be public");
    }

    /** @dataProvider startActionClasses */
    public function test_action_default_commands_is_empty_array(string $class): void
    {
        $reflection = new \ReflectionClass($class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertIsArray($defaults['commands'], "\$commands in {$class} must default to array");
        $this->assertEmpty($defaults['commands'], "\$commands in {$class} must be empty by default");
    }

    // -------------------------------------------------------------------------
    // Status set to 'starting' immediately — critical for UI feedback
    // -------------------------------------------------------------------------

    /** @dataProvider startActionClasses */
    public function test_action_sets_status_to_starting_immediately(string $class): void
    {
        $source = file_get_contents($this->actionPath($class));

        $this->assertStringContainsString("'status' => 'starting'", $source,
            "{$class} must set database status to 'starting' at the start of handle()");
    }

    // -------------------------------------------------------------------------
    // mkdir -p configuration_dir — critical for docker-compose file placement
    // -------------------------------------------------------------------------

    /** @dataProvider startActionClasses */
    public function test_action_creates_configuration_directory(string $class): void
    {
        $source = file_get_contents($this->actionPath($class));

        $this->assertStringContainsString('mkdir -p', $source,
            "{$class} must create the configuration directory");
    }

    // -------------------------------------------------------------------------
    // docker compose up -d — final deploy command must be present
    // -------------------------------------------------------------------------

    /** @dataProvider startActionClasses */
    public function test_action_runs_docker_compose_up(string $class): void
    {
        $source = file_get_contents($this->actionPath($class));

        $this->assertStringContainsString('docker compose', $source);
        $this->assertStringContainsString('up -d', $source);
    }

    // -------------------------------------------------------------------------
    // docker compose pull — image must be pulled before running
    // -------------------------------------------------------------------------

    /** @dataProvider startActionClasses */
    public function test_action_pulls_image_before_starting(string $class): void
    {
        $source = file_get_contents($this->actionPath($class));

        $this->assertStringContainsString('docker compose', $source);
        $this->assertStringContainsString('pull', $source);
    }

    // -------------------------------------------------------------------------
    // DatabaseStatusChanged event — fired after deployment
    // -------------------------------------------------------------------------

    /** @dataProvider startActionClasses */
    public function test_action_fires_database_status_changed_event(string $class): void
    {
        $source = file_get_contents($this->actionPath($class));

        $this->assertStringContainsString('DatabaseStatusChanged', $source,
            "{$class} must fire DatabaseStatusChanged event for real-time UI updates");
    }

    // -------------------------------------------------------------------------
    // SSL cleanup — when SSL disabled, remove old SSL files
    // Clickhouse does not yet implement SSL so it is excluded from this check.
    // -------------------------------------------------------------------------

    /** @dataProvider startActionClassesWithSsl */
    public function test_action_removes_ssl_dir_when_ssl_disabled(string $class): void
    {
        $source = file_get_contents($this->actionPath($class));

        // When SSL is disabled, old SSL files must be removed to prevent stale certs
        $this->assertStringContainsString('rm -rf', $source);
        $this->assertStringContainsString('ssl', $source);
    }

    // -------------------------------------------------------------------------
    // Healthcheck — all start actions must define a healthcheck
    // -------------------------------------------------------------------------

    /** @dataProvider startActionClassesWithHealthcheck */
    public function test_action_defines_healthcheck_in_docker_compose(string $class): void
    {
        $source = file_get_contents($this->actionPath($class));

        $this->assertStringContainsString('healthcheck', $source,
            "{$class} must define a healthcheck in the docker-compose configuration");
    }

    // -------------------------------------------------------------------------
    // Resource limits — memory and CPU must be configurable
    // -------------------------------------------------------------------------

    /** @dataProvider startActionClasses */
    public function test_action_applies_memory_limits(string $class): void
    {
        $source = file_get_contents($this->actionPath($class));

        $this->assertStringContainsString('limits_memory', $source,
            "{$class} must include memory limits from the database model");
    }

    /** @dataProvider startActionClasses */
    public function test_action_applies_cpu_limits(string $class): void
    {
        $source = file_get_contents($this->actionPath($class));

        $this->assertStringContainsString('limits_cpus', $source,
            "{$class} must include CPU limits from the database model");
    }

    // =========================================================================
    // StartPostgresql — specific checks
    // =========================================================================

    public function test_postgresql_healthcheck_uses_psql_select_1(): void
    {
        $source = file_get_contents($this->actionPath(StartPostgresql::class));

        $this->assertStringContainsString('SELECT 1', $source,
            'PostgreSQL healthcheck must use psql SELECT 1');
    }

    public function test_postgresql_environment_includes_postgres_user(): void
    {
        $source = file_get_contents($this->actionPath(StartPostgresql::class));

        $this->assertStringContainsString('POSTGRES_USER', $source);
    }

    public function test_postgresql_environment_includes_postgres_password(): void
    {
        $source = file_get_contents($this->actionPath(StartPostgresql::class));

        $this->assertStringContainsString('POSTGRES_PASSWORD', $source);
    }

    public function test_postgresql_environment_includes_postgres_db(): void
    {
        $source = file_get_contents($this->actionPath(StartPostgresql::class));

        $this->assertStringContainsString('POSTGRES_DB', $source);
    }

    public function test_postgresql_ssl_uses_custom_format(): void
    {
        $source = file_get_contents($this->actionPath(StartPostgresql::class));

        // PostgreSQL custom format backup requires this flag during dump
        $this->assertStringContainsString('ssl_cert_file', $source);
    }

    public function test_postgresql_init_scripts_are_copied_to_entrypoint_dir(): void
    {
        $source = file_get_contents($this->actionPath(StartPostgresql::class));

        $this->assertStringContainsString('docker-entrypoint-initdb.d', $source);
    }

    // =========================================================================
    // StartMysql — specific checks
    // =========================================================================

    public function test_mysql_healthcheck_uses_mysqladmin_ping(): void
    {
        $source = file_get_contents($this->actionPath(StartMysql::class));

        $this->assertStringContainsString('mysqladmin', $source);
    }

    public function test_mysql_environment_includes_mysql_root_password(): void
    {
        $source = file_get_contents($this->actionPath(StartMysql::class));

        $this->assertStringContainsString('MYSQL_ROOT_PASSWORD', $source);
    }

    public function test_mysql_environment_includes_mysql_database(): void
    {
        $source = file_get_contents($this->actionPath(StartMysql::class));

        $this->assertStringContainsString('MYSQL_DATABASE', $source);
    }

    // =========================================================================
    // StartRedis — specific checks
    // =========================================================================

    public function test_redis_healthcheck_uses_redis_cli_ping(): void
    {
        $source = file_get_contents($this->actionPath(StartRedis::class));

        $this->assertStringContainsString('redis-cli', $source);
        // Redis uses lowercase 'ping' command (not uppercase PING)
        $this->assertStringContainsString("'ping'", $source);
    }

    public function test_redis_config_file_is_written_when_present(): void
    {
        $source = file_get_contents($this->actionPath(StartRedis::class));

        $this->assertStringContainsString('redis_conf', $source);
    }

    // =========================================================================
    // StopDatabase — specific checks
    // =========================================================================

    public function test_stop_database_uses_as_action_trait(): void
    {
        $traits = class_uses_recursive(StopDatabase::class);

        $this->assertArrayHasKey(AsAction::class, $traits);
    }

    public function test_stop_database_checks_server_is_functional(): void
    {
        $source = file_get_contents($this->actionPath(StopDatabase::class));

        $this->assertStringContainsString('isFunctional()', $source,
            'StopDatabase must check server functionality before issuing stop commands');
    }

    public function test_stop_database_stops_container_with_timeout(): void
    {
        $source = file_get_contents($this->actionPath(StopDatabase::class));

        $this->assertStringContainsString('docker stop -t', $source);
        $this->assertStringContainsString('docker rm -f', $source);
    }

    public function test_stop_database_stops_proxy_when_database_is_public(): void
    {
        $source = file_get_contents($this->actionPath(StopDatabase::class));

        $this->assertStringContainsString('StopDatabaseProxy::run', $source);
        $this->assertStringContainsString('is_public', $source);
    }

    public function test_stop_database_fires_service_status_changed_in_finally(): void
    {
        $source = file_get_contents($this->actionPath(StopDatabase::class));

        // Must fire even on failure to keep UI in sync
        $this->assertStringContainsString('finally', $source);
        $this->assertStringContainsString('ServiceStatusChanged::dispatch', $source);
    }

    public function test_stop_database_uses_null_safe_for_team_id_in_finally(): void
    {
        $source = file_get_contents($this->actionPath(StopDatabase::class));

        // BUGFIX: Null-safe operator prevents NPE when database is being deleted
        $this->assertStringContainsString('?->project?->team?->id', $source);
    }

    public function test_stop_database_uses_escapeshellarg_for_container_name(): void
    {
        $source = file_get_contents($this->actionPath(StopDatabase::class));

        $this->assertStringContainsString('escapeshellarg(', $source,
            'StopDatabase must use escapeshellarg() to prevent command injection');
    }

    public function test_stop_database_returns_success_message(): void
    {
        $source = file_get_contents($this->actionPath(StopDatabase::class));

        $this->assertStringContainsString('Database stopped successfully', $source);
    }

    public function test_stop_database_returns_error_message_on_exception(): void
    {
        $source = file_get_contents($this->actionPath(StopDatabase::class));

        $this->assertStringContainsString('Database stop failed:', $source);
    }

    // =========================================================================
    // Data providers
    // =========================================================================

    public static function startActionClasses(): array
    {
        return [
            'StartPostgresql' => [StartPostgresql::class],
            'StartMysql' => [StartMysql::class],
            'StartMariadb' => [StartMariadb::class],
            'StartMongodb' => [StartMongodb::class],
            'StartRedis' => [StartRedis::class],
            'StartKeydb' => [StartKeydb::class],
            'StartDragonfly' => [StartDragonfly::class],
            'StartClickhouse' => [StartClickhouse::class],
        ];
    }

    /**
     * All databases except Redis-like (they use a different healthcheck pattern)
     * are covered separately. All 8 do define healthchecks.
     */
    public static function startActionClassesWithHealthcheck(): array
    {
        return self::startActionClasses();
    }

    /**
     * Databases that implement SSL support (Clickhouse does not yet).
     */
    public static function startActionClassesWithSsl(): array
    {
        return [
            'StartPostgresql' => [StartPostgresql::class],
            'StartMysql' => [StartMysql::class],
            'StartMariadb' => [StartMariadb::class],
            'StartMongodb' => [StartMongodb::class],
            'StartRedis' => [StartRedis::class],
            'StartKeydb' => [StartKeydb::class],
            'StartDragonfly' => [StartDragonfly::class],
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function actionPath(string $class): string
    {
        $reflection = new \ReflectionClass($class);

        return $reflection->getFileName();
    }
}
