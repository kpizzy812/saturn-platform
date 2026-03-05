<?php

/**
 * E2E Integration Tests — Webhook PR/MR Deployment Workflows
 *
 * Tests the full HTTP path from an incoming pull request / merge request
 * webhook through signature validation, preview deployment creation,
 * author association filtering, watch paths, and multi-provider support
 * (GitHub, GitLab, Bitbucket, Gitea).
 *
 * All tests run against a real test database (rolled back per test).
 * The queue is faked so no SSH connections are attempted.
 */

use App\Actions\Application\CleanupPreviewDeployment;
use App\Jobs\ApplicationDeploymentJob;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\ApplicationPreview;
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

    $this->server = Server::withoutEvents(fn () => Server::factory()->create([
        'uuid' => (string) new Cuid2,
        'team_id' => $this->team->id,
        'private_key_id' => $this->privateKey->id,
        'ip' => '10.0.0.1',
    ]));
    $setting = ServerSetting::firstOrCreate(['server_id' => $this->server->id]);
    $setting->forceFill(['is_reachable' => true, 'is_usable' => true, 'force_disabled' => false])->save();

    $this->destination = StandaloneDocker::withoutEvents(fn () => StandaloneDocker::create([
        'uuid' => (string) new Cuid2,
        'name' => 'default',
        'network' => 'saturn',
        'server_id' => $this->server->id,
    ]));

    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);

    $this->webhookSecret = 'super-secret-test-key-pr-e2e';
    $this->gitlabToken = 'gitlab-secret-token-pr-e2e';
    $this->bitbucketSecret = 'bitbucket-secret-pr-e2e';
    $this->giteaSecret = 'gitea-secret-pr-e2e';

    $this->application = Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
        'git_repository' => 'github.com/myorg/myrepo',
        'git_branch' => 'main',
        'manual_webhook_secret_github' => $this->webhookSecret,
        'manual_webhook_secret_gitlab' => $this->gitlabToken,
        'manual_webhook_secret_bitbucket' => $this->bitbucketSecret,
        'manual_webhook_secret_gitea' => $this->giteaSecret,
        'ports_exposes' => '3000',
        'watch_paths' => null,
    ]);

    $this->application->settings()->update([
        'is_auto_deploy_enabled' => true,
        'is_preview_deployments_enabled' => true,
    ]);
});

// ─── GitHub PR Webhook ────────────────────────────────────────────────────────

