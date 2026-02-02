<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeDeploymentLogsJob;
use App\Models\ApplicationDeploymentQueue;
use App\Models\DeploymentLogAnalysis;
use App\Services\AI\DeploymentLogAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeploymentAnalysisController extends Controller
{
    /**
     * Get analysis for a deployment.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->firstOrFail();

        // Check authorization
        if (! Gate::allows('view', $deployment->application)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $analysis = $deployment->logAnalysis;

        if ($analysis === null) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No analysis available for this deployment',
            ], 404);
        }

        return response()->json([
            'status' => $analysis->status,
            'analysis' => $this->formatAnalysis($analysis),
        ]);
    }

    /**
     * Trigger analysis for a deployment.
     */
    public function analyze(Request $request, string $uuid, DeploymentLogAnalyzer $analyzer): JsonResponse
    {
        if (! config('ai.enabled', true)) {
            return response()->json([
                'error' => 'AI analysis is disabled',
                'hint' => 'Enable AI analysis by setting AI_ANALYSIS_ENABLED=true',
            ], 503);
        }

        if (! $analyzer->isAvailable()) {
            return response()->json([
                'error' => 'No AI provider available',
                'hint' => 'Configure at least one AI provider (ANTHROPIC_API_KEY, OPENAI_API_KEY, or Ollama)',
            ], 503);
        }

        $deployment = ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->firstOrFail();

        // Check authorization
        if (! Gate::allows('update', $deployment->application)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if already analyzing
        $existingAnalysis = $deployment->logAnalysis;
        if ($existingAnalysis?->isAnalyzing()) {
            return response()->json([
                'status' => 'analyzing',
                'message' => 'Analysis is already in progress',
            ]);
        }

        // Dispatch job
        AnalyzeDeploymentLogsJob::dispatch($deployment->id);

        return response()->json([
            'status' => 'queued',
            'message' => 'Analysis has been queued',
        ]);
    }

    /**
     * Check AI service status.
     */
    public function status(DeploymentLogAnalyzer $analyzer): JsonResponse
    {
        $isEnabled = config('ai.enabled', true);
        $isAvailable = $analyzer->isAvailable();
        $provider = $analyzer->getAvailableProvider();

        return response()->json([
            'enabled' => $isEnabled,
            'available' => $isAvailable,
            'provider' => $provider?->getName(),
            'model' => $provider?->getModel(),
        ]);
    }

    /**
     * Format analysis for API response.
     */
    private function formatAnalysis(DeploymentLogAnalysis $analysis): array
    {
        return [
            'id' => $analysis->id,
            'root_cause' => $analysis->root_cause,
            'root_cause_details' => $analysis->root_cause_details,
            'solution' => $analysis->solution,
            'prevention' => $analysis->prevention,
            'error_category' => $analysis->error_category,
            'category_label' => $analysis->category_label,
            'severity' => $analysis->severity,
            'severity_color' => $analysis->severity_color,
            'confidence' => $analysis->confidence,
            'confidence_percent' => round($analysis->confidence * 100),
            'provider' => $analysis->provider,
            'model' => $analysis->model,
            'tokens_used' => $analysis->tokens_used,
            'status' => $analysis->status,
            'error_message' => $analysis->error_message,
            'created_at' => $analysis->created_at->toISOString(),
            'updated_at' => $analysis->updated_at->toISOString(),
        ];
    }
}
