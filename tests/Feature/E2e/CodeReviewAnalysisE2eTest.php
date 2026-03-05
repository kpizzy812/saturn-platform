<?php

/**
 * E2E Code Review & Deployment Analysis Tests
 *
 * Covers the full lifecycle of Code Review and Deployment Log Analysis
 * API endpoints, including authorization, cross-team isolation,
 * token ability enforcement, and edge cases.
 */

use App\Enums\ApplicationDeploymentStatus;
use App\Jobs\AnalyzeCodeReviewJob;
use App\Jobs\AnalyzeDeploymentLogsJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\CodeReview;
use App\Models\CodeReviewViolation;
use App\Models\DeploymentLogAnalysis;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use App\Services\AI\CodeReview\LLMEnricher;
use App\Services\AI\DeploymentLogAnalyzer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function crHeaders(string $bearerToken): array
{
    return [
        'Authorization' => 'Bearer '.$bearerToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

/**
 * Create a CodeReview record for the given deployment.
 */
function createCodeReview(
    int $deploymentId,
    int $applicationId,
    string $commitSha,
    string $status = 'completed',
    array $extra = [],
): CodeReview {
    return CodeReview::create(array_merge([
        'deployment_id' => $deploymentId,
        'application_id' => $applicationId,
        'commit_sha' => $commitSha,
        'status' => $status,
        'files_analyzed' => ['app/Models/User.php', 'config/app.php'],
        'violations_count' => 0,
        'critical_count' => 0,
        'llm_failed' => false,
        'cache_key' => hash('sha256', $commitSha.'_test'),
    ], $extra));
}

/**
 * Create a CodeReviewViolation record.
 */
function createViolation(int $codeReviewId, array $extra = []): CodeReviewViolation
{
    return CodeReviewViolation::create(array_merge([
        'code_review_id' => $codeReviewId,
        'rule_id' => 'SEC001',
        'source' => 'regex',
        'severity' => 'high',
        'confidence' => 1.0,
        'file_path' => 'config/database.php',
        'line_number' => 42,
        'message' => 'Hardcoded API key detected',
        'contains_secret' => false,
    ], $extra));
}

/**
 * Create a DeploymentLogAnalysis record.
 */
function createLogAnalysis(int $deploymentId, string $status = 'completed', array $extra = []): DeploymentLogAnalysis
{
    return DeploymentLogAnalysis::create(array_merge([
        'deployment_id' => $deploymentId,
        'root_cause' => 'Missing dependency in Dockerfile',
        'root_cause_details' => 'The package libpq-dev was not installed.',
        'solution' => ['Add libpq-dev to Dockerfile', 'Run composer install'],
        'prevention' => ['Use multi-stage builds', 'Pin dependency versions'],
        'error_category' => 'dockerfile',
        'severity' => 'high',
        'confidence' => 0.92,
        'provider' => 'claude',
        'model' => 'claude-sonnet-4-20250514',
        'tokens_used' => 1500,
        'error_hash' => hash('sha256', 'test_error_'.$deploymentId),
        'status' => $status,
    ], $extra));
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Queue::fake();
    Cache::flush();
    try {
        Cache::store('redis')->flush();
    } catch (\Throwable $e) {
    }

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! InstanceSettings::first()) {
        InstanceSettings::create(['id' => 0, 'is_api_enabled' => true]);
    }

    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);
    $this->server = Server::withoutEvents(fn () => Server::factory()->create([
        'uuid' => (string) new Cuid2,
        'team_id' => $this->team->id,
        'private_key_id' => $this->privateKey->id,
    ]));
    ServerSetting::firstOrCreate(['server_id' => $this->server->id]);

    $this->destination = StandaloneDocker::withoutEvents(fn () => StandaloneDocker::create([
        'uuid' => (string) new Cuid2,
        'name' => 'default',
        'network' => 'saturn',
        'server_id' => $this->server->id,
    ]));

    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);

    $this->application = Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'ports_exposes' => '3000',
    ]);

    $this->deployment = ApplicationDeploymentQueue::factory()->create([
        'application_id' => $this->application->id,
        'server_id' => $this->server->id,
        'status' => ApplicationDeploymentStatus::FINISHED->value,
        'deployment_uuid' => (string) new Cuid2,
        'commit' => 'abc123def456789012345678901234567890abcd',
    ]);
});

