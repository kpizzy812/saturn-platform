<?php

/**
 * E2E Integration Tests — Webhook → Deployment
 *
 * Tests the full HTTP path from an incoming webhook (GitHub / GitLab)
 * through signature/token validation, application lookup, server
 * functional check, and deployment queuing.
 *
 * All tests run against a real test database (rolled back per test).
 * The queue is faked so no SSH connections are attempted.
 */

use App\Jobs\ApplicationDeploymentJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Environment;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

// ─── Setup ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    Queue::fake();
    $this->withoutMiddleware(ThrottleRequests::class);

    if (! DB::table('instance_settings')->where('id', 0)->exists()) {
        DB::table('instance_settings')->insert([
            'id' => 0,
            'is_api_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    $this->privateKey = PrivateKey::factory()->create(['team_id' => $this->team->id]);

    // Server must not have ip=1.2.3.4 and must have is_reachable/is_usable=true
    // so that isFunctional() returns true during webhook processing.
    $this->server = Server::withoutEvents(fn () => Server::factory()->create([
        'uuid' => (string) new Cuid2,
        'team_id' => $this->team->id,
        'private_key_id' => $this->privateKey->id,
        'ip' => '10.0.0.1',
    ]));
    $setting = ServerSetting::firstOrCreate(['server_id' => $this->server->id]);
    // is_reachable / is_usable are excluded from $fillable (system-managed),
    // so we bypass mass-assignment protection with forceFill().
    $setting->forceFill(['is_reachable' => true, 'is_usable' => true, 'force_disabled' => false])->save();

    $this->destination = StandaloneDocker::withoutEvents(fn () => StandaloneDocker::create([
        'uuid' => (string) new Cuid2,
        'name' => 'default',
        'network' => 'saturn',
        'server_id' => $this->server->id,
    ]));

    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);

    $this->webhookSecret = 'super-secret-test-key-e2e';
    $this->gitlabToken = 'gitlab-secret-token-e2e';

    $this->application = Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        // Must contain 'myorg/myrepo' so LIKE '%myorg/myrepo%' matches
        'git_repository' => 'github.com/myorg/myrepo',
        'git_branch' => 'main',
        'manual_webhook_secret_github' => $this->webhookSecret,
        'manual_webhook_secret_gitlab' => $this->gitlabToken,
        'ports_exposes' => '3000',
        'watch_paths' => null,
    ]);

    // Enable auto-deploy so isDeployable() returns true
    $this->application->settings()->update(['is_auto_deploy_enabled' => true]);
});

// ─── GitHub Webhook ───────────────────────────────────────────────────────────

describe('GitHub webhook → deploy', function () {
    test('valid HMAC signature triggers deployment and creates DB record', function () {
        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'deadbeef1234',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-uuid-1',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();

        // Deployment must be queued in DB with correct metadata
        $this->assertDatabaseHas('application_deployment_queues', [
            'application_id' => $this->application->id,
            'is_webhook' => true,
            'triggered_by' => 'webhook',
            'commit' => 'deadbeef1234',
        ]);

        // Job must have been dispatched
        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('valid HMAC webhook response contains success status', function () {
        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'cafebabeaabb',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-uuid-2',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);

        $match = collect($data)->first(fn ($item) => ($item['status'] ?? '') === 'success');
        expect($match)->not->toBeNull();
        expect($match['message'])->toBe('Deployment queued.');
        expect($match)->toHaveKey('deployment_uuid');
        expect($match)->toHaveKey('application_uuid');
    });

    test('invalid HMAC signature rejects deployment — no DB record created', function () {
        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'badcoffee1234',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [],
        ];

        $json = json_encode($payload);
        // Deliberately wrong secret
        $sig = hash_hmac('sha256', $json, 'wrong-secret');

        $before = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-uuid-3',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $after = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        // No new deployment should be created
        expect($after)->toBe($before);
        Queue::assertNothingPushed();
    });

    test('invalid HMAC returns invalid signature in payload', function () {
        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'deadbeef0000',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, 'wrong-secret');

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-uuid-4',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $messages = collect($data)->pluck('message')->implode(' ');
        expect($messages)->toContain('Invalid signature');
    });

    test('app with no webhook secret configured rejects push — cannot bypass validation', function () {
        // Create a second app with no webhook secret (security: must not allow bypass)
        $unsecuredApp = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'git_repository' => 'github.com/myorg/myrepo',
            'git_branch' => 'main',
            'manual_webhook_secret_github' => null, // No secret!
            'ports_exposes' => '3001',
        ]);
        $unsecuredApp->settings()->update(['is_auto_deploy_enabled' => true]);

        // Remove the main app's secret too (test only the unsecured app scenario)
        $this->application->update(['manual_webhook_secret_github' => null]);

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'abc123xyz',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [],
        ];
        $json = json_encode($payload);
        // Any signature (or none) should fail when webhook_secret is null
        $sig = hash_hmac('sha256', $json, 'any-value');

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-uuid-5',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();

        // No deployment should be created for apps without secrets
        $this->assertDatabaseMissing('application_deployment_queues', [
            'application_id' => $unsecuredApp->id,
        ]);
        $data = json_decode($response->getContent(), true);
        $messages = collect($data)->pluck('message')->implode(' ');
        expect($messages)->toContain('Webhook secret not configured');
    });

    test('LIKE special chars in repo full_name are escaped — does not leak to other apps', function () {
        // Create an application with a repo that contains LIKE-special characters
        $safeApp = Application::factory()->create([
            'environment_id' => $this->environment->id,
            'destination_id' => $this->destination->id,
            'destination_type' => StandaloneDocker::class,
            'git_repository' => 'github.com/org/specific-repo',
            'git_branch' => 'main',
            'manual_webhook_secret_github' => 'safe-secret',
            'ports_exposes' => '3002',
        ]);
        $safeApp->settings()->update(['is_auto_deploy_enabled' => true]);

        // Attacker sends full_name with '%' to match ALL repositories
        $maliciousFullName = '%';
        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'attack1234',
            'repository' => ['full_name' => $maliciousFullName],
            'commits' => [],
        ];
        $json = json_encode($payload);
        // Compute valid sig for the malicious payload (attacker controls both)
        $sig = hash_hmac('sha256', $json, 'safe-secret');

        $before = ApplicationDeploymentQueue::where('application_id', $safeApp->id)->count();

        $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-uuid-6',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        // safeApp should NOT be triggered — '%' query would have matched EVERYTHING
        // but the controller escapes '%' to '\%' so no app with '%' as full repo name is found
        $after = ApplicationDeploymentQueue::where('application_id', $safeApp->id)->count();
        expect($after)->toBe($before);
    });

    test('ping event returns pong without touching deployments', function () {
        $before = ApplicationDeploymentQueue::count();

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'ping',
        ], json_encode(['zen' => 'Push yourself to the limits']));

        $response->assertOk();
        expect($response->getContent())->toBe('pong');
        expect(ApplicationDeploymentQueue::count())->toBe($before);
    });

    test('non-functional server is reported as failed — no deployment queued', function () {
        // is_reachable is system-managed (not in $fillable), use forceFill to bypass
        $this->server->settings->forceFill(['is_reachable' => false])->save();

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'deadbeef9999',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [],
        ];
        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $before = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-uuid-7',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $messages = collect($data)->pluck('message')->implode(' ');
        expect($messages)->toContain('Server is not functional');

        $after = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();
        expect($after)->toBe($before);
        Queue::assertNothingPushed();
    });

    test('push to wrong branch does not trigger deployment', function () {
        $payload = [
            'ref' => 'refs/heads/feature/some-branch',
            'after' => 'wrongbranch1234',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [],
        ];
        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $before = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-uuid-8',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $after = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();
        expect($after)->toBe($before);
    });
});

