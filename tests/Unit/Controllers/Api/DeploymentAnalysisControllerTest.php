<?php

/**
 * Unit tests for DeploymentAnalysisController.
 *
 * Tests cover:
 * - Class structure (methods exist)
 * - analyze() early exits (AI disabled → 503 before any DB access)
 * - formatAnalysis() private method returns correct structure
 */

use App\Http\Controllers\Api\DeploymentAnalysisController;
use App\Models\DeploymentLogAnalysis;
use App\Services\AI\DeploymentLogAnalyzer;
use Carbon\Carbon;
use Illuminate\Http\Request;

// ─── Class structure ──────────────────────────────────────────────────────────

test('DeploymentAnalysisController class exists', function () {
    expect(class_exists(DeploymentAnalysisController::class))->toBeTrue();
});

test('DeploymentAnalysisController has show method', function () {
    expect(method_exists(DeploymentAnalysisController::class, 'show'))->toBeTrue();
});

test('DeploymentAnalysisController has analyze method', function () {
    expect(method_exists(DeploymentAnalysisController::class, 'analyze'))->toBeTrue();
});

test('DeploymentAnalysisController has status method', function () {
    expect(method_exists(DeploymentAnalysisController::class, 'status'))->toBeTrue();
});

// ─── analyze() early exits (no DB access needed) ─────────────────────────────

test('analyze returns 503 when AI analysis is disabled', function () {
    config(['ai.enabled' => false]);

    // Real analyzer instance — safe because analyze() returns before calling isAvailable()
    $analyzer = new DeploymentLogAnalyzer;
    $controller = new DeploymentAnalysisController;
    $request = Request::create('/api/v1/deployment/test/analyze', 'POST');

    $response = $controller->analyze($request, 'some-uuid', $analyzer);

    expect($response->getStatusCode())->toBe(503);
    $data = json_decode($response->getContent(), true);
    expect($data['error'])->toBe('AI analysis is disabled');
    expect($data)->toHaveKey('hint');
});

test('analyze error response has hint field when AI disabled', function () {
    config(['ai.enabled' => false]);

    $analyzer = new DeploymentLogAnalyzer;
    $controller = new DeploymentAnalysisController;
    $request = Request::create('/api/v1/deployment/test/analyze', 'POST');

    $response = $controller->analyze($request, 'test-uuid', $analyzer);
    $data = json_decode($response->getContent(), true);

    expect($data['hint'])->toContain('AI_ANALYSIS_ENABLED');
});

// ─── formatAnalysis() private method via reflection ──────────────────────────

test('formatAnalysis returns all required keys', function () {
    $now = Carbon::now()->toDateTimeString();

    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes([
        'id' => 42,
        'root_cause' => 'Memory limit exceeded',
        'root_cause_details' => 'Container was OOM killed',
        'solution' => 'Increase memory limit in settings',
        'prevention' => 'Add memory monitoring alerts',
        'error_category' => 'resource',
        'category_label' => 'Resource Limit',
        'severity' => 'high',
        'severity_color' => 'red',
        'confidence' => 0.9,
        'provider' => 'claude',
        'model' => 'claude-sonnet-4-6',
        'tokens_used' => 1500,
        'status' => 'completed',
        'error_message' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $method = new ReflectionMethod(DeploymentAnalysisController::class, 'formatAnalysis');
    $controller = new DeploymentAnalysisController;
    $result = $method->invoke($controller, $analysis);

    expect($result)->toHaveKeys([
        'id',
        'root_cause',
        'root_cause_details',
        'solution',
        'prevention',
        'error_category',
        'category_label',
        'severity',
        'severity_color',
        'confidence',
        'confidence_percent',
        'provider',
        'model',
        'tokens_used',
        'status',
        'error_message',
        'created_at',
        'updated_at',
    ]);
});

test('formatAnalysis calculates confidence_percent correctly', function () {
    $now = Carbon::now()->toDateTimeString();

    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes([
        'id' => 1,
        'confidence' => 0.75,
        'status' => 'completed',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $method = new ReflectionMethod(DeploymentAnalysisController::class, 'formatAnalysis');
    $controller = new DeploymentAnalysisController;
    $result = $method->invoke($controller, $analysis);

    expect($result['confidence'])->toBe(0.75);
    expect($result['confidence_percent'])->toEqual(75);
});

test('formatAnalysis returns correct field values', function () {
    $now = Carbon::now()->toDateTimeString();

    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes([
        'id' => 99,
        'root_cause' => 'Docker image not found',
        'solution' => 'Check image name and registry',
        'severity' => 'critical',
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'status' => 'failed',
        'error_message' => 'Provider unavailable',
        'confidence' => 0.0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $method = new ReflectionMethod(DeploymentAnalysisController::class, 'formatAnalysis');
    $controller = new DeploymentAnalysisController;
    $result = $method->invoke($controller, $analysis);

    expect($result['id'])->toBe(99);
    expect($result['root_cause'])->toBe('Docker image not found');
    expect($result['severity'])->toBe('critical');
    expect($result['provider'])->toBe('openai');
    expect($result['status'])->toBe('failed');
    expect($result['error_message'])->toBe('Provider unavailable');
    expect($result['confidence_percent'])->toEqual(0);
});
