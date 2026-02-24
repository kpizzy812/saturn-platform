<?php

namespace Tests\Unit;

use App\Models\StatusPageResource;
use Tests\TestCase;

class StatusPageTest extends TestCase
{
    public function test_normalize_status_running(): void
    {
        $this->assertEquals('operational', StatusPageResource::normalizeStatus('running'));
    }

    public function test_normalize_status_healthy(): void
    {
        $this->assertEquals('operational', StatusPageResource::normalizeStatus('healthy'));
    }

    public function test_normalize_status_degraded(): void
    {
        $this->assertEquals('degraded', StatusPageResource::normalizeStatus('degraded'));
    }

    public function test_normalize_status_exited(): void
    {
        $this->assertEquals('major_outage', StatusPageResource::normalizeStatus('exited'));
    }

    public function test_normalize_status_stopped(): void
    {
        $this->assertEquals('major_outage', StatusPageResource::normalizeStatus('stopped'));
    }

    public function test_normalize_status_down(): void
    {
        $this->assertEquals('major_outage', StatusPageResource::normalizeStatus('down'));
    }

    public function test_normalize_status_unreachable(): void
    {
        $this->assertEquals('major_outage', StatusPageResource::normalizeStatus('unreachable'));
    }

    public function test_normalize_status_restarting(): void
    {
        $this->assertEquals('maintenance', StatusPageResource::normalizeStatus('restarting'));
    }

    public function test_normalize_status_in_progress(): void
    {
        $this->assertEquals('maintenance', StatusPageResource::normalizeStatus('in_progress'));
    }

    public function test_normalize_status_unknown(): void
    {
        $this->assertEquals('unknown', StatusPageResource::normalizeStatus('some-random-status'));
    }

    public function test_compute_overall_status_all_operational(): void
    {
        $statuses = ['operational', 'operational', 'operational'];
        $this->assertEquals('operational', StatusPageResource::computeOverallStatus($statuses));
    }

    public function test_compute_overall_status_with_degraded(): void
    {
        $statuses = ['operational', 'degraded', 'operational'];
        $this->assertEquals('partial_outage', StatusPageResource::computeOverallStatus($statuses));
    }

    public function test_compute_overall_status_with_major_outage(): void
    {
        $statuses = ['operational', 'degraded', 'major_outage'];
        $this->assertEquals('major_outage', StatusPageResource::computeOverallStatus($statuses));
    }

    public function test_compute_overall_status_with_maintenance(): void
    {
        $statuses = ['operational', 'maintenance'];
        $this->assertEquals('maintenance', StatusPageResource::computeOverallStatus($statuses));
    }

    public function test_compute_overall_status_empty(): void
    {
        $this->assertEquals('unknown', StatusPageResource::computeOverallStatus([]));
    }

    public function test_compute_overall_major_takes_priority_over_degraded(): void
    {
        $statuses = ['degraded', 'major_outage', 'maintenance'];
        $this->assertEquals('major_outage', StatusPageResource::computeOverallStatus($statuses));
    }

    public function test_model_fillable_fields(): void
    {
        $resource = new StatusPageResource;
        $fillable = $resource->getFillable();

        $this->assertContains('team_id', $fillable);
        $this->assertContains('resource_type', $fillable);
        $this->assertContains('resource_id', $fillable);
        $this->assertContains('display_name', $fillable);
        $this->assertContains('display_order', $fillable);
        $this->assertContains('is_visible', $fillable);
        $this->assertContains('group_name', $fillable);
    }

    public function test_model_casts(): void
    {
        $resource = new StatusPageResource;
        $casts = $resource->getCasts();

        $this->assertEquals('boolean', $casts['is_visible']);
        $this->assertEquals('integer', $casts['display_order']);
    }
}