// ─── Code Review Lifecycle ───────────────────────────────────────────────────

describe('GET /api/v1/deployments/{uuid}/code-review — Code review lifecycle', function () {
    test('returns code review with violations when review exists', function () {
        $review = createCodeReview(
            $this->deployment->id,
            $this->application->id,
            $this->deployment->commit,
            'completed',
            ['violations_count' => 2, 'critical_count' => 1],
        );

        createViolation($review->id, [
            'severity' => 'critical',
            'rule_id' => 'SEC007',
            'message' => 'Private key in code',
        ]);
        createViolation($review->id, [
            'severity' => 'high',
            'rule_id' => 'SEC001',
            'message' => 'Hardcoded API key',
        ]);

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('review.commit_sha', $this->deployment->commit);
        $response->assertJsonPath('review.violations_count', 2);
        $response->assertJsonPath('review.critical_count', 1);
        $response->assertJsonPath('review.has_violations', true);
        $response->assertJsonPath('review.has_critical', true);
        $response->assertJsonStructure([
            'status',
            'review' => [
                'id', 'deployment_id', 'application_id', 'commit_sha',
                'status', 'status_label', 'status_color',
                'files_analyzed', 'files_count',
                'violations_count', 'critical_count',
                'has_violations', 'has_critical', 'violations_by_severity',
                'llm_provider', 'llm_model', 'llm_failed',
                'duration_ms', 'created_at', 'violations',
            ],
        ]);

        // Verify violations are included
        $violations = $response->json('review.violations');
        expect($violations)->toHaveCount(2);
    });

    test('finds code review by commit SHA when deployment_id does not match', function () {
        // Create review linked to a different deployment but same commit SHA
        $otherDeployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => (string) new Cuid2,
            'commit' => $this->deployment->commit,
        ]);

        createCodeReview(
            $otherDeployment->id,
            $this->application->id,
            $this->deployment->commit,
            'completed',
            ['violations_count' => 1],
        );

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('review.violations_count', 1);
    });
});

// ─── Trigger Code Review ─────────────────────────────────────────────────────

describe('POST /api/v1/deployments/{uuid}/code-review — Trigger code review', function () {
    test('queues code review job and returns queued status', function () {
        config()->set('ai.code_review.enabled', true);

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'queued');
        $response->assertJsonPath('message', 'Code review has been queued');

        Queue::assertPushed(AnalyzeCodeReviewJob::class, function ($job) {
            return true;
        });
    });

    test('returns 503 when code review is disabled', function () {
        config()->set('ai.code_review.enabled', false);

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review");

        $response->assertStatus(503);
        $response->assertJsonPath('error', 'Code review is disabled');
        $response->assertJsonStructure(['error', 'hint']);
    });

    test('returns 400 when deployment has no commit SHA', function () {
        config()->set('ai.code_review.enabled', true);

        // Create deployment without commit
        $noCommitDeployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => (string) new Cuid2,
            'commit' => null,
        ]);

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->postJson("/api/v1/deployments/{$noCommitDeployment->deployment_uuid}/code-review");

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'No commit SHA available');
    });

    test('returns analyzing status when review is already in progress', function () {
        config()->set('ai.code_review.enabled', true);

        createCodeReview(
            $this->deployment->id,
            $this->application->id,
            $this->deployment->commit,
            'analyzing',
        );

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'analyzing');
        $response->assertJsonPath('message', 'Code review is already in progress');

        Queue::assertNotPushed(AnalyzeCodeReviewJob::class);
    });

    test('returns completed status with existing review data', function () {
        config()->set('ai.code_review.enabled', true);

        $review = createCodeReview(
            $this->deployment->id,
            $this->application->id,
            $this->deployment->commit,
            'completed',
            ['violations_count' => 3],
        );

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('message', 'Code review already exists for this commit');
        $response->assertJsonPath('review.violations_count', 3);

        Queue::assertNotPushed(AnalyzeCodeReviewJob::class);
    });
});

// ─── Code Review Violations with Secret Filtering ────────────────────────────

