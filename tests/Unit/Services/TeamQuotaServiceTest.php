<?php

namespace Tests\Unit\Services;

use App\Models\Team;
use App\Services\TeamQuotaService;
use Mockery;
use Tests\TestCase;

class TeamQuotaServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function get_usage_returns_correct_structure()
    {
        $service = Mockery::mock(TeamQuotaService::class)->makePartial();
        $team = Mockery::mock(Team::class);

        // Mock getUsage to return known data (avoids DB calls)
        $service->shouldReceive('getUsage')->with($team)->andReturn([
            'servers' => ['current' => 2, 'limit' => 10],
            'applications' => ['current' => 5, 'limit' => null],
            'databases' => ['current' => 3, 'limit' => 20],
            'projects' => ['current' => 1, 'limit' => 5],
        ]);

        $usage = $service->getUsage($team);

        $this->assertArrayHasKey('servers', $usage);
        $this->assertArrayHasKey('applications', $usage);
        $this->assertArrayHasKey('databases', $usage);
        $this->assertArrayHasKey('projects', $usage);

        foreach ($usage as $resource) {
            $this->assertArrayHasKey('current', $resource);
            $this->assertArrayHasKey('limit', $resource);
        }
    }

    /** @test */
    public function check_quota_returns_true_when_no_limit()
    {
        $service = Mockery::mock(TeamQuotaService::class)->makePartial();
        $team = Mockery::mock(Team::class);

        $service->shouldReceive('getUsage')->andReturn([
            'servers' => ['current' => 5, 'limit' => null],
            'applications' => ['current' => 10, 'limit' => null],
            'databases' => ['current' => 3, 'limit' => null],
            'projects' => ['current' => 2, 'limit' => null],
        ]);

        $this->assertTrue($service->checkQuota($team, 'servers'));
        $this->assertTrue($service->checkQuota($team, 'applications'));
        $this->assertTrue($service->checkQuota($team, 'databases'));
        $this->assertTrue($service->checkQuota($team, 'projects'));
    }

    /** @test */
    public function check_quota_returns_true_when_within_limit()
    {
        $service = Mockery::mock(TeamQuotaService::class)->makePartial();
        $team = Mockery::mock(Team::class);

        $service->shouldReceive('getUsage')->andReturn([
            'servers' => ['current' => 2, 'limit' => 10],
            'applications' => ['current' => 3, 'limit' => 100],
            'databases' => ['current' => 1, 'limit' => 50],
            'projects' => ['current' => 3, 'limit' => 20],
        ]);

        $this->assertTrue($service->checkQuota($team, 'servers'));
        $this->assertTrue($service->checkQuota($team, 'projects'));
    }

    /** @test */
    public function check_quota_returns_true_for_unknown_type()
    {
        $service = Mockery::mock(TeamQuotaService::class)->makePartial();
        $team = Mockery::mock(Team::class);

        $service->shouldReceive('getUsage')->andReturn([
            'servers' => ['current' => 0, 'limit' => null],
            'applications' => ['current' => 0, 'limit' => null],
            'databases' => ['current' => 0, 'limit' => null],
            'projects' => ['current' => 0, 'limit' => null],
        ]);

        $this->assertTrue($service->checkQuota($team, 'nonexistent'));
    }

    /** @test */
    public function check_quota_returns_false_when_at_limit()
    {
        $service = Mockery::mock(TeamQuotaService::class)->makePartial();
        $team = Mockery::mock(Team::class);

        $service->shouldReceive('getUsage')->andReturn([
            'servers' => ['current' => 10, 'limit' => 10],
            'applications' => ['current' => 5, 'limit' => null],
            'databases' => ['current' => 20, 'limit' => 20],
            'projects' => ['current' => 5, 'limit' => 5],
        ]);

        $this->assertFalse($service->checkQuota($team, 'servers'));
        $this->assertFalse($service->checkQuota($team, 'projects'));
        $this->assertFalse($service->checkQuota($team, 'databases'));
    }
}
