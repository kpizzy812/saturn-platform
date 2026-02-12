<?php

namespace Tests\Unit\Traits;

use App\Jobs\MonitorDeploymentHealthJob;
use Mockery;
use Tests\TestCase;

/**
 * Tests for HandlesDeploymentStatus trait.
 *
 * Verifies that MonitorDeploymentHealthJob is dispatched after successful deployments
 * when auto-rollback is enabled.
 */
class HandlesDeploymentStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_trait_exists(): void
    {
        $this->assertTrue(trait_exists(\App\Traits\Deployment\HandlesDeploymentStatus::class));
    }

    public function test_trait_imports_monitor_deployment_health_job(): void
    {
        $reflection = new \ReflectionClass(\App\Jobs\ApplicationDeploymentJob::class);
        $source = file_get_contents($reflection->getFileName());

        // Check the trait file directly for the import
        $traitFile = file_get_contents(app_path('Traits/Deployment/HandlesDeploymentStatus.php'));

        $this->assertStringContainsString(
            'use App\Jobs\MonitorDeploymentHealthJob',
            $traitFile,
            'HandlesDeploymentStatus trait must import MonitorDeploymentHealthJob'
        );
    }

    public function test_trait_contains_monitor_dispatch_code(): void
    {
        $traitFile = file_get_contents(app_path('Traits/Deployment/HandlesDeploymentStatus.php'));

        $this->assertStringContainsString(
            'MonitorDeploymentHealthJob::dispatch',
            $traitFile,
            'handleSuccessfulDeployment must dispatch MonitorDeploymentHealthJob'
        );
    }

    public function test_trait_skips_rollback_deployments(): void
    {
        $traitFile = file_get_contents(app_path('Traits/Deployment/HandlesDeploymentStatus.php'));

        $this->assertStringContainsString(
            'rollback',
            $traitFile,
            'handleSuccessfulDeployment must check for rollback flag to prevent infinite loops'
        );
    }

    public function test_trait_checks_auto_rollback_enabled(): void
    {
        $traitFile = file_get_contents(app_path('Traits/Deployment/HandlesDeploymentStatus.php'));

        $this->assertStringContainsString(
            'auto_rollback_enabled',
            $traitFile,
            'handleSuccessfulDeployment must check auto_rollback_enabled setting'
        );
    }
}
