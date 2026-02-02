<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeCodeReviewJob;
use App\Models\ApplicationDeploymentQueue;
use App\Models\CodeReview;
use App\Services\AI\CodeReview\LLMEnricher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * API controller for code review functionality.
 *
 * Provides endpoints for viewing and triggering code reviews on deployments.
 */
class CodeReviewController extends Controller
{
    /**
     * Get code review for a deployment.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->firstOrFail();

        // Check authorization
        if (! Gate::allows('view', $deployment->application)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $review = CodeReview::where('deployment_id', $deployment->id)
            ->with('violations')
            ->first();

        if ($review === null) {
            // Try to find by commit SHA (different deployment, same commit)
            $review = CodeReview::where('application_id', $deployment->application_id)
                ->where('commit_sha', $deployment->commit)
                ->with('violations')
                ->first();
        }

        if ($review === null) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No code review available for this deployment',
            ], 404);
        }

        return response()->json([
            'status' => $review->status,
            'review' => $this->formatReview($review),
        ]);
    }

    /**
     * Trigger code review for a deployment.
     */
    public function trigger(Request $request, string $uuid): JsonResponse
    {
        if (! config('ai.code_review.enabled', false)) {
            return response()->json([
                'error' => 'Code review is disabled',
                'hint' => 'Enable code review by setting AI_CODE_REVIEW_ENABLED=true',
            ], 503);
        }

        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->firstOrFail();

        // Check authorization
        if (! Gate::allows('update', $deployment->application)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if commit exists
        if (empty($deployment->commit)) {
            return response()->json([
                'error' => 'No commit SHA available',
                'hint' => 'This deployment does not have a commit SHA associated with it',
            ], 400);
        }

        // Check if already analyzing
        $existingReview = CodeReview::where('application_id', $deployment->application_id)
            ->where('commit_sha', $deployment->commit)
            ->first();

        if ($existingReview?->isAnalyzing()) {
            return response()->json([
                'status' => 'analyzing',
                'message' => 'Code review is already in progress',
            ]);
        }

        if ($existingReview?->isCompleted()) {
            return response()->json([
                'status' => 'completed',
                'message' => 'Code review already exists for this commit',
                'review' => $this->formatReview($existingReview->load('violations')),
            ]);
        }

        // Dispatch job
        AnalyzeCodeReviewJob::dispatch($deployment->id);

        return response()->json([
            'status' => 'queued',
            'message' => 'Code review has been queued',
        ]);
    }

    /**
     * Get violations for a code review.
     */
    public function violations(Request $request, string $uuid): JsonResponse
    {
        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->firstOrFail();

        // Check authorization
        if (! Gate::allows('view', $deployment->application)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $review = CodeReview::where('deployment_id', $deployment->id)
            ->orWhere(function ($query) use ($deployment) {
                $query->where('application_id', $deployment->application_id)
                    ->where('commit_sha', $deployment->commit);
            })
            ->first();

        if ($review === null) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No code review available for this deployment',
            ], 404);
        }

        // Determine if user can see secret violations
        $canViewSecrets = Gate::allows('update', $deployment->application);

        $violations = $review->violations()
            ->when(! $canViewSecrets, function ($query) {
                $query->where('contains_secret', false);
            })
            ->orderBy('severity')
            ->orderBy('file_path')
            ->get();

        return response()->json([
            'violations' => $violations->map(fn ($v) => $this->formatViolation($v)),
            'total_count' => $review->violations_count,
            'visible_count' => $violations->count(),
            'secrets_hidden' => ! $canViewSecrets && $review->violations()->where('contains_secret', true)->exists(),
        ]);
    }

    /**
     * Check code review service status.
     */
    public function status(LLMEnricher $enricher): JsonResponse
    {
        $isEnabled = config('ai.code_review.enabled', false);
        $mode = config('ai.code_review.mode', 'report_only');
        $llmAvailable = $enricher->isAvailable();
        $llmInfo = $enricher->getProviderInfo();

        return response()->json([
            'enabled' => $isEnabled,
            'mode' => $mode,
            'detectors' => [
                'secrets' => config('ai.code_review.detectors.secrets', true),
                'dangerous_functions' => config('ai.code_review.detectors.dangerous_functions', true),
            ],
            'llm' => [
                'enabled' => config('ai.code_review.llm_enrichment', true),
                'available' => $llmAvailable,
                'provider' => $llmInfo['provider'],
                'model' => $llmInfo['model'],
            ],
        ]);
    }

    /**
     * Format code review for API response.
     */
    private function formatReview(CodeReview $review): array
    {
        return [
            'id' => $review->id,
            'deployment_id' => $review->deployment_id,
            'application_id' => $review->application_id,
            'commit_sha' => $review->commit_sha,
            'base_commit_sha' => $review->base_commit_sha,
            'status' => $review->status,
            'status_label' => $review->status_label,
            'status_color' => $review->status_color,
            'files_analyzed' => $review->files_analyzed ?? [],
            'files_count' => count($review->files_analyzed ?? []),
            'violations_count' => $review->violations_count,
            'critical_count' => $review->critical_count,
            'has_violations' => $review->hasViolations(),
            'has_critical' => $review->hasCriticalViolations(),
            'violations_by_severity' => $review->getViolationsBySeverity(),
            'llm_provider' => $review->llm_provider,
            'llm_model' => $review->llm_model,
            'llm_failed' => $review->llm_failed,
            'duration_ms' => $review->duration_ms,
            'started_at' => $review->started_at?->toISOString(),
            'finished_at' => $review->finished_at?->toISOString(),
            'error_message' => $review->error_message,
            'created_at' => $review->created_at->toISOString(),
            'violations' => $review->relationLoaded('violations')
                ? $review->violations->map(fn ($v) => $this->formatViolation($v))
                : null,
        ];
    }

    /**
     * Format violation for API response.
     */
    private function formatViolation($violation): array
    {
        return [
            'id' => $violation->id,
            'rule_id' => $violation->rule_id,
            'rule_description' => $violation->rule_description,
            'rule_category' => $violation->rule_category,
            'source' => $violation->source,
            'severity' => $violation->severity,
            'severity_color' => $violation->severity_color,
            'confidence' => $violation->confidence,
            'file_path' => $violation->file_path,
            'line_number' => $violation->line_number,
            'location' => $violation->location,
            'message' => $violation->message,
            'snippet' => $violation->snippet,
            'suggestion' => $violation->suggestion,
            'contains_secret' => $violation->contains_secret,
            'is_deterministic' => $violation->isDeterministic(),
            'created_at' => $violation->created_at->toISOString(),
        ];
    }
}
