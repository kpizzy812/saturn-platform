<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MonitorCanaryDeploymentJob;
use App\Models\ApplicationDeploymentQueue;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for Deployment Analysis Jobs:
 * AnalyzeDeploymentLogsJob, AnalyzeCodeReviewJob, MonitorCanaryDeploymentJob.
 */
class DeploymentAnalysisJobsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // AnalyzeDeploymentLogsJob
    // =========================================================================

    /** @test */
    public function analyze_deployment_logs_has_single_try(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeDeploymentLogsJob.php'));

        $this->assertStringContainsString('$tries = 1', $source);
        $this->assertStringContainsString('$timeout = 120', $source);
    }

    /** @test */
    public function analyze_deployment_logs_checks_ai_enabled(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeDeploymentLogsJob.php'));

        $this->assertStringContainsString("config('ai.enabled'", $source);
        $this->assertStringContainsString('AI analysis is disabled', $source);
    }

    /** @test */
    public function analyze_deployment_logs_validates_deployment_exists(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeDeploymentLogsJob.php'));

        $this->assertStringContainsString('ApplicationDeploymentQueue::find($this->deploymentId)', $source);
        $this->assertStringContainsString('Deployment not found for AI analysis', $source);
    }

    /** @test */
    public function analyze_deployment_logs_only_analyzes_failed_deployments(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeDeploymentLogsJob.php'));

        $this->assertStringContainsString("'failed'", $source);
        $this->assertStringContainsString('Cannot analyze: deployment status is', $source);
    }

    /** @test */
    public function analyze_deployment_logs_checks_provider_availability(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeDeploymentLogsJob.php'));

        $this->assertStringContainsString('$analyzer->isAvailable()', $source);
        $this->assertStringContainsString('No AI provider available', $source);
    }

    /** @test */
    public function analyze_deployment_logs_broadcasts_completion(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeDeploymentLogsJob.php'));

        $this->assertStringContainsString('DeploymentAnalysisCompleted', $source);
    }

    /** @test */
    public function analyze_deployment_logs_saves_failed_analysis(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeDeploymentLogsJob.php'));

        $this->assertStringContainsString('saveFailedAnalysis', $source);
        $this->assertStringContainsString('DeploymentLogAnalysis::updateOrCreate(', $source);
    }

    /** @test */
    public function analyze_deployment_logs_validates_logs_not_empty(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeDeploymentLogsJob.php'));

        $this->assertStringContainsString('No deployment logs available to analyze', $source);
    }

    // =========================================================================
    // AnalyzeCodeReviewJob
    // =========================================================================

    /** @test */
    public function analyze_code_review_has_retry_config(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeCodeReviewJob.php'));

        $this->assertStringContainsString('$tries = 2', $source);
        $this->assertStringContainsString('$timeout = 180', $source);
        $this->assertStringContainsString('$backoff', $source);
    }

    /** @test */
    public function analyze_code_review_checks_instance_setting(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeCodeReviewJob.php'));

        $this->assertStringContainsString('is_ai_code_review_enabled', $source);
        $this->assertStringContainsString('Code review is disabled in instance settings', $source);
    }

    /** @test */
    public function analyze_code_review_validates_commit_sha(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeCodeReviewJob.php'));

        $this->assertStringContainsString('No commit SHA available for code review', $source);
    }

    /** @test */
    public function analyze_code_review_uses_cache_key(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeCodeReviewJob.php'));

        $this->assertStringContainsString('computeCacheKey', $source);
        $this->assertStringContainsString('cache_key', $source);
        $this->assertStringContainsString('Code review cache hit', $source);
    }

    /** @test */
    public function analyze_code_review_runs_detectors(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeCodeReviewJob.php'));

        $this->assertStringContainsString('SecretsDetector', $source);
        $this->assertStringContainsString('DangerousFunction', $source);
        $this->assertStringContainsString('AICodeAnalyzer', $source);
    }

    /** @test */
    public function analyze_code_review_enriches_with_llm(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeCodeReviewJob.php'));

        $this->assertStringContainsString('LLMEnricher', $source);
        $this->assertStringContainsString('enrich(', $source);
    }

    /** @test */
    public function analyze_code_review_fetches_diff(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeCodeReviewJob.php'));

        $this->assertStringContainsString('DiffFetcher', $source);
        $this->assertStringContainsString('fetch(', $source);
        $this->assertStringContainsString('Empty diff, skipping code review', $source);
    }

    /** @test */
    public function analyze_code_review_handles_diff_size_limit(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeCodeReviewJob.php'));

        $this->assertStringContainsString('Diff exceeds size limit', $source);
    }

    /** @test */
    public function analyze_code_review_tracks_ai_usage(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeCodeReviewJob.php'));

        $this->assertStringContainsString('AiPricingService', $source);
        $this->assertStringContainsString('logUsage', $source);
    }

    /** @test */
    public function analyze_code_review_saves_violations(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeCodeReviewJob.php'));

        $this->assertStringContainsString('violations()->create(', $source);
        $this->assertStringContainsString('markAsCompleted(', $source);
        $this->assertStringContainsString('markAsFailed(', $source);
    }

    /** @test */
    public function analyze_code_review_stores_result_metadata(): void
    {
        $source = file_get_contents(app_path('Jobs/AnalyzeCodeReviewJob.php'));

        $this->assertStringContainsString("'summary'", $source);
        $this->assertStringContainsString("'files_analyzed'", $source);
        $this->assertStringContainsString("'violations_count'", $source);
        $this->assertStringContainsString("'critical_count'", $source);
    }

    // =========================================================================
    // MonitorCanaryDeploymentJob
    // =========================================================================

    /** @test */
    public function canary_monitor_stores_constructor_params(): void
    {
        $deployment = Mockery::mock(ApplicationDeploymentQueue::class)
            ->makePartial()
            ->shouldIgnoreMissing();

        $job = new MonitorCanaryDeploymentJob(
            $deployment,
            'canary-container',
            'stable-container',
            2,
            1
        );

        $this->assertSame($deployment, $job->deployment);
        $this->assertEquals('canary-container', $job->canaryContainer);
        $this->assertEquals('stable-container', $job->stableContainer);
        $this->assertEquals(2, $job->currentStep);
        $this->assertEquals(1, $job->consecutiveFailures);
    }

    /** @test */
    public function canary_monitor_has_config(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        $this->assertStringContainsString('$tries = 1', $source);
        $this->assertStringContainsString('$timeout = 120', $source);
    }

    /** @test */
    public function canary_monitor_runs_on_deployments_queue(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        $this->assertStringContainsString("'deployments'", $source);
    }

    /** @test */
    public function canary_monitor_checks_both_containers_alive(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        $this->assertStringContainsString('bothContainersAlive', $source);
        $this->assertStringContainsString('docker ps --filter name=', $source);
    }

    /** @test */
    public function canary_monitor_checks_error_rate(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        $this->assertStringContainsString('checkCanaryErrorRate', $source);
        $this->assertStringContainsString('docker logs --since=5m', $source);
        $this->assertStringContainsString('5[0-9]{2}', $source);
    }

    /** @test */
    public function canary_monitor_uses_default_traffic_steps(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        // Default steps: [10, 25, 50, 100]
        $this->assertStringContainsString('10', $source);
        $this->assertStringContainsString('25', $source);
        $this->assertStringContainsString('50', $source);
        $this->assertStringContainsString('100', $source);
    }

    /** @test */
    public function canary_monitor_promotes_on_all_steps_passed(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        $this->assertStringContainsString('performPromote', $source);
        $this->assertStringContainsString('promote_canary', $source);
        $this->assertStringContainsString('all steps passed', $source);
    }

    /** @test */
    public function canary_monitor_performs_rollback_on_failure(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        $this->assertStringContainsString('performRollback', $source);
        $this->assertStringContainsString('rollback_canary', $source);
    }

    /** @test */
    public function canary_monitor_reschedules_self(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        $this->assertStringContainsString('reschedule', $source);
        $this->assertStringContainsString('MonitorCanaryDeploymentJob', $source);
    }

    /** @test */
    public function canary_monitor_has_global_timeout(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        $this->assertStringContainsString('canary_max_duration_minutes', $source);
        $this->assertStringContainsString('Canary global timeout reached', $source);
    }

    /** @test */
    public function canary_monitor_handles_missing_application(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        $this->assertStringContainsString('application not found, aborting', $source);
    }

    /** @test */
    public function canary_monitor_updates_traffic_weight(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        $this->assertStringContainsString('update_canary_traffic', $source);
    }

    /** @test */
    public function canary_monitor_failed_cleans_up(): void
    {
        $source = file_get_contents(app_path('Jobs/MonitorCanaryDeploymentJob.php'));

        $this->assertStringContainsString("setStatus('failed')", $source);
        $this->assertStringContainsString('canary_state', $source);
    }
}