describe('GitHub PR webhook → preview deploy', function () {
    test('pull_request opened creates preview deployment and ApplicationPreview record', function () {
        $payload = [
            'action' => 'opened',
            'number' => 42,
            'pull_request' => [
                'head' => [
                    'ref' => 'feature/awesome',
                    'sha' => 'abc123def456',
                ],
                'base' => [
                    'ref' => 'main',
                ],
                'html_url' => 'https://github.com/myorg/myrepo/pull/42',
                'author_association' => 'MEMBER',
            ],
            'repository' => ['full_name' => 'myorg/myrepo'],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-pr-1',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();

        $data = json_decode($response->getContent(), true);
        $match = collect($data)->first(fn ($item) => ($item['status'] ?? '') === 'success');
        expect($match)->not->toBeNull();
        expect($match['message'])->toBe('Preview deployment queued.');

        // ApplicationPreview record must exist
        $this->assertDatabaseHas('application_previews', [
            'application_id' => $this->application->id,
            'pull_request_id' => 42,
        ]);

        // Deployment must be queued with PR metadata
        $this->assertDatabaseHas('application_deployment_queues', [
            'application_id' => $this->application->id,
            'pull_request_id' => 42,
            'is_webhook' => true,
        ]);

        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('pull_request synchronize updates existing preview deployment', function () {
        // Pre-create the preview to simulate an already-opened PR
        ApplicationPreview::create([
            'application_id' => $this->application->id,
            'pull_request_id' => 43,
            'pull_request_html_url' => 'https://github.com/myorg/myrepo/pull/43',
        ]);

        $payload = [
            'action' => 'synchronize',
            'number' => 43,
            'pull_request' => [
                'head' => [
                    'ref' => 'feature/update',
                    'sha' => 'newcommit789',
                ],
                'base' => [
                    'ref' => 'main',
                ],
                'html_url' => 'https://github.com/myorg/myrepo/pull/43',
                'author_association' => 'COLLABORATOR',
            ],
            'repository' => ['full_name' => 'myorg/myrepo'],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-pr-2',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $match = collect($data)->first(fn ($item) => ($item['status'] ?? '') === 'success');
        expect($match)->not->toBeNull();
        expect($match['message'])->toBe('Preview deployment queued.');

        // A new deployment must have been queued for the same PR
        $this->assertDatabaseHas('application_deployment_queues', [
            'application_id' => $this->application->id,
            'pull_request_id' => 43,
            'is_webhook' => true,
        ]);
    });

    test('pull_request closed cleans up preview deployment', function () {
        // Pre-create the preview record
        ApplicationPreview::create([
            'application_id' => $this->application->id,
            'pull_request_id' => 44,
            'pull_request_html_url' => 'https://github.com/myorg/myrepo/pull/44',
        ]);

        // Mock CleanupPreviewDeployment to avoid SSH calls to a non-existent server
        $mock = Mockery::mock(CleanupPreviewDeployment::class);
        $mock->shouldReceive('handle')->once()->andReturn([
            'cancelled_deployments' => 0,
            'killed_containers' => 0,
            'status' => 'success',
        ]);
        $this->app->instance(CleanupPreviewDeployment::class, $mock);

        $payload = [
            'action' => 'closed',
            'number' => 44,
            'pull_request' => [
                'head' => [
                    'ref' => 'feature/done',
                    'sha' => 'finalcommit000',
                ],
                'base' => [
                    'ref' => 'main',
                ],
                'html_url' => 'https://github.com/myorg/myrepo/pull/44',
                'author_association' => 'OWNER',
            ],
            'repository' => ['full_name' => 'myorg/myrepo'],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-pr-3',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $match = collect($data)->first(fn ($item) => ($item['status'] ?? '') === 'success');
        expect($match)->not->toBeNull();
        expect($match['message'])->toBe('Preview deployment closed.');
    });

    test('PR from NONE author_association is rejected when public PRs are disabled', function () {
        // Disable public PR deployments (restrict to trusted contributors)
        $this->application->settings()->update([
            'is_pr_deployments_public_enabled' => false,
        ]);

        $payload = [
            'action' => 'opened',
            'number' => 45,
            'pull_request' => [
                'head' => [
                    'ref' => 'feature/malicious',
                    'sha' => 'untrusted111',
                ],
                'base' => [
                    'ref' => 'main',
                ],
                'html_url' => 'https://github.com/myorg/myrepo/pull/45',
                'author_association' => 'NONE',
            ],
            'repository' => ['full_name' => 'myorg/myrepo'],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $before = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-pr-4',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $messages = collect($data)->pluck('message')->implode(' ');
        expect($messages)->toContain('PR deployments are restricted');
        expect($messages)->toContain('Author association: NONE');

        $after = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();
        expect($after)->toBe($before);
        Queue::assertNothingPushed();
    });

    test('PR from COLLABORATOR is allowed when public PRs are disabled', function () {
        // Disable public PR deployments but allow trusted associations
        $this->application->settings()->update([
            'is_pr_deployments_public_enabled' => false,
        ]);

        $payload = [
            'action' => 'opened',
            'number' => 46,
            'pull_request' => [
                'head' => [
                    'ref' => 'feature/trusted',
                    'sha' => 'trusted222',
                ],
                'base' => [
                    'ref' => 'main',
                ],
                'html_url' => 'https://github.com/myorg/myrepo/pull/46',
                'author_association' => 'COLLABORATOR',
            ],
            'repository' => ['full_name' => 'myorg/myrepo'],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-pr-5',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $match = collect($data)->first(fn ($item) => ($item['status'] ?? '') === 'success');
        expect($match)->not->toBeNull();
        expect($match['message'])->toBe('Preview deployment queued.');

        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('preview deployments disabled returns failure message', function () {
        // Disable preview deployments entirely
        $this->application->settings()->update([
            'is_preview_deployments_enabled' => false,
        ]);

        $payload = [
            'action' => 'opened',
            'number' => 47,
            'pull_request' => [
                'head' => [
                    'ref' => 'feature/no-preview',
                    'sha' => 'noprev333',
                ],
                'base' => [
                    'ref' => 'main',
                ],
                'html_url' => 'https://github.com/myorg/myrepo/pull/47',
                'author_association' => 'OWNER',
            ],
            'repository' => ['full_name' => 'myorg/myrepo'],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'pull_request',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-pr-6',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $messages = collect($data)->pluck('message')->implode(' ');
        expect($messages)->toContain('Preview deployments disabled');

        Queue::assertNothingPushed();
    });
});

// ─── GitLab MR Webhook ────────────────────────────────────────────────────────

describe('GitLab MR webhook → preview deploy', function () {
    test('merge_request open creates preview deployment', function () {
        // Enable public MR deployments (GitLab has no author_association)
        $this->application->settings()->update([
            'is_pr_deployments_public_enabled' => true,
        ]);

        $payload = [
            'object_kind' => 'merge_request',
            'object_attributes' => [
                'action' => 'open',
                'iid' => 10,
                'source_branch' => 'feature/gitlab-mr',
                'target_branch' => 'main',
                'url' => 'https://gitlab.com/myorg/myrepo/-/merge_requests/10',
                'last_commit' => [
                    'id' => 'gitlab-commit-abc',
                ],
            ],
            'project' => ['path_with_namespace' => 'myorg/myrepo'],
        ];
        $json = json_encode($payload);

        $response = $this->call('POST', '/webhooks/source/gitlab/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITLAB_TOKEN' => $this->gitlabToken,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $match = collect($data)->first(fn ($item) => ($item['status'] ?? '') === 'success');
        expect($match)->not->toBeNull();
        expect($match['message'])->toBe('Preview Deployment queued');

        // ApplicationPreview record must exist
        $this->assertDatabaseHas('application_previews', [
            'application_id' => $this->application->id,
            'pull_request_id' => 10,
        ]);

        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('merge_request update triggers new preview deployment', function () {
        $this->application->settings()->update([
            'is_pr_deployments_public_enabled' => true,
        ]);

        // Pre-create the preview
        ApplicationPreview::create([
            'application_id' => $this->application->id,
            'pull_request_id' => 11,
            'pull_request_html_url' => 'https://gitlab.com/myorg/myrepo/-/merge_requests/11',
        ]);

        $payload = [
            'object_kind' => 'merge_request',
            'object_attributes' => [
                'action' => 'update',
                'iid' => 11,
                'source_branch' => 'feature/gitlab-update',
                'target_branch' => 'main',
                'url' => 'https://gitlab.com/myorg/myrepo/-/merge_requests/11',
                'last_commit' => [
                    'id' => 'gitlab-updated-commit',
                ],
            ],
            'project' => ['path_with_namespace' => 'myorg/myrepo'],
        ];
        $json = json_encode($payload);

        $response = $this->call('POST', '/webhooks/source/gitlab/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITLAB_TOKEN' => $this->gitlabToken,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $match = collect($data)->first(fn ($item) => ($item['status'] ?? '') === 'success');
        expect($match)->not->toBeNull();

        $this->assertDatabaseHas('application_deployment_queues', [
            'application_id' => $this->application->id,
            'pull_request_id' => 11,
            'is_webhook' => true,
        ]);
    });

    test('merge_request close cleans up preview deployment', function () {
        $this->application->settings()->update([
            'is_pr_deployments_public_enabled' => true,
        ]);

        // Pre-create the preview record
        ApplicationPreview::create([
            'application_id' => $this->application->id,
            'pull_request_id' => 12,
            'pull_request_html_url' => 'https://gitlab.com/myorg/myrepo/-/merge_requests/12',
        ]);

        // Mock CleanupPreviewDeployment to avoid SSH calls
        $mock = Mockery::mock(CleanupPreviewDeployment::class);
        $mock->shouldReceive('handle')->once()->andReturn([
            'cancelled_deployments' => 0,
            'killed_containers' => 0,
            'status' => 'success',
        ]);
        $this->app->instance(CleanupPreviewDeployment::class, $mock);

        $payload = [
            'object_kind' => 'merge_request',
            'object_attributes' => [
                'action' => 'close',
                'iid' => 12,
                'source_branch' => 'feature/gitlab-closed',
                'target_branch' => 'main',
                'url' => 'https://gitlab.com/myorg/myrepo/-/merge_requests/12',
                'last_commit' => [
                    'id' => 'gitlab-close-commit',
                ],
            ],
            'project' => ['path_with_namespace' => 'myorg/myrepo'],
        ];
        $json = json_encode($payload);

        $response = $this->call('POST', '/webhooks/source/gitlab/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITLAB_TOKEN' => $this->gitlabToken,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $match = collect($data)->first(fn ($item) => ($item['status'] ?? '') === 'success');
        expect($match)->not->toBeNull();
        expect($match['message'])->toBe('Preview deployment closed.');
    });

    test('merge_request rejected when public MR deployments are disabled', function () {
        $this->application->settings()->update([
            'is_pr_deployments_public_enabled' => false,
        ]);

        $payload = [
            'object_kind' => 'merge_request',
            'object_attributes' => [
                'action' => 'open',
                'iid' => 13,
                'source_branch' => 'feature/restricted',
                'target_branch' => 'main',
                'url' => 'https://gitlab.com/myorg/myrepo/-/merge_requests/13',
                'last_commit' => [
                    'id' => 'gitlab-restricted-commit',
                ],
            ],
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
        expect($messages)->toContain('MR deployments are restricted');

        Queue::assertNothingPushed();
    });
});

// ─── Watch Paths Filtering ───────────────────────────────────────────────────

describe('Watch paths filtering', function () {
    test('push with matching changed files triggers deployment', function () {
        // Set watch paths to only trigger on src/ directory changes
        $this->application->update(['watch_paths' => "src/**\npackage.json"]);

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'watchmatch1234',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [
                [
                    'added' => ['src/index.js'],
                    'removed' => [],
                    'modified' => [],
                ],
            ],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-watch-1',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $match = collect($data)->first(fn ($item) => ($item['status'] ?? '') === 'success');
        expect($match)->not->toBeNull();
        expect($match['message'])->toBe('Deployment queued.');

        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('push with non-matching changed files skips deployment', function () {
        // Set watch paths to only trigger on src/ directory changes
        $this->application->update(['watch_paths' => "src/**\npackage.json"]);

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'watchnomatch5678',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [
                [
                    'added' => [],
                    'removed' => [],
                    'modified' => ['docs/README.md', 'tests/unit/example.test.js'],
                ],
            ],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $before = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-watch-2',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $messages = collect($data)->pluck('message')->implode(' ');
        expect($messages)->toContain('Changed files do not match watch paths');

        $after = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();
        expect($after)->toBe($before);
        Queue::assertNothingPushed();
    });
});

// ─── Auto-deploy Disabled ────────────────────────────────────────────────────

describe('Auto-deploy disabled', function () {
    test('push to correct branch with auto-deploy disabled is skipped', function () {
        // Disable auto-deploy
        $this->application->settings()->update(['is_auto_deploy_enabled' => false]);

        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'autodisabled1234',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->webhookSecret);

        $before = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $response = $this->call('POST', '/webhooks/source/github/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITHUB_EVENT' => 'push',
            'HTTP_X_GITHUB_DELIVERY' => 'delivery-autodisabled-1',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $messages = collect($data)->pluck('message')->implode(' ');
        expect($messages)->toContain('Deployments disabled');

        $after = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();
        expect($after)->toBe($before);
        Queue::assertNothingPushed();
    });
});

// ─── Bitbucket Webhook ───────────────────────────────────────────────────────

describe('Bitbucket webhook → deploy', function () {
    test('valid HMAC signature on repo:push triggers deployment', function () {
        $payload = [
            'push' => [
                'changes' => [
                    [
                        'new' => [
                            'name' => 'main',
                            'target' => [
                                'hash' => 'bitbucket-commit-abc',
                            ],
                        ],
                    ],
                ],
            ],
            'repository' => ['full_name' => 'myorg/myrepo'],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->bitbucketSecret);

        $response = $this->call('POST', '/webhooks/source/bitbucket/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EVENT_KEY' => 'repo:push',
            'HTTP_X_HUB_SIGNATURE' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $match = collect($data)->first(fn ($item) => ($item['status'] ?? '') === 'success');
        expect($match)->not->toBeNull();
        expect($match['message'])->toBe('Deployment queued.');

        $this->assertDatabaseHas('application_deployment_queues', [
            'application_id' => $this->application->id,
            'is_webhook' => true,
            'commit' => 'bitbucket-commit-abc',
        ]);

        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('invalid HMAC signature on repo:push is rejected', function () {
        $payload = [
            'push' => [
                'changes' => [
                    [
                        'new' => [
                            'name' => 'main',
                            'target' => [
                                'hash' => 'bitbucket-bad-sig',
                            ],
                        ],
                    ],
                ],
            ],
            'repository' => ['full_name' => 'myorg/myrepo'],
        ];

        $json = json_encode($payload);
        // Deliberately wrong secret
        $sig = hash_hmac('sha256', $json, 'wrong-bitbucket-secret');

        $before = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $response = $this->call('POST', '/webhooks/source/bitbucket/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EVENT_KEY' => 'repo:push',
            'HTTP_X_HUB_SIGNATURE' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $messages = collect($data)->pluck('message')->implode(' ');
        expect($messages)->toContain('Invalid signature');

        $after = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();
        expect($after)->toBe($before);
        Queue::assertNothingPushed();
    });

    test('unhandled Bitbucket event returns nothing to do', function () {
        $payload = [
            'repository' => ['full_name' => 'myorg/myrepo'],
        ];

        $json = json_encode($payload);

        $response = $this->call('POST', '/webhooks/source/bitbucket/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EVENT_KEY' => 'repo:fork',
            'HTTP_X_HUB_SIGNATURE' => 'sha256=anything',
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        expect($data['message'])->toContain('Nothing to do');

        Queue::assertNothingPushed();
    });
});

// ─── Gitea Webhook ───────────────────────────────────────────────────────────

describe('Gitea webhook → deploy', function () {
    test('valid HMAC-SHA256 signature on push triggers deployment', function () {
        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'gitea-commit-xyz',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [
                [
                    'added' => ['main.go'],
                    'removed' => [],
                    'modified' => [],
                ],
            ],
        ];

        $json = json_encode($payload);
        $sig = hash_hmac('sha256', $json, $this->giteaSecret);

        $response = $this->call('POST', '/webhooks/source/gitea/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITEA_EVENT' => 'push',
            'HTTP_X_GITEA_DELIVERY' => 'delivery-gitea-1',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $match = collect($data)->first(fn ($item) => ($item['status'] ?? '') === 'success');
        expect($match)->not->toBeNull();
        expect($match['message'])->toBe('Deployment queued.');

        $this->assertDatabaseHas('application_deployment_queues', [
            'application_id' => $this->application->id,
            'is_webhook' => true,
            'commit' => 'gitea-commit-xyz',
        ]);

        Queue::assertPushed(ApplicationDeploymentJob::class);
    });

    test('invalid HMAC-SHA256 signature on Gitea push is rejected', function () {
        $payload = [
            'ref' => 'refs/heads/main',
            'after' => 'gitea-bad-sig',
            'repository' => ['full_name' => 'myorg/myrepo'],
            'commits' => [],
        ];

        $json = json_encode($payload);
        // Deliberately wrong secret
        $sig = hash_hmac('sha256', $json, 'wrong-gitea-secret');

        $before = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();

        $response = $this->call('POST', '/webhooks/source/gitea/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITEA_EVENT' => 'push',
            'HTTP_X_GITEA_DELIVERY' => 'delivery-gitea-2',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.$sig,
        ], $json);

        $response->assertOk();
        $data = json_decode($response->getContent(), true);
        $messages = collect($data)->pluck('message')->implode(' ');
        expect($messages)->toContain('Invalid signature');

        $after = ApplicationDeploymentQueue::where('application_id', $this->application->id)->count();
        expect($after)->toBe($before);
        Queue::assertNothingPushed();
    });

    test('Gitea ping event returns pong', function () {
        $response = $this->call('POST', '/webhooks/source/gitea/events/manual', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_GITEA_EVENT' => 'ping',
        ], json_encode(['zen' => 'Gitea test']));

        $response->assertOk();
        expect($response->getContent())->toBe('pong');
        Queue::assertNothingPushed();
    });
});
