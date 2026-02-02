<?php

namespace App\Jobs;

use App\Events\CodeReviewCompleted;
use App\Models\ApplicationDeploymentQueue;
use App\Models\CodeReview;
use App\Services\AI\CodeReview\Detectors\DangerousFunctionsDetector;
use App\Services\AI\CodeReview\Detectors\SecretsDetector;
use App\Services\AI\CodeReview\GitDiffFetcher;
use App\Services\AI\CodeReview\LLMEnricher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Analyzes code changes for security vulnerabilities and code quality issues.
 *
 * This job orchestrates the code review process:
 * 1. Fetches diff from GitHub
 * 2. Runs deterministic detectors (regex-based)
 * 3. Optionally enriches findings with LLM explanations
 * 4. Stores results (no raw diff stored for security)
 * 5. Broadcasts completion event
 */
class AnalyzeCodeReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 180;

    /**
     * The number of seconds to wait before retrying.
     *
     * @var int[]
     */
    public array $backoff = [30, 60];

    public function __construct(
        public int $deploymentId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        GitDiffFetcher $diffFetcher,
        SecretsDetector $secretsDetector,
        DangerousFunctionsDetector $dangerousDetector,
        LLMEnricher $llmEnricher
    ): void {
        if (! config('ai.code_review.enabled', false)) {
            Log::debug('Code review is disabled', ['deployment_id' => $this->deploymentId]);

            return;
        }

        $deployment = ApplicationDeploymentQueue::find($this->deploymentId);

        if ($deployment === null) {
            Log::warning('Deployment not found for code review', ['deployment_id' => $this->deploymentId]);

            return;
        }

        // Check if commit exists
        if (empty($deployment->commit)) {
            Log::debug('No commit SHA available for code review', ['deployment_id' => $this->deploymentId]);

            return;
        }

        $application = $deployment->application;
        if ($application === null) {
            Log::warning('Application not found for code review', ['deployment_id' => $this->deploymentId]);

            return;
        }

        // Compute cache key early
        $cacheKey = $this->computeCacheKey($deployment->commit);

        // Check for existing completed review (idempotency)
        $existing = CodeReview::where('cache_key', $cacheKey)
            ->where('application_id', $application->id)
            ->completed()
            ->first();

        if ($existing !== null) {
            Log::info('Code review cache hit', [
                'cache_key' => $cacheKey,
                'deployment_id' => $this->deploymentId,
            ]);

            // Link existing review to this deployment if different
            if ($existing->deployment_id !== $deployment->id) {
                // Don't update deployment_id, just use existing review
            }

            return;
        }

        // Create or update review record
        $review = CodeReview::updateOrCreate(
            [
                'application_id' => $application->id,
                'commit_sha' => $deployment->commit,
            ],
            [
                'deployment_id' => $deployment->id,
                'cache_key' => $cacheKey,
            ]
        );

        $review->markAsAnalyzing();

        try {
            // Fetch diff from GitHub
            Log::info('Fetching diff for code review', [
                'deployment_id' => $this->deploymentId,
                'commit' => $deployment->commit,
            ]);

            $diff = $diffFetcher->fetch($application, $deployment->commit);

            if ($diff->isEmpty()) {
                Log::info('Empty diff, skipping code review', ['deployment_id' => $this->deploymentId]);
                $review->markAsCompleted([
                    'files_analyzed' => [],
                    'violations_count' => 0,
                    'critical_count' => 0,
                ]);

                return;
            }

            // Check diff size limits
            if (! $diff->isWithinLimits()) {
                Log::warning('Diff exceeds size limit, truncating analysis', [
                    'deployment_id' => $this->deploymentId,
                    'total_changes' => $diff->getTotalChanges(),
                ]);
            }

            // Run deterministic detectors
            $violations = collect();

            if ($secretsDetector->isEnabled()) {
                $secretsViolations = $secretsDetector->detect($diff);
                $violations = $violations->merge($secretsViolations);
                Log::debug('Secrets detector found violations', ['count' => $secretsViolations->count()]);
            }

            if ($dangerousDetector->isEnabled()) {
                $dangerousViolations = $dangerousDetector->detect($diff);
                $violations = $violations->merge($dangerousViolations);
                Log::debug('Dangerous functions detector found violations', ['count' => $dangerousViolations->count()]);
            }

            // LLM enrichment (optional, for explanations only)
            $llmFailed = false;
            $llmProvider = null;
            $llmModel = null;
            $llmTokensUsed = null;

            if ($violations->isNotEmpty() && config('ai.code_review.llm_enrichment', true) && $llmEnricher->isAvailable()) {
                try {
                    Log::info('Enriching violations with LLM', ['count' => $violations->count()]);
                    $violations = $llmEnricher->enrich($violations, $diff);

                    $providerInfo = $llmEnricher->getProviderInfo();
                    $llmProvider = $providerInfo['provider'];
                    $llmModel = $providerInfo['model'];
                } catch (\Throwable $e) {
                    Log::warning('LLM enrichment failed, continuing without it', [
                        'error' => $e->getMessage(),
                    ]);
                    $llmFailed = true;
                }
            }

            // Save violations
            foreach ($violations as $violation) {
                $review->violations()->create($violation->toArray());
            }

            // Complete review
            $review->markAsCompleted([
                'files_analyzed' => $diff->getFilePaths(),
                'violations_count' => $violations->count(),
                'critical_count' => $violations->where('severity', 'critical')->count(),
                'llm_provider' => $llmProvider,
                'llm_model' => $llmModel,
                'llm_tokens_used' => $llmTokensUsed,
                'llm_failed' => $llmFailed,
            ]);

            Log::info('Code review completed', [
                'deployment_id' => $this->deploymentId,
                'violations' => $violations->count(),
                'critical' => $violations->where('severity', 'critical')->count(),
            ]);

            // Broadcast completion event
            event(new CodeReviewCompleted($deployment, $review));

        } catch (\Throwable $e) {
            Log::error('Code review failed', [
                'deployment_id' => $this->deploymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $review->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Compute cache key for idempotency.
     */
    private function computeCacheKey(string $commitSha): string
    {
        return hash('sha256', implode('|', [
            $commitSha,
            config('ai.code_review.detectors_version', '1.0.0'),
        ]));
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['code-review', 'deployment:'.$this->deploymentId];
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Code review job failed', [
            'deployment_id' => $this->deploymentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
