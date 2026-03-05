<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ConnectProxyToNetworksJob;
use App\Jobs\RegenerateSslCertJob;
use App\Models\Server;
use App\Models\Team;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for infrastructure Jobs: ConnectProxyToNetworksJob,
 * RegenerateSslCertJob, CleanupStaleMultiplexedConnections,
 * DatabaseMetricsManagerJob.
 */
class InfrastructureJobsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // ConnectProxyToNetworksJob
    // =========================================================================

    /** @test */
    public function connect_proxy_job_implements_encrypted_and_silenced(): void
    {
        $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
        $job = new ConnectProxyToNetworksJob($server);

        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
    }

    /** @test */
    public function connect_proxy_job_has_single_try(): void
    {
        $server = Mockery::mock(Server::class)->makePartial()->shouldIgnoreMissing();
        $job = new ConnectProxyToNetworksJob($server);

        $this->assertEquals(1, $job->tries);
        $this->assertEquals(60, $job->timeout);
    }

    /** @test */
    public function connect_proxy_job_uses_without_overlapping_middleware(): void
    {
        $source = file_get_contents(app_path('Jobs/ConnectProxyToNetworksJob.php'));

        $this->assertStringContainsString('WithoutOverlapping', $source);
        $this->assertStringContainsString("'connect-proxy-networks-'", $source);
        $this->assertStringContainsString('expireAfter(60)', $source);
        $this->assertStringContainsString('dontRelease()', $source);
    }

    /** @test */
    public function connect_proxy_job_source_checks_server_functional(): void
    {
        $source = file_get_contents(app_path('Jobs/ConnectProxyToNetworksJob.php'));

        $this->assertStringContainsString('$this->server->isFunctional()', $source);
    }

    /** @test */
    public function connect_proxy_job_source_uses_connect_proxy_helper(): void
    {
        $source = file_get_contents(app_path('Jobs/ConnectProxyToNetworksJob.php'));

        $this->assertStringContainsString('connectProxyToNetworks($this->server)', $source);
    }

    /** @test */
    public function connect_proxy_job_source_skips_when_no_commands(): void
    {
        $source = file_get_contents(app_path('Jobs/ConnectProxyToNetworksJob.php'));

        $this->assertStringContainsString('empty($connectProxyToDockerNetworks)', $source);
    }

    // =========================================================================
    // RegenerateSslCertJob
    // =========================================================================

    /** @test */
    public function regenerate_ssl_job_has_retry_config(): void
    {
        $job = new RegenerateSslCertJob;

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);
        $this->assertEquals(60, $job->backoff);
    }

    /** @test */
    public function regenerate_ssl_job_accepts_optional_parameters(): void
    {
        $team = Mockery::mock(Team::class)->makePartial()->shouldIgnoreMissing();
        $job = new RegenerateSslCertJob(
            team: $team,
            server_id: 5,
            force_regeneration: true
        );

        $this->assertNotNull($job);
    }

    /** @test */
    public function regenerate_ssl_job_source_filters_by_server_id(): void
    {
        $source = file_get_contents(app_path('Jobs/RegenerateSslCertJob.php'));

        $this->assertStringContainsString("where('server_id', \$this->server_id)", $source);
    }

    /** @test */
    public function regenerate_ssl_job_source_checks_expiration_within_14_days(): void
    {
        $source = file_get_contents(app_path('Jobs/RegenerateSslCertJob.php'));

        $this->assertStringContainsString("now()->addDays(14)", $source);
        $this->assertStringContainsString("'valid_until', '<='", $source);
    }

    /** @test */
    public function regenerate_ssl_job_source_skips_ca_certificates(): void
    {
        $source = file_get_contents(app_path('Jobs/RegenerateSslCertJob.php'));

        $this->assertStringContainsString("where('is_ca_certificate', false)", $source);
    }

    /** @test */
    public function regenerate_ssl_job_source_uses_ssl_helper(): void
    {
        $source = file_get_contents(app_path('Jobs/RegenerateSslCertJob.php'));

        $this->assertStringContainsString('SslHelper::generateSslCertificate(', $source);
    }

    /** @test */
    public function regenerate_ssl_job_source_notifies_team_after_regeneration(): void
    {
        $source = file_get_contents(app_path('Jobs/RegenerateSslCertJob.php'));

        $this->assertStringContainsString('SslExpirationNotification', $source);
        $this->assertStringContainsString('$this->team?->notify(', $source);
    }

    /** @test */
    public function regenerate_ssl_job_source_supports_force_regeneration(): void
    {
        $source = file_get_contents(app_path('Jobs/RegenerateSslCertJob.php'));

        $this->assertStringContainsString('$this->force_regeneration', $source);
    }

    /** @test */
    public function regenerate_ssl_job_source_uses_cursor_for_memory_efficiency(): void
    {
        $source = file_get_contents(app_path('Jobs/RegenerateSslCertJob.php'));

        $this->assertStringContainsString('cursor()->each(', $source);
    }

    // =========================================================================
    // CleanupStaleMultiplexedConnections
    // =========================================================================

    /** @test */
    public function cleanup_mux_job_source_has_single_try(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupStaleMultiplexedConnections.php'));

        $this->assertStringContainsString('$tries = 1', $source);
        $this->assertStringContainsString('$timeout = 60', $source);
    }

    /** @test */
    public function cleanup_mux_job_source_loads_all_servers_once(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupStaleMultiplexedConnections.php'));

        $this->assertStringContainsString("Server::select('uuid', 'ip', 'user')->get()->keyBy('uuid')", $source);
    }

    /** @test */
    public function cleanup_mux_job_source_checks_ssh_connection_status(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupStaleMultiplexedConnections.php'));

        $this->assertStringContainsString('ssh -O check', $source);
        $this->assertStringContainsString('ControlPath=', $source);
    }

    /** @test */
    public function cleanup_mux_job_source_checks_connection_expiration(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupStaleMultiplexedConnections.php'));

        $this->assertStringContainsString('Carbon::parse(', $source);
        $this->assertStringContainsString('addSeconds(config(', $source);
        $this->assertStringContainsString('isAfter($expirationTime)', $source);
    }

    /** @test */
    public function cleanup_mux_job_source_removes_files_for_nonexistent_servers(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupStaleMultiplexedConnections.php'));

        $this->assertStringContainsString('cleanupNonExistentServerConnections', $source);
        $this->assertStringContainsString('$existingServerUuids', $source);
    }

    /** @test */
    public function cleanup_mux_job_source_closes_ssh_and_deletes_file(): void
    {
        $source = file_get_contents(app_path('Jobs/CleanupStaleMultiplexedConnections.php'));

        $this->assertStringContainsString('ssh -O exit', $source);
        $this->assertStringContainsString("Storage::disk('ssh-mux')->delete(", $source);
    }

    // =========================================================================
    // DatabaseMetricsManagerJob
    // =========================================================================

    /** @test */
    public function db_metrics_job_source_has_config(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseMetricsManagerJob.php'));

        $this->assertStringContainsString('$tries = 1', $source);
        $this->assertStringContainsString('$timeout = 120', $source);
    }

    /** @test */
    public function db_metrics_job_source_dispatches_per_server(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseMetricsManagerJob.php'));

        $this->assertStringContainsString('CollectDatabaseMetricsJob::dispatch($server)', $source);
        $this->assertStringContainsString('$server->isFunctional()', $source);
        $this->assertStringContainsString('$server->databases()->isNotEmpty()', $source);
    }

    /** @test */
    public function db_metrics_job_source_cleans_up_old_metrics(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseMetricsManagerJob.php'));

        $this->assertStringContainsString('cleanupOldMetrics(30)', $source);
        $this->assertStringContainsString('DatabaseMetric::cleanupOldMetrics(', $source);
    }

    /** @test */
    public function db_metrics_job_source_filters_servers_for_cloud(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseMetricsManagerJob.php'));

        $this->assertStringContainsString('isCloud()', $source);
        $this->assertStringContainsString('stripe_invoice_paid', $source);
        $this->assertStringContainsString("Team::find(0)", $source);
    }

    /** @test */
    public function db_metrics_job_source_excludes_placeholder_servers(): void
    {
        $source = file_get_contents(app_path('Jobs/DatabaseMetricsManagerJob.php'));

        $this->assertStringContainsString("where('ip', '!=', '1.2.3.4')", $source);
    }
}