describe('GET /api/v1/deployments/{uuid}/code-review/violations — Violations endpoint', function () {
    test('returns all violations for user with write access', function () {
        $review = createCodeReview(
            $this->deployment->id,
            $this->application->id,
            $this->deployment->commit,
            'completed',
            ['violations_count' => 3],
        );

        createViolation($review->id, [
            'severity' => 'critical',
            'contains_secret' => true,
            'message' => 'Hardcoded AWS secret key',
        ]);
        createViolation($review->id, [
            'severity' => 'high',
            'contains_secret' => false,
            'message' => 'Shell command injection risk',
        ]);
        createViolation($review->id, [
            'severity' => 'medium',
            'contains_secret' => false,
            'message' => 'Unsafe eval usage',
        ]);

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review/violations");

        $response->assertStatus(200);
        $response->assertJsonPath('total_count', 3);
        // Owner has update gate, so sees all including secrets
        $response->assertJsonPath('secrets_hidden', false);

        $violations = $response->json('violations');
        expect($violations)->toBeArray();
        expect(count($violations))->toBeGreaterThanOrEqual(3);
    });

    test('hides secret violations from read-only token user', function () {
        // Create a separate user with read-only access (member)
        $readUser = User::factory()->create();
        $this->team->members()->attach($readUser->id, ['role' => 'member']);
        $readToken = $readUser->createToken('read-only', ['read']);

        $review = createCodeReview(
            $this->deployment->id,
            $this->application->id,
            $this->deployment->commit,
            'completed',
            ['violations_count' => 2],
        );

        createViolation($review->id, [
            'severity' => 'critical',
            'contains_secret' => true,
            'message' => 'Exposed AWS credentials',
        ]);
        createViolation($review->id, [
            'severity' => 'high',
            'contains_secret' => false,
            'message' => 'Dangerous function call',
        ]);

        $response = $this->withHeaders(crHeaders($readToken->plainTextToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review/violations");

        $response->assertStatus(200);
        $response->assertJsonPath('total_count', 2);
        $response->assertJsonPath('secrets_hidden', true);

        // Only non-secret violation visible
        $violations = $response->json('violations');
        expect($violations)->toHaveCount(1);
        expect($violations[0]['message'])->toBe('Dangerous function call');
    });

    test('returns 404 when no review exists for violations or code-review endpoint', function () {
        // Violations endpoint
        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review/violations");
        $response->assertStatus(404);
        $response->assertJsonPath('status', 'not_found');

        // Code review endpoint (same deployment, no review)
        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review");
        $response->assertStatus(404);
        $response->assertJsonPath('message', 'No code review available for this deployment');
    });
});

// ─── Code Review Service Status ──────────────────────────────────────────────

describe('GET /api/v1/code-review/status — Code review service status', function () {
    test('returns service configuration reflecting current state', function () {
        config()->set('ai.code_review.enabled', true);
        config()->set('ai.code_review.mode', 'report_only');
        config()->set('ai.code_review.detectors.secrets', true);
        config()->set('ai.code_review.detectors.dangerous_functions', true);
        config()->set('ai.code_review.llm_enrichment', true);

        // Mock LLMEnricher since no real API keys available in test
        $this->mock(LLMEnricher::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->once()->andReturn(false);
            $mock->shouldReceive('getProviderInfo')->once()->andReturn([
                'provider' => null,
                'model' => null,
            ]);
        });

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson('/api/v1/code-review/status');

        $response->assertStatus(200);
        $response->assertJsonPath('enabled', true);
        $response->assertJsonPath('mode', 'report_only');
        $response->assertJsonStructure([
            'enabled', 'mode',
            'detectors' => ['secrets', 'dangerous_functions'],
            'llm' => ['enabled', 'available', 'provider', 'model'],
        ]);
    });
});

// ─── Deployment Analysis Lifecycle ───────────────────────────────────────────

describe('GET /api/v1/deployments/{uuid}/analysis — Deployment analysis lifecycle', function () {
    test('returns completed analysis data', function () {
        $analysis = createLogAnalysis($this->deployment->id);

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/analysis");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'completed');
        $response->assertJsonPath('analysis.root_cause', 'Missing dependency in Dockerfile');
        $response->assertJsonPath('analysis.error_category', 'dockerfile');
        $response->assertJsonPath('analysis.severity', 'high');
        $response->assertJsonPath('analysis.provider', 'claude');
        $response->assertJsonStructure([
            'status',
            'analysis' => [
                'id', 'root_cause', 'root_cause_details',
                'solution', 'prevention',
                'error_category', 'category_label',
                'severity', 'severity_color',
                'confidence', 'confidence_percent',
                'provider', 'model', 'tokens_used',
                'status', 'created_at', 'updated_at',
            ],
        ]);

        // Verify array fields are arrays
        $solution = $response->json('analysis.solution');
        expect($solution)->toBeArray();
        expect($solution)->toContain('Add libpq-dev to Dockerfile');
    });

    test('returns 404 when no analysis exists for deployment', function () {
        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/analysis");

        $response->assertStatus(404);
        $response->assertJsonPath('status', 'not_found');
        $response->assertJsonPath('message', 'No analysis available for this deployment');
    });
});

// ─── Trigger Deployment Analysis ─────────────────────────────────────────────

describe('POST /api/v1/deployments/{uuid}/analyze — Trigger deployment analysis', function () {
    test('queues analysis job and returns queued status', function () {
        config()->set('ai.enabled', true);

        // Mock the analyzer to indicate availability
        $this->mock(DeploymentLogAnalyzer::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        });

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/analyze");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'queued');
        $response->assertJsonPath('message', 'Analysis has been queued');

        Queue::assertPushed(AnalyzeDeploymentLogsJob::class);
    });

    test('returns 503 when AI analysis is disabled or no provider available', function () {
        // Test disabled
        config()->set('ai.enabled', false);

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/analyze");

        $response->assertStatus(503);
        $response->assertJsonPath('error', 'AI analysis is disabled');

        // Test no provider
        config()->set('ai.enabled', true);

        $this->mock(DeploymentLogAnalyzer::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->once()->andReturn(false);
        });

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/analyze");

        $response->assertStatus(503);
        $response->assertJsonPath('error', 'No AI provider available');
    });

    test('returns analyzing status when analysis is already in progress', function () {
        config()->set('ai.enabled', true);

        $this->mock(DeploymentLogAnalyzer::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->once()->andReturn(true);
        });

        createLogAnalysis($this->deployment->id, 'analyzing');

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/analyze");

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'analyzing');
        $response->assertJsonPath('message', 'Analysis is already in progress');

        Queue::assertNotPushed(AnalyzeDeploymentLogsJob::class);
    });
});

