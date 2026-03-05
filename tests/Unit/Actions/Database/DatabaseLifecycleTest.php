<?php

namespace Tests\Unit\Actions\Database;

use Tests\TestCase;

/**
 * Unit tests for Database lifecycle Actions: StartPostgresql, StartMysql,
 * StartRedis, StartDatabaseProxy, RestartDatabase, StopDatabase.
 *
 * Uses source-level assertions since these actions execute remote SSH
 * commands and require running Docker containers.
 */
class DatabaseLifecycleTest extends TestCase
{
    // =========================================================================
    // StartPostgresql
    // =========================================================================

    /** @test */
    public function start_postgresql_sets_status_to_starting(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString("update(['status' => 'starting'])", $source);
    }

    /** @test */
    public function start_postgresql_creates_configuration_directories(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString('mkdir -p $this->configuration_dir', $source);
        $this->assertStringContainsString('docker-entrypoint-initdb.d', $source);
    }

    /** @test */
    public function start_postgresql_generates_env_vars_with_defaults(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString('POSTGRES_USER', $source);
        $this->assertStringContainsString('PGUSER', $source);
        $this->assertStringContainsString('POSTGRES_PASSWORD', $source);
        $this->assertStringContainsString('POSTGRES_DB', $source);
    }

    /** @test */
    public function start_postgresql_includes_healthcheck(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString('psql -U', $source);
        $this->assertStringContainsString('SELECT 1', $source);
    }

    /** @test */
    public function start_postgresql_handles_ssl_configuration(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString('enable_ssl', $source);
        $this->assertStringContainsString('ssl=on', $source);
        $this->assertStringContainsString('ssl_cert_file', $source);
        $this->assertStringContainsString('ssl_key_file', $source);
    }

    /** @test */
    public function start_postgresql_generates_ssl_certificate_when_missing(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString('SslHelper::generateSslCertificate(', $source);
        $this->assertStringContainsString('generateCaCertificate()', $source);
    }

    /** @test */
    public function start_postgresql_sets_resource_limits(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString('mem_limit', $source);
        $this->assertStringContainsString('memswap_limit', $source);
        $this->assertStringContainsString('mem_swappiness', $source);
        $this->assertStringContainsString('mem_reservation', $source);
        $this->assertStringContainsString('cpus', $source);
        $this->assertStringContainsString('cpu_shares', $source);
    }

    /** @test */
    public function start_postgresql_handles_custom_config(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString('postgres_conf', $source);
        $this->assertStringContainsString('config_file=/etc/postgresql/postgresql.conf', $source);
    }

    /** @test */
    public function start_postgresql_ensures_listen_addresses_in_config(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString("listen_addresses = '*'", $source);
    }

    /** @test */
    public function start_postgresql_sanitizes_init_script_filenames(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString('basename((string) $filename)', $source);
        $this->assertStringContainsString("preg_replace('/[^a-zA-Z0-9._-]/', '_', \$filename)", $source);
        $this->assertStringContainsString('escapeshellarg($filename)', $source);
    }

    /** @test */
    public function start_postgresql_fires_database_status_changed_event(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString("callEventOnFinish: 'DatabaseStatusChanged'", $source);
        $this->assertStringContainsString("'databaseId'", $source);
    }

    /** @test */
    public function start_postgresql_handles_log_drain(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString('isLogDrainEnabled()', $source);
        $this->assertStringContainsString('generate_fluentd_configuration()', $source);
    }

    /** @test */
    public function start_postgresql_handles_custom_docker_run_options(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartPostgresql.php'));

        $this->assertStringContainsString('convertDockerRunToCompose(', $source);
        $this->assertStringContainsString('generateCustomDockerRunOptionsForDatabases(', $source);
    }

    // =========================================================================
    // StartMysql
    // =========================================================================

    /** @test */
    public function start_mysql_sets_status_to_starting(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartMysql.php'));

        $this->assertStringContainsString("update(['status' => 'starting'])", $source);
    }

    /** @test */
    public function start_mysql_generates_env_vars_with_defaults(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartMysql.php'));

        $this->assertStringContainsString('MYSQL_ROOT_PASSWORD', $source);
        $this->assertStringContainsString('MYSQL_DATABASE', $source);
        $this->assertStringContainsString('MYSQL_USER', $source);
        $this->assertStringContainsString('MYSQL_PASSWORD', $source);
    }

