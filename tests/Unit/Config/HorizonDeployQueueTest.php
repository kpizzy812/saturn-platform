<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

/**
 * Validates that deployment-related jobs are isolated on the 'deployments' queue
 * and that Horizon is configured with a dedicated supervisor for that queue.
 *
 * Prevents regressions where a queue change accidentally moves long-running
 * deployment jobs back to 'high', starving notifications and monitoring jobs.
 */
class HorizonDeployQueueTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Job queue assignments
    // ---------------------------------------------------------------------------

    public function test_monitor_deployment_health_job_uses_deployments_queue(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorDeploymentHealthJob.php'));

        $this->assertStringContainsString("'deployments'", $source);
        $this->assertStringNotContainsString("'high'", $source);
    }

    public function test_application_deployment_job_uses_deployments_queue(): void
    {
        $source = file_get_contents(app_path('Jobs/ApplicationDeploymentJob.php'));

        $this->assertStringContainsString("'deployments'", $source);
    }

    // ---------------------------------------------------------------------------
    // Horizon supervisor configuration
    // ---------------------------------------------------------------------------

    public function test_horizon_has_dedicated_deploy_supervisor(): void
    {
        $config = config('horizon.defaults');

        $this->assertArrayHasKey('s6-deploy', $config, 's6-deploy supervisor must exist in horizon defaults');
    }

    public function test_deploy_supervisor_processes_deployments_queue_only(): void
    {
        $deployConfig = config('horizon.defaults.s6-deploy');

        $this->assertSame('deployments', $deployConfig['queue']);
    }

    public function test_general_supervisor_does_not_process_deployments_queue(): void
    {
        $generalQueues = config('horizon.defaults.s6.queue');

        $this->assertStringNotContainsString('deployments', $generalQueues);
    }

    public function test_deploy_supervisor_has_minimum_2_processes_in_production(): void
    {
        $prodConfig = config('horizon.environments.production.s6-deploy');

        $minProcesses = $prodConfig['minProcesses'];
        $this->assertGreaterThanOrEqual(2, $minProcesses, 'Deploy supervisor needs at least 2 workers in production');
    }

    public function test_deploy_supervisor_exists_in_all_environments(): void
    {
        $environments = config('horizon.environments');

        foreach ($environments as $env => $supervisors) {
            $this->assertArrayHasKey(
                's6-deploy',
                $supervisors,
                "s6-deploy supervisor is missing in '{$env}' environment"
            );
        }
    }

    public function test_deploy_supervisor_has_higher_memory_limit_than_general(): void
    {
        $generalMemory = config('horizon.defaults.s6.memory');
        $deployMemory = config('horizon.defaults.s6-deploy.memory');

        $this->assertGreaterThan($generalMemory, $deployMemory, 'Deploy workers need more memory for build operations');
    }

    public function test_deployments_queue_has_wait_threshold_configured(): void
    {
        $waits = config('horizon.waits');

        $this->assertArrayHasKey('redis:deployments', $waits);
    }
}