// ─── AI Service Status ───────────────────────────────────────────────────────

describe('GET /api/v1/ai/status — AI service status', function () {
    test('returns AI service configuration reflecting enabled state', function () {
        config()->set('ai.enabled', true);

        $this->mock(DeploymentLogAnalyzer::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->once()->andReturn(false);
            $mock->shouldReceive('getAvailableProvider')->once()->andReturn(null);
        });

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson('/api/v1/ai/status');

        $response->assertStatus(200);
        $response->assertJsonPath('enabled', true);
        $response->assertJsonPath('available', false);
        $response->assertJsonPath('provider', null);
        $response->assertJsonPath('model', null);
        $response->assertJsonStructure([
            'enabled', 'available', 'provider', 'model',
        ]);
    });
});

// ─── Cross-Team Isolation ────────────────────────────────────────────────────

describe('Cross-team isolation', function () {
    test('cannot view code review from another team deployment', function () {
        // Setup another team
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);
        $otherDeployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $otherApp->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => (string) new Cuid2,
            'commit' => 'other_commit_sha_1234567890abcdef1234',
        ]);

        createCodeReview(
            $otherDeployment->id,
            $otherApp->id,
            $otherDeployment->commit,
            'completed',
        );

        // Current user tries to access other team's review
        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$otherDeployment->deployment_uuid}/code-review");

        $response->assertStatus(404);
    });

    test('cannot view deployment analysis from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);
        $otherDeployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $otherApp->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => (string) new Cuid2,
            'commit' => 'other_commit_sha_1234567890abcdef1234',
        ]);

        createLogAnalysis($otherDeployment->id);

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$otherDeployment->deployment_uuid}/analysis");

        // DeploymentAnalysisController uses firstOrFail + Gate::allows('view'), returns 403
        $response->assertStatus(403);
    });

    test('cannot trigger code review for another team deployment', function () {
        config()->set('ai.code_review.enabled', true);

        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);
        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnv->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);
        $otherDeployment = ApplicationDeploymentQueue::factory()->create([
            'application_id' => $otherApp->id,
            'server_id' => $this->server->id,
            'status' => ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => (string) new Cuid2,
            'commit' => 'other_commit_sha_1234567890abcdef1234',
        ]);

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->postJson("/api/v1/deployments/{$otherDeployment->deployment_uuid}/code-review");

        $response->assertStatus(404);

        Queue::assertNotPushed(AnalyzeCodeReviewJob::class);
    });
});