    /** @test */
    public function start_mysql_includes_healthcheck(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartMysql.php'));

        $this->assertStringContainsString('mysqladmin', $source);
        $this->assertStringContainsString('ping', $source);
    }

    /** @test */
    public function start_mysql_handles_ssl_with_require_secure_transport(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartMysql.php'));

        $this->assertStringContainsString('--require-secure-transport=1', $source);
        $this->assertStringContainsString('--ssl-cert=', $source);
        $this->assertStringContainsString('--ssl-key=', $source);
        $this->assertStringContainsString('--ssl-ca=', $source);
    }

    /** @test */
    public function start_mysql_handles_custom_mysql_conf(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartMysql.php'));

        $this->assertStringContainsString('mysql_conf', $source);
        $this->assertStringContainsString('custom-config.cnf', $source);
    }

    /** @test */
    public function start_mysql_fires_database_status_changed_event(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartMysql.php'));

        $this->assertStringContainsString("callEventOnFinish: 'DatabaseStatusChanged'", $source);
    }

    // =========================================================================
    // StartRedis
    // =========================================================================

    /** @test */
    public function start_redis_sets_status_to_starting(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartRedis.php'));

        $this->assertStringContainsString("update(['status' => 'starting'])", $source);
    }

    /** @test */
    public function start_redis_builds_start_command_with_password(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartRedis.php'));

        $this->assertStringContainsString('--requirepass', $source);
        $this->assertStringContainsString('redis_password', $source);
    }

    /** @test */
    public function start_redis_uses_appendonly_by_default(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartRedis.php'));

        $this->assertStringContainsString('--appendonly yes', $source);
    }

    /** @test */
    public function start_redis_handles_custom_redis_conf(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartRedis.php'));

        $this->assertStringContainsString('redis_conf', $source);
        $this->assertStringContainsString('redis.conf', $source);
    }

    /** @test */
    public function start_redis_handles_ssl_with_tls_args(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartRedis.php'));

        $this->assertStringContainsString('--tls-port 6380', $source);
        $this->assertStringContainsString('--tls-cert-file', $source);
        $this->assertStringContainsString('--tls-key-file', $source);
        $this->assertStringContainsString('--tls-ca-cert-file', $source);
        $this->assertStringContainsString('--tls-auth-clients optional', $source);
    }

    /** @test */
    public function start_redis_syncs_shared_env_password(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartRedis.php'));

        $this->assertStringContainsString("if (\$env->key === 'REDIS_PASSWORD')", $source);
        $this->assertStringContainsString("if (\$env->key === 'REDIS_USERNAME')", $source);
        $this->assertStringContainsString('$env->is_shared', $source);
    }

    /** @test */
    public function start_redis_includes_healthcheck(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartRedis.php'));

        $this->assertStringContainsString('redis-cli', $source);
        $this->assertStringContainsString('ping', $source);
    }

    // =========================================================================
    // StartDatabaseProxy
    // =========================================================================

    /** @test */
    public function start_database_proxy_maps_correct_internal_ports(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartDatabaseProxy.php'));

        $this->assertStringContainsString("'standalone-mariadb', 'standalone-mysql' => 3306", $source);
        $this->assertStringContainsString("'standalone-postgresql'", $source);
        $this->assertStringContainsString('5432', $source);
        $this->assertStringContainsString("'standalone-redis'", $source);
        $this->assertStringContainsString('6379', $source);
        $this->assertStringContainsString("'standalone-clickhouse' => 9000", $source);
        $this->assertStringContainsString("'standalone-mongodb' => 27017", $source);
    }

    /** @test */
    public function start_database_proxy_uses_ssl_ports_when_enabled(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartDatabaseProxy.php'));

        $this->assertStringContainsString('$isSSLEnabled', $source);
        $this->assertStringContainsString("'standalone-redis', 'standalone-keydb', 'standalone-dragonfly' => 6380", $source);
    }

    /** @test */
    public function start_database_proxy_verifies_container_exists_before_starting(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartDatabaseProxy.php'));

        $this->assertStringContainsString('docker inspect {', $source);
        $this->assertStringContainsString('not_found', $source);
        $this->assertStringContainsString('will retry', $source);
    }