// ─── GitLab Webhook ───────────────────────────────────────────────────────────

describe('GitLab webhook → deploy', function () {
    test('valid token triggers deployment and creates DB record', function () {
        $payload = [
            'object_kind' => 'push',
            'ref' => 'refs/heads/main',
            'project' => ['path_with_namespace' => 'myorg/myrepo'],
            'commits' => [],
        ];
        $json = json_encode($payload);

        $response = $this->call('POST', '/webhooks/source/gitlab/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITLAB_TOKEN' => $this->gitlabToken,
        ], $json);

        $response->assertOk();

        $this->assertDatabaseHas('application_deployment_queues', [
            'application_id' => $this->application->id,
            'is_webhook' => true,
            'triggered_by' => 'webhook',
        ]);
        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('empty x-gitlab-token header is rejected — cannot bypass validation', function () {
        $payload = [
            'object_kind' => 'push',
            'ref' => 'refs/heads/main',
            'project' => ['path_with_namespace' => 'myorg/myrepo'],
            'commits' => [],
        ];
        $json = json_encode($payload);

        $before = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $response = $this->call('POST', '/webhooks/source/gitlab/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            // No HTTP_X_GITLAB_TOKEN header
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $messages = collect($data)->pluck('message')->implode(' ');
        expect($messages)->toContain('Invalid signature');

        $after = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();
        expect($after)->toBe($before);
        Queue::assertNothingPushed();
    });

    test('wrong token value is rejected with timing-safe comparison', function () {
        $payload = [
            'object_kind' => 'push',
            'ref' => 'refs/heads/main',
            'project' => ['path_with_namespace' => 'myorg/myrepo'],
            'commits' => [],
        ];
        $json = json_encode($payload);

        $before = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $this->call('POST', '/webhooks/source/gitlab/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITLAB_TOKEN' => 'wrong-token-value',
        ], $json);

        $after = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();
        expect($after)->toBe($before);
    });

    test('disallowed event type returns event not allowed', function () {
        $payload = [
            'object_kind' => 'issue',
            'project' => ['path_with_namespace' => 'myorg/myrepo'],
        ];
        $json = json_encode($payload);

        $response = $this->call('POST', '/webhooks/source/gitlab/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITLAB_TOKEN' => $this->gitlabToken,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $messages = collect($data)->pluck('message')->implode(' ');
        expect($messages)->toContain('Event not allowed');

        Queue::assertNothingPushed();
    });

    test('gitlab LIKE special chars in repo path are escaped', function () {
        // Attacker sends '%' as namespace to match all repos
        $payload = [
            'object_kind' => 'push',
            'ref' => 'refs/heads/main',
            'project' => ['path_with_namespace' => '%'],
            'commits' => [],
        ];
        $json = json_encode($payload);

        $before = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $this->call('POST', '/webhooks/source/gitlab/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITLAB_TOKEN' => $this->gitlabToken,
        ], $json);

        $after = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();
        // myorg/myrepo should NOT match — '%' is escaped to '\%'
        expect($after)->toBe($before);
    });
});
