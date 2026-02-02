<?php

namespace App\Jobs;

use App\Events\DeploymentAnalysisCompleted;
use App\Models\ApplicationDeploymentQueue;
use App\Services\AI\DeploymentLogAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeDeploymentLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    public function __construct(
        public int $deploymentId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DeploymentLogAnalyzer $analyzer): void
    {
        if (! config('ai.enabled', true)) {
            Log::debug('AI analysis is disabled, skipping', ['deployment_id' => $this->deploymentId]);

            return;
        }

        $deployment = ApplicationDeploymentQueue::find($this->deploymentId);

        if ($deployment === null) {
            Log::warning('Deployment not found for AI analysis', ['deployment_id' => $this->deploymentId]);

            return;
        }

        // Only analyze failed deployments
        if ($deployment->status !== 'failed') {
            Log::debug('Skipping AI analysis for non-failed deployment', [
                'deployment_id' => $this->deploymentId,
                'status' => $deployment->status,
            ]);

            return;
        }

        // Check if logs exist
        if (empty($deployment->logs)) {
            Log::debug('No logs available for AI analysis', ['deployment_id' => $this->deploymentId]);

            return;
        }

        if (! $analyzer->isAvailable()) {
            Log::warning('No AI provider available for analysis', ['deployment_id' => $this->deploymentId]);

            return;
        }

        Log::info('Starting AI analysis for deployment', ['deployment_id' => $this->deploymentId]);

        $analysis = $analyzer->analyzeAndSave($deployment);

        // Broadcast completion event
        if ($analysis->isCompleted()) {
            event(new DeploymentAnalysisCompleted($deployment, $analysis));
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['ai-analysis', 'deployment:'.$this->deploymentId];
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AI analysis job failed', [
            'deployment_id' => $this->deploymentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