    /** @test */
    public function start_database_proxy_uses_nginx_stream_proxy(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartDatabaseProxy.php'));

        $this->assertStringContainsString('nginx:stable-alpine', $source);
        $this->assertStringContainsString('stream', $source);
        $this->assertStringContainsString('proxy_pass', $source);
    }

    /** @test */
    public function start_database_proxy_handles_service_database(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartDatabaseProxy.php'));

        $this->assertStringContainsString('ServiceDatabase', $source);
        $this->assertStringContainsString('$database->databaseType()', $source);
        $this->assertStringContainsString('$database->service->uuid', $source);
    }

    /** @test */
    public function start_database_proxy_uses_unique_project_name(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartDatabaseProxy.php'));

        $this->assertStringContainsString('db-proxy-{', $source);
        $this->assertStringContainsString('--project-name', $source);
    }

    /** @test */
    public function start_database_proxy_has_retry_configuration(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StartDatabaseProxy.php'));

        $this->assertStringContainsString('$jobTries = 5', $source);
        $this->assertStringContainsString('$jobMaxExceptions = 5', $source);
        $this->assertStringContainsString('$jobBackoff = [10, 15, 30, 60, 120]', $source);
    }

    // =========================================================================
    // RestartDatabase
    // =========================================================================

    /** @test */
    public function restart_database_checks_server_is_functional(): void
    {
        $source = file_get_contents(app_path('Actions/Database/RestartDatabase.php'));

        $this->assertStringContainsString('$server->isFunctional()', $source);
        $this->assertStringContainsString("'Server is not functional'", $source);
    }

    /** @test */
    public function restart_database_calls_stop_then_start(): void
    {
        $source = file_get_contents(app_path('Actions/Database/RestartDatabase.php'));

        $this->assertStringContainsString('StopDatabase::run(', $source);
        $this->assertStringContainsString('StartDatabase::run(', $source);
    }

    /** @test */
    public function restart_database_skips_docker_cleanup_on_stop(): void
    {
        $source = file_get_contents(app_path('Actions/Database/RestartDatabase.php'));

        $this->assertStringContainsString('dockerCleanup: false', $source);
    }

    // =========================================================================
    // StopDatabase
    // =========================================================================

    /** @test */
    public function stop_database_checks_server_is_functional(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StopDatabase.php'));

        $this->assertStringContainsString('$server->isFunctional()', $source);
        $this->assertStringContainsString("'Server is not functional'", $source);
    }

    /** @test */
    public function stop_database_stops_and_removes_container(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StopDatabase.php'));

        $this->assertStringContainsString('docker stop -t', $source);
        $this->assertStringContainsString('docker rm -f', $source);
    }

    /** @test */
    public function stop_database_escapes_container_name(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StopDatabase.php'));

        $this->assertStringContainsString('escapeshellarg($containerName)', $source);
    }

    /** @test */
    public function stop_database_stops_proxy_when_public(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StopDatabase.php'));

        $this->assertStringContainsString('$database->is_public', $source);
        $this->assertStringContainsString('StopDatabaseProxy::run(', $source);
    }

    /** @test */
    public function stop_database_dispatches_docker_cleanup(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StopDatabase.php'));

        $this->assertStringContainsString('CleanupDocker::dispatch(', $source);
        $this->assertStringContainsString('if ($dockerCleanup)', $source);
    }

    /** @test */
    public function stop_database_dispatches_status_changed_event_in_finally(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StopDatabase.php'));

        $this->assertStringContainsString('ServiceStatusChanged::dispatch(', $source);
        $this->assertStringContainsString('finally', $source);
    }

    /** @test */
    public function stop_database_uses_null_safe_operator_for_team_id(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StopDatabase.php'));

        $this->assertStringContainsString('$database->environment?->project?->team?->id', $source);
    }

    /** @test */
    public function stop_database_returns_success_message(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StopDatabase.php'));

        $this->assertStringContainsString("'Database stopped successfully'", $source);
    }

    /** @test */
    public function stop_database_catches_exceptions_with_message(): void
    {
        $source = file_get_contents(app_path('Actions/Database/StopDatabase.php'));

        $this->assertStringContainsString('catch (\Exception $e)', $source);
        $this->assertStringContainsString("'Database stop failed: '", $source);
    }
}