// ─── Token Ability Enforcement ───────────────────────────────────────────────

describe('Token ability enforcement', function () {
    test('read-only token can GET code review and analysis but cannot POST triggers', function () {
        config()->set('ai.code_review.enabled', true);
        config()->set('ai.enabled', true);

        $readToken = $this->user->createToken('read-only', ['read']);

        // Setup data for GET assertions
        createCodeReview(
            $this->deployment->id,
            $this->application->id,
            $this->deployment->commit,
            'completed',
        );
        createLogAnalysis($this->deployment->id);

        // GET code review — allowed
        $response = $this->withHeaders(crHeaders($readToken->plainTextToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review");
        $response->assertStatus(200);
        $response->assertJsonPath('status', 'completed');

        // GET analysis — allowed
        $response = $this->withHeaders(crHeaders($readToken->plainTextToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/analysis");
        $response->assertStatus(200);
        $response->assertJsonPath('status', 'completed');

        // POST trigger code review — forbidden
        $response = $this->withHeaders(crHeaders($readToken->plainTextToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review");
        $response->assertStatus(403);

        // POST trigger analysis — forbidden
        $response = $this->withHeaders(crHeaders($readToken->plainTextToken))
            ->postJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/analyze");
        $response->assertStatus(403);
    });

    test('read-only token can GET status endpoints', function () {
        $readToken = $this->user->createToken('read-only', ['read']);

        $this->mock(DeploymentLogAnalyzer::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->once()->andReturn(false);
            $mock->shouldReceive('getAvailableProvider')->once()->andReturn(null);
        });

        $response = $this->withHeaders(crHeaders($readToken->plainTextToken))
            ->getJson('/api/v1/ai/status');
        $response->assertStatus(200);
        $response->assertJsonStructure(['enabled', 'available', 'provider', 'model']);
    });
});

// ─── Edge Cases ──────────────────────────────────────────────────────────────

describe('Edge cases and error handling', function () {
    test('returns 404 for non-existent deployment UUID on both code review and analysis', function () {
        $fakeUuid = 'non-existent-uuid-12345';

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$fakeUuid}/code-review");
        $response->assertStatus(404);

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$fakeUuid}/analysis");
        $response->assertStatus(404);
    });

    test('violation response includes structured fields', function () {
        $review = createCodeReview(
            $this->deployment->id,
            $this->application->id,
            $this->deployment->commit,
            'completed',
            ['violations_count' => 1],
        );

        createViolation($review->id, [
            'rule_id' => 'SEC004',
            'severity' => 'high',
            'source' => 'regex',
            'file_path' => 'app/Http/Controllers/ExecController.php',
            'line_number' => 55,
            'message' => 'Shell command execution detected',
            'snippet' => 'exec($command)',
            'suggestion' => 'Use Process facade instead',
            'confidence' => 0.95,
            'contains_secret' => false,
        ]);

        $response = $this->withHeaders(crHeaders($this->bearerToken))
            ->getJson("/api/v1/deployments/{$this->deployment->deployment_uuid}/code-review/violations");

        $response->assertStatus(200);

        $violation = $response->json('violations.0');
        expect($violation)->toHaveKeys([
            'id', 'rule_id', 'rule_description', 'rule_category',
            'source', 'severity', 'severity_color', 'confidence',
            'file_path', 'line_number', 'location', 'message',
            'snippet', 'suggestion', 'contains_secret', 'is_deterministic',
            'created_at',
        ]);
        expect($violation['rule_id'])->toBe('SEC004');
        expect($violation['rule_description'])->toBe('Shell Command Execution');
        expect($violation['rule_category'])->toBe('Security');
        expect($violation['severity_color'])->toBe('orange');
        expect($violation['is_deterministic'])->toBeTrue();
        expect($violation['location'])->toBe('app/Http/Controllers/ExecController.php:55');
    });
});
