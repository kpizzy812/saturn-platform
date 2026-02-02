<?php

namespace Tests\Unit\Services;

use App\Services\IncidentTimelineService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class IncidentTimelineServiceTest extends TestCase
{
    private IncidentTimelineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IncidentTimelineService;
    }

    public function test_constants_are_defined(): void
    {
        // Event types
        $this->assertEquals('status_change', IncidentTimelineService::TYPE_STATUS_CHANGE);
        $this->assertEquals('deployment', IncidentTimelineService::TYPE_DEPLOYMENT);
        $this->assertEquals('alert', IncidentTimelineService::TYPE_ALERT);
        $this->assertEquals('rollback', IncidentTimelineService::TYPE_ROLLBACK);
        $this->assertEquals('action', IncidentTimelineService::TYPE_ACTION);
        $this->assertEquals('metric', IncidentTimelineService::TYPE_METRIC);

        // Severity levels
        $this->assertEquals('critical', IncidentTimelineService::SEVERITY_CRITICAL);
        $this->assertEquals('warning', IncidentTimelineService::SEVERITY_WARNING);
        $this->assertEquals('info', IncidentTimelineService::SEVERITY_INFO);
        $this->assertEquals('success', IncidentTimelineService::SEVERITY_SUCCESS);
    }

    public function test_detect_incidents_groups_events_within_threshold(): void
    {
        $service = new class extends IncidentTimelineService
        {
            public function publicDetectIncidents(Collection $events): array
            {
                return $this->detectIncidents($events);
            }
        };

        // Create events within 5 minutes of each other (should be one incident)
        $events = collect([
            [
                'id' => 'event_1',
                'severity' => 'critical',
                'timestamp' => Carbon::now()->subMinutes(10)->toIso8601String(),
            ],
            [
                'id' => 'event_2',
                'severity' => 'warning',
                'timestamp' => Carbon::now()->subMinutes(8)->toIso8601String(),
            ],
            [
                'id' => 'event_3',
                'severity' => 'critical',
                'timestamp' => Carbon::now()->subMinutes(6)->toIso8601String(),
            ],
        ]);

        $incidents = $service->publicDetectIncidents($events);

        $this->assertCount(1, $incidents);
        $this->assertCount(3, $incidents[0]['events']);
        $this->assertEquals('critical', $incidents[0]['severity']);
    }

    public function test_detect_incidents_returns_array_with_required_keys(): void
    {
        $service = new class extends IncidentTimelineService
        {
            public function publicDetectIncidents(Collection $events): array
            {
                return $this->detectIncidents($events);
            }
        };

        $events = collect([
            [
                'id' => 'event_1',
                'severity' => 'critical',
                'timestamp' => Carbon::now()->subMinutes(5)->toIso8601String(),
            ],
        ]);

        $incidents = $service->publicDetectIncidents($events);

        $this->assertIsArray($incidents);
        $this->assertNotEmpty($incidents);

        $incident = $incidents[0];
        $this->assertArrayHasKey('id', $incident);
        $this->assertArrayHasKey('started_at', $incident);
        $this->assertArrayHasKey('ended_at', $incident);
        $this->assertArrayHasKey('events', $incident);
        $this->assertArrayHasKey('severity', $incident);
        $this->assertArrayHasKey('duration_seconds', $incident);
    }

    public function test_detect_incidents_ignores_info_and_success_events(): void
    {
        $service = new class extends IncidentTimelineService
        {
            public function publicDetectIncidents(Collection $events): array
            {
                return $this->detectIncidents($events);
            }
        };

        $events = collect([
            [
                'id' => 'event_1',
                'severity' => 'info',
                'timestamp' => Carbon::now()->subMinutes(5)->toIso8601String(),
            ],
            [
                'id' => 'event_2',
                'severity' => 'success',
                'timestamp' => Carbon::now()->subMinutes(3)->toIso8601String(),
            ],
        ]);

        $incidents = $service->publicDetectIncidents($events);

        $this->assertCount(0, $incidents);
    }

    public function test_calculate_health_status(): void
    {
        $service = new class extends IncidentTimelineService
        {
            public function publicCalculateHealthStatus(int $critical, int $warnings, int $incidents): string
            {
                return $this->calculateHealthStatus($critical, $warnings, $incidents);
            }
        };

        // Critical events = critical status
        $this->assertEquals('critical', $service->publicCalculateHealthStatus(1, 0, 0));
        $this->assertEquals('critical', $service->publicCalculateHealthStatus(0, 0, 1));

        // Many warnings = degraded
        $this->assertEquals('degraded', $service->publicCalculateHealthStatus(0, 3, 0));

        // Few warnings = warning
        $this->assertEquals('warning', $service->publicCalculateHealthStatus(0, 1, 0));
        $this->assertEquals('warning', $service->publicCalculateHealthStatus(0, 2, 0));

        // No issues = healthy
        $this->assertEquals('healthy', $service->publicCalculateHealthStatus(0, 0, 0));
    }

    public function test_generate_summary_structure(): void
    {
        $service = new class extends IncidentTimelineService
        {
            public function publicGenerateSummary(Collection $events, array $incidents): array
            {
                return $this->generateSummary($events, $incidents);
            }
        };

        $events = collect([
            ['type' => 'deployment', 'severity' => 'success', 'metadata' => ['status' => 'finished']],
            ['type' => 'deployment', 'severity' => 'critical', 'metadata' => ['status' => 'failed']],
            ['type' => 'alert', 'severity' => 'warning', 'metadata' => []],
        ]);

        $incidents = [
            ['id' => 'incident_1', 'events' => ['e1', 'e2']],
        ];

        $summary = $service->publicGenerateSummary($events, $incidents);

        $this->assertArrayHasKey('total_events', $summary);
        $this->assertArrayHasKey('critical_events', $summary);
        $this->assertArrayHasKey('warning_events', $summary);
        $this->assertArrayHasKey('incidents_count', $summary);
        $this->assertArrayHasKey('deployments', $summary);
        $this->assertArrayHasKey('health_status', $summary);

        $this->assertEquals(3, $summary['total_events']);
        $this->assertEquals(1, $summary['critical_events']);
        $this->assertEquals(1, $summary['warning_events']);
        $this->assertEquals(1, $summary['incidents_count']);
        $this->assertEquals(2, $summary['deployments']['total']);
        $this->assertEquals(1, $summary['deployments']['failed']);
    }
}
