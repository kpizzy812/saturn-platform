<?php

namespace App\Jobs;

use App\Events\DeploymentAnalysisCompleted;
use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentLogAnalysis;
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
    public int $tries = 1;

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
            $this->saveFailedAnalysis('AI analysis is disabled');

            return;
        }

        $deployment = ApplicationDeploymentQueue::find($this->deploymentId);

        if ($deployment === null) {
            Log::warning('Deployment not found for AI analysis', ['deployment_id' => $this->deploymentId]);

            return;
        }

        // Only analyze failed deployments
        if ($deployment->status !== 'failed') {
            $this->saveFailedAnalysis(
                "Cannot analyze: deployment status is '{$deployment->status}', not 'failed'",
                $deployment->id
            );

            return;
        }

        // Check if logs exist
        if (empty($deployment->logs)) {
            $this->saveFailedAnalysis(
                'No deployment logs available to analyze. The deployment may have failed before generating any output.',
                $deployment->id
            );

            return;
        }

        if (! $analyzer->isAvailable()) {
            $this->saveFailedAnalysis(
                'No AI provider available. Configure ANTHROPIC_API_KEY, OPENAI_API_KEY, or Ollama.',
                $deployment->id
            );

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
     * Save a failed analysis record so the frontend stops polling.
     */
    private function saveFailedAnalysis(string $message, ?int $deploymentId = null): void
    {
        $id = $deploymentId ?? $this->deploymentId;

        DeploymentLogAnalysis::updateOrCreate(
            ['deployment_id' => $id],
            [
                'status' => 'failed',
                'error_message' => $message,
            ]
        );

        Log::debug('AI analysis skipped', ['deployment_id' => $id, 'reason' => $message]);
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

        $this->saveFailedAnalysis('Analysis job failed: '.$exception->getMessage());
    }
}
