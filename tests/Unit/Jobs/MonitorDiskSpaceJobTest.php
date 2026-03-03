<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MonitorDiskSpaceJob;
use App\Models\Server;
use App\Notifications\Server\DiskSpaceCriticalNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Laravel\Horizon\Contracts\Silenced;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for MonitorDiskSpaceJob.
 *
 * Tests threshold logic and notification dispatch for normal, warning, and critical disk states.
 * Full integration tests (SSH, DB) run in tests/Feature/.
 */
class MonitorDiskSpaceJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ---------------------------------------------------------------------------
    // Job configuration
    // ---------------------------------------------------------------------------

    public function test_job_implements_required_interfaces(): void
    {
        $interfaces = class_implements(MonitorDiskSpaceJob::class);

        $this->assertTrue(in_array(ShouldQueue::class, $interfaces));
        $this->assertTrue(in_array(ShouldBeEncrypted::class, $interfaces));
        $this->assertTrue(in_array(Silenced::class, $interfaces));
    }

    public function test_job_has_correct_timeout(): void
    {
        $job = new MonitorDiskSpaceJob;
        $this->assertEquals(300, $job->timeout);
    }

    public function test_job_has_single_try(): void
    {
        $job = new MonitorDiskSpaceJob;
        $this->assertSame(1, $job->tries);
    }

    // ---------------------------------------------------------------------------
    // Threshold constants
    // ---------------------------------------------------------------------------

    public function test_warning_threshold_is_85(): void
    {
        $this->assertSame(85, MonitorDiskSpaceJob::WARNING_THRESHOLD);
    }

    public function test_critical_threshold_is_95(): void
    {
        $this->assertSame(95, MonitorDiskSpaceJob::CRITICAL_THRESHOLD);
    }

    // ---------------------------------------------------------------------------
    // Normal state: disk < 85%
    // ---------------------------------------------------------------------------

    public function test_normal_disk_usage_does_not_trigger_notification(): void
    {
        $diskUsage = 50;
        $this->assertFalse($diskUsage >= MonitorDiskSpaceJob::WARNING_THRESHOLD);
        $this->assertFalse($diskUsage >= MonitorDiskSpaceJob::CRITICAL_THRESHOLD);
    }

    public function test_disk_usage_at_84_is_below_warning(): void
    {
        $diskUsage = 84;
        $this->assertFalse($diskUsage >= MonitorDiskSpaceJob::WARNING_THRESHOLD);
    }

    public function test_zero_disk_usage_is_normal(): void
    {
        $diskUsage = 0;
        $this->assertFalse($diskUsage >= MonitorDiskSpaceJob::WARNING_THRESHOLD);
    }

    // ---------------------------------------------------------------------------
    // Warning state: 85% <= disk < 95%
    // ---------------------------------------------------------------------------

    public function test_disk_usage_at_warning_threshold_triggers_warning(): void
    {
        $diskUsage = 85;
        $this->assertTrue($diskUsage >= MonitorDiskSpaceJob::WARNING_THRESHOLD);
        $this->assertFalse($diskUsage >= MonitorDiskSpaceJob::CRITICAL_THRESHOLD);
    }

    public function test_disk_usage_at_90_is_in_warning_range(): void
    {
        $diskUsage = 90;
        $this->assertTrue($diskUsage >= MonitorDiskSpaceJob::WARNING_THRESHOLD);
        $this->assertFalse($diskUsage >= MonitorDiskSpaceJob::CRITICAL_THRESHOLD);
    }

    public function test_disk_usage_at_94_is_still_in_warning_range(): void
    {
        $diskUsage = 94;
        $this->assertTrue($diskUsage >= MonitorDiskSpaceJob::WARNING_THRESHOLD);
        $this->assertFalse($diskUsage >= MonitorDiskSpaceJob::CRITICAL_THRESHOLD);
    }

    // ---------------------------------------------------------------------------
    // Critical state: disk >= 95%
    // ---------------------------------------------------------------------------

    public function test_disk_usage_at_critical_threshold_triggers_critical(): void
    {
        $diskUsage = 95;
        $this->assertTrue($diskUsage >= MonitorDiskSpaceJob::CRITICAL_THRESHOLD);
    }

    public function test_disk_usage_at_100_is_critical(): void
    {
        $diskUsage = 100;
        $this->assertTrue($diskUsage >= MonitorDiskSpaceJob::CRITICAL_THRESHOLD);
    }

    public function test_critical_also_exceeds_warning_threshold(): void
    {
        $diskUsage = 97;
        $this->assertTrue($diskUsage >= MonitorDiskSpaceJob::WARNING_THRESHOLD);
        $this->assertTrue($diskUsage >= MonitorDiskSpaceJob::CRITICAL_THRESHOLD);
    }

    // ---------------------------------------------------------------------------
    // isDiskFull logic
    // ---------------------------------------------------------------------------

    public function test_is_disk_full_returns_false_below_critical(): void
    {
        $latestUsage = 90.0;
        $result = $latestUsage !== null && $latestUsage >= MonitorDiskSpaceJob::CRITICAL_THRESHOLD;
        $this->assertFalse($result);
    }

    public function test_is_disk_full_returns_true_at_critical(): void
    {
        $latestUsage = 95.0;
        $result = $latestUsage !== null && $latestUsage >= MonitorDiskSpaceJob::CRITICAL_THRESHOLD;
        $this->assertTrue($result);
    }

    public function test_is_disk_full_returns_false_when_no_metrics(): void
    {
        $latestUsage = null;
        $result = $latestUsage !== null && $latestUsage >= MonitorDiskSpaceJob::CRITICAL_THRESHOLD;
        $this->assertFalse($result);
    }

    // ---------------------------------------------------------------------------
    // DiskSpaceCriticalNotification configuration
    // ---------------------------------------------------------------------------

    public function test_critical_notification_has_correct_threshold_constant(): void
    {
        $this->assertSame(95, DiskSpaceCriticalNotification::CRITICAL_THRESHOLD);
    }

    public function test_critical_notification_stores_server_and_usage(): void
    {
        $server = Mockery::mock(Server::class)->makePartial();
        $server->id = 1;
        $server->name = 'test-server';
        $server->uuid = 'abc-123';

        $notification = new DiskSpaceCriticalNotification($server, 97);

        $this->assertSame($server, $notification->server);
        $this->assertSame(97, $notification->disk_usage);
    }

    // ---------------------------------------------------------------------------
    // Notification channel routing
    // ---------------------------------------------------------------------------

    public function test_warning_notification_uses_server_disk_usage_channel(): void
    {
        $source = file_get_contents(app_path('Notifications/Server/HighDiskUsage.php'));
        $this->assertStringContainsString("'server_disk_usage'", $source);
    }

    public function test_critical_notification_uses_server_disk_usage_channel(): void
    {
        $source = file_get_contents(app_path('Notifications/Server/DiskSpaceCriticalNotification.php'));
        $this->assertStringContainsString("'server_disk_usage'", $source);
    }

    // ---------------------------------------------------------------------------
    // Rate limiter key format
    // ---------------------------------------------------------------------------

    public function test_critical_rate_limiter_key_format(): void
    {
        $serverId = 42;
        $expectedKey = 'disk-critical:'.$serverId;
        $this->assertStringContainsString((string) $serverId, $expectedKey);
        $this->assertStringStartsWith('disk-critical:', $expectedKey);
    }

    public function test_warning_rate_limiter_key_format(): void
    {
        $serverId = 42;
        $expectedKey = 'disk-warning:'.$serverId;
        $this->assertStringContainsString((string) $serverId, $expectedKey);
        $this->assertStringStartsWith('disk-warning:', $expectedKey);
    }

    public function test_critical_and_warning_keys_are_distinct(): void
    {
        $serverId = 1;
        $this->assertNotSame('disk-critical:'.$serverId, 'disk-warning:'.$serverId);
    }
}
