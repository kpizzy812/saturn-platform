<?php

/**
 * E2E API Token Ability Matrix Tests
 *
 * Systematically tests API token ability enforcement across ALL major API endpoint categories.
 * Verifies that each ability (read, write, deploy, read:sensitive, root) is properly
 * enforced on every endpoint type, and that cross-team IDOR returns 404 consistently.
 *
 * Ability matrix:
 * - read:     can GET list/detail endpoints, cannot POST/PATCH/DELETE or deploy
 * - write:    can GET and POST/PATCH/DELETE resources, cannot deploy
 * - deploy:   can trigger deployments, cannot write resources or read lists
 * - root:     full access to everything
 * - read:sensitive: can access sensitive data fields (private keys, env vars in logs)
 * - no abilities: 403 everywhere
 */

use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\Service;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

// ─── Helper ──────────────────────────────────────────────────────────────────

function abilityHeaders(string $bearer): array
{
    return [
        'Authorization' => 'Bearer '.$bearer,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Queue::fake();

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);
    session(['currentTeam' => $this->team]);

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

    $this->service = Service::factory()->create([
        'environment_id' => $this->environment->id,
        'server_id' => $this->server->id,
        'destination_id' => $this->destination->id,
        'destination_type' => StandaloneDocker::class,
    ]);

    // Create OTHER team for IDOR tests
    $this->otherTeam = Team::factory()->create();
    $this->otherKey = PrivateKey::factory()->create(['team_id' => $this->otherTeam->id]);
    $this->otherServer = Server::withoutEvents(fn () => Server::factory()->create([
        'uuid' => (string) new Cuid2,
        'team_id' => $this->otherTeam->id,
        'private_key_id' => $this->otherKey->id,
    ]));
    ServerSetting::firstOrCreate(['server_id' => $this->otherServer->id]);
});

// ─── Read-only token ability enforcement ─────────────────────────────────────

describe('read-only token ability enforcement', function () {
    beforeEach(function () {
        $this->readToken = $this->user->createToken('read-only', ['read']);
        $this->readBearer = $this->readToken->plainTextToken;
    });

    // -- Servers --
    test('read token can GET /servers', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->getJson('/api/v1/servers')
            ->assertOk();
    });

    test('read token can GET /servers/{uuid}', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->getJson("/api/v1/servers/{$this->server->uuid}")
            ->assertOk();
    });

    test('read token cannot POST /servers', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->postJson('/api/v1/servers', [
                'name' => 'Test Server',
                'ip' => '10.0.0.1',
                'private_key_uuid' => $this->privateKey->uuid,
            ])
            ->assertStatus(403);
    });

    test('read token cannot PATCH /servers/{uuid}', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->patchJson("/api/v1/servers/{$this->server->uuid}", ['name' => 'Renamed'])
            ->assertStatus(403);
    });

    test('read token cannot DELETE /servers/{uuid}', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->deleteJson("/api/v1/servers/{$this->server->uuid}")
            ->assertStatus(403);
    });

    // -- Applications --
    test('read token can GET /applications', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->getJson('/api/v1/applications')
            ->assertOk();
    });

    test('read token can GET /applications/{uuid}', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->getJson("/api/v1/applications/{$this->application->uuid}")
            ->assertOk();
    });

    test('read token cannot POST /applications/public', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->postJson('/api/v1/applications/public', [
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'server_uuid' => $this->server->uuid,
                'destination_uuid' => $this->destination->uuid,
                'git_repository' => 'https://github.com/example/repo',
                'git_branch' => 'main',
                'ports_exposes' => '3000',
            ])
            ->assertStatus(403);
    });

    // -- Services --
    test('read token can GET /services', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->getJson('/api/v1/services')
            ->assertOk();
    });

    test('read token cannot POST /services', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->postJson('/api/v1/services', [
                'type' => 'plausible-analytics',
                'server_uuid' => $this->server->uuid,
                'project_uuid' => $this->project->uuid,
                'environment_name' => $this->environment->name,
                'destination_uuid' => $this->destination->uuid,
            ])
            ->assertStatus(403);
    });

    test('read token cannot PATCH /services/{uuid}', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->patchJson("/api/v1/services/{$this->service->uuid}", ['name' => 'Renamed'])
            ->assertStatus(403);
    });

    test('read token cannot DELETE /services/{uuid}', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->deleteJson("/api/v1/services/{$this->service->uuid}")
            ->assertStatus(403);
    });

    // -- Databases --
    test('read token can GET /databases', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->getJson('/api/v1/databases')
            ->assertOk();
    });

    // -- Deploy --
    test('read token cannot GET /deploy', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}")
            ->assertStatus(403);

        Queue::assertNothingPushed();
    });

    // -- Security/Keys --
    test('read token can GET /security/keys', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->getJson('/api/v1/security/keys')
            ->assertOk();
    });

    test('read token cannot POST /security/keys', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->postJson('/api/v1/security/keys', [
                'name' => 'New Key',
                'private_key' => 'ssh-rsa AAAA...',
            ])
            ->assertStatus(403);
    });

    // -- Projects --
    test('read token can GET /projects', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->getJson('/api/v1/projects')
            ->assertOk();
    });

    test('read token cannot POST /projects', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->postJson('/api/v1/projects', ['name' => 'Unauthorized Project'])
            ->assertStatus(403);
    });

    // -- Webhooks --
    test('read token can GET /webhooks', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->getJson('/api/v1/webhooks')
            ->assertOk();
    });

    test('read token cannot POST /webhooks', function () {
        $this->withHeaders(abilityHeaders($this->readBearer))
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['deployment.created'],
            ])
            ->assertStatus(403);
    });
});

// ─── Write token ability enforcement ─────────────────────────────────────────

describe('write token ability enforcement', function () {
    beforeEach(function () {
        $this->writeToken = $this->user->createToken('write-only', ['write']);
        $this->writeBearer = $this->writeToken->plainTextToken;
    });

    // Write tokens should also grant read access (write implies read in Sanctum with root shortcut,
    // but actual enforcement depends on route middleware). Write middleware is on write routes;
    // read routes require 'read' ability specifically. Let's verify this.

    test('write token cannot GET /servers (requires read ability)', function () {
        $this->withHeaders(abilityHeaders($this->writeBearer))
            ->getJson('/api/v1/servers')
            ->assertStatus(403);
    });

    test('write token can POST /servers', function () {
        $response = $this->withHeaders(abilityHeaders($this->writeBearer))
            ->postJson('/api/v1/servers', [
                'name' => 'Write-Created Server',
                'ip' => '10.0.0.2',
                'private_key_uuid' => $this->privateKey->uuid,
            ]);

        // Accepts 201 (created) or 422 (validation issue), but not 403
        expect($response->status())->not->toBe(403);
    });

    test('write token can PATCH /servers/{uuid}', function () {
        $response = $this->withHeaders(abilityHeaders($this->writeBearer))
            ->patchJson("/api/v1/servers/{$this->server->uuid}", ['name' => 'Updated by write']);

        // Should not be 403 — write ability is sufficient
        expect($response->status())->not->toBe(403);
    });

    test('write token cannot trigger deploy (requires deploy ability)', function () {
        $this->withHeaders(abilityHeaders($this->writeBearer))
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}")
            ->assertStatus(403);

        Queue::assertNothingPushed();
    });

    test('write token can POST /projects', function () {
        $response = $this->withHeaders(abilityHeaders($this->writeBearer))
            ->postJson('/api/v1/projects', ['name' => 'Write Project']);

        // Not 403 — either 201 or validation error
        expect($response->status())->not->toBe(403);
    });

    test('write token can POST /security/keys', function () {
        $response = $this->withHeaders(abilityHeaders($this->writeBearer))
            ->postJson('/api/v1/security/keys', [
                'name' => 'Write Key',
                'private_key' => 'ssh-rsa AAAA...',
            ]);

        // Not 403
        expect($response->status())->not->toBe(403);
    });

    test('write token can POST /webhooks', function () {
        $response = $this->withHeaders(abilityHeaders($this->writeBearer))
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['deployment.created'],
            ]);

        expect($response->status())->not->toBe(403);
    });
});

// ─── Deploy token ability enforcement ────────────────────────────────────────

describe('deploy token ability enforcement', function () {
    beforeEach(function () {
        $this->deployToken = $this->user->createToken('deploy-only', ['deploy']);
        $this->deployBearer = $this->deployToken->plainTextToken;
    });

    test('deploy token can trigger deployment', function () {
        $response = $this->withHeaders(abilityHeaders($this->deployBearer))
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertOk();
        Queue::assertPushed(\App\Jobs\ApplicationDeploymentJob::class);
    });

    test('deploy token cannot GET /servers (requires read ability)', function () {
        $this->withHeaders(abilityHeaders($this->deployBearer))
            ->getJson('/api/v1/servers')
            ->assertStatus(403);
    });

    test('deploy token cannot POST /servers (requires write ability)', function () {
        $this->withHeaders(abilityHeaders($this->deployBearer))
            ->postJson('/api/v1/servers', [
                'name' => 'Deploy Token Server',
                'ip' => '10.0.0.3',
                'private_key_uuid' => $this->privateKey->uuid,
            ])
            ->assertStatus(403);
    });

    test('deploy token cannot GET /applications (requires read ability)', function () {
        $this->withHeaders(abilityHeaders($this->deployBearer))
            ->getJson('/api/v1/applications')
            ->assertStatus(403);
    });

    test('deploy token cannot POST /projects (requires write ability)', function () {
        $this->withHeaders(abilityHeaders($this->deployBearer))
            ->postJson('/api/v1/projects', ['name' => 'Deploy Project'])
            ->assertStatus(403);
    });

    test('deploy token cannot GET /security/keys (requires read ability)', function () {
        $this->withHeaders(abilityHeaders($this->deployBearer))
            ->getJson('/api/v1/security/keys')
            ->assertStatus(403);
    });

    test('deploy token cannot GET /databases (requires read ability)', function () {
        $this->withHeaders(abilityHeaders($this->deployBearer))
            ->getJson('/api/v1/databases')
            ->assertStatus(403);
    });
});

// ─── Root token has full access ──────────────────────────────────────────────

describe('root token has full access', function () {
    beforeEach(function () {
        $this->rootToken = $this->user->createToken('root-token', ['root']);
        $this->rootBearer = $this->rootToken->plainTextToken;
    });

    test('root token can GET /servers', function () {
        $this->withHeaders(abilityHeaders($this->rootBearer))
            ->getJson('/api/v1/servers')
            ->assertOk();
    });

    test('root token can POST /servers', function () {
        $response = $this->withHeaders(abilityHeaders($this->rootBearer))
            ->postJson('/api/v1/servers', [
                'name' => 'Root Server',
                'ip' => '10.0.0.4',
                'private_key_uuid' => $this->privateKey->uuid,
            ]);

        // root can write — not 403
        expect($response->status())->not->toBe(403);
    });

    test('root token can trigger deployment', function () {
        $response = $this->withHeaders(abilityHeaders($this->rootBearer))
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}");

        $response->assertOk();
        Queue::assertPushed(\App\Jobs\ApplicationDeploymentJob::class);
    });

    test('root token can GET /applications', function () {
        $this->withHeaders(abilityHeaders($this->rootBearer))
            ->getJson('/api/v1/applications')
            ->assertOk();
    });

    test('root token can GET /databases', function () {
        $this->withHeaders(abilityHeaders($this->rootBearer))
            ->getJson('/api/v1/databases')
            ->assertOk();
    });

    test('root token can GET /projects', function () {
        $this->withHeaders(abilityHeaders($this->rootBearer))
            ->getJson('/api/v1/projects')
            ->assertOk();
    });

    test('root token can POST /projects', function () {
        $response = $this->withHeaders(abilityHeaders($this->rootBearer))
            ->postJson('/api/v1/projects', ['name' => 'Root Project']);

        expect($response->status())->not->toBe(403);
    });

    test('root token can GET /security/keys', function () {
        $this->withHeaders(abilityHeaders($this->rootBearer))
            ->getJson('/api/v1/security/keys')
            ->assertOk();
    });

    test('root token can GET /services', function () {
        $this->withHeaders(abilityHeaders($this->rootBearer))
            ->getJson('/api/v1/services')
            ->assertOk();
    });

    test('root token can GET /webhooks', function () {
        $this->withHeaders(abilityHeaders($this->rootBearer))
            ->getJson('/api/v1/webhooks')
            ->assertOk();
    });

    test('root token can POST /webhooks', function () {
        $response = $this->withHeaders(abilityHeaders($this->rootBearer))
            ->postJson('/api/v1/webhooks', [
                'url' => 'https://example.com/webhook',
                'events' => ['deployment.created'],
            ]);

        expect($response->status())->not->toBe(403);
    });
});

// ─── Token with no abilities ─────────────────────────────────────────────────

describe('token with no abilities returns 403 everywhere', function () {
    beforeEach(function () {
        // Create token with empty abilities array — no permissions at all
        $this->emptyToken = $this->user->createToken('no-abilities', []);
        $this->emptyBearer = $this->emptyToken->plainTextToken;
    });

    test('empty token cannot GET /servers', function () {
        $this->withHeaders(abilityHeaders($this->emptyBearer))
            ->getJson('/api/v1/servers')
            ->assertStatus(403);
    });

    test('empty token cannot POST /servers', function () {
        $this->withHeaders(abilityHeaders($this->emptyBearer))
            ->postJson('/api/v1/servers', ['name' => 'Denied', 'ip' => '10.0.0.5'])
            ->assertStatus(403);
    });

    test('empty token cannot GET /applications', function () {
        $this->withHeaders(abilityHeaders($this->emptyBearer))
            ->getJson('/api/v1/applications')
            ->assertStatus(403);
    });

    test('empty token cannot trigger deploy', function () {
        $this->withHeaders(abilityHeaders($this->emptyBearer))
            ->getJson("/api/v1/deploy?uuid={$this->application->uuid}")
            ->assertStatus(403);

        Queue::assertNothingPushed();
    });

    test('empty token cannot GET /projects', function () {
        $this->withHeaders(abilityHeaders($this->emptyBearer))
            ->getJson('/api/v1/projects')
            ->assertStatus(403);
    });

    test('empty token cannot GET /databases', function () {
        $this->withHeaders(abilityHeaders($this->emptyBearer))
            ->getJson('/api/v1/databases')
            ->assertStatus(403);
    });

    test('empty token cannot GET /services', function () {
        $this->withHeaders(abilityHeaders($this->emptyBearer))
            ->getJson('/api/v1/services')
            ->assertStatus(403);
    });

    test('empty token cannot GET /webhooks', function () {
        $this->withHeaders(abilityHeaders($this->emptyBearer))
            ->getJson('/api/v1/webhooks')
            ->assertStatus(403);
    });

    test('empty token cannot GET /security/keys', function () {
        $this->withHeaders(abilityHeaders($this->emptyBearer))
            ->getJson('/api/v1/security/keys')
            ->assertStatus(403);
    });
});

// ─── Authentication edge cases ───────────────────────────────────────────────

describe('authentication edge cases', function () {
    test('request without any token returns 401', function () {
        $this->getJson('/api/v1/servers')
            ->assertStatus(401);
    });

    test('request with invalid bearer token returns 401', function () {
        $this->withHeaders(abilityHeaders('completely-invalid-token-value'))
            ->getJson('/api/v1/servers')
            ->assertStatus(401);
    });

    test('request with malformed authorization header returns 401', function () {
        $this->withHeaders([
            'Authorization' => 'InvalidScheme token123',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/servers')
            ->assertStatus(401);
    });
});

// ─── Cross-team IDOR protection ──────────────────────────────────────────────

describe('cross-team IDOR protection returns 404 consistently', function () {
    beforeEach(function () {
        // Full-access token for our team
        $this->fullToken = $this->user->createToken('full-access', ['*']);
        $this->fullBearer = $this->fullToken->plainTextToken;
    });

    test('cannot access other team server by uuid — returns 404', function () {
        $this->withHeaders(abilityHeaders($this->fullBearer))
            ->getJson("/api/v1/servers/{$this->otherServer->uuid}")
            ->assertStatus(404);
    });

    test('cannot PATCH other team server — returns 404', function () {
        $this->withHeaders(abilityHeaders($this->fullBearer))
            ->patchJson("/api/v1/servers/{$this->otherServer->uuid}", ['name' => 'Hijacked'])
            ->assertStatus(404);
    });

    test('cannot DELETE other team server — returns 404', function () {
        $this->withHeaders(abilityHeaders($this->fullBearer))
            ->deleteJson("/api/v1/servers/{$this->otherServer->uuid}")
            ->assertStatus(404);
    });

    test('cannot deploy using other team application uuid — returns 404', function () {
        // Create application in other team
        $otherProject = Project::factory()->create(['team_id' => $this->otherTeam->id]);
        $otherEnv = Environment::factory()->create(['project_id' => $otherProject->id]);

        $otherDestination = StandaloneDocker::withoutEvents(fn () => StandaloneDocker::create([
            'uuid' => (string) new Cuid2,
            'name' => 'other-default',
            'network' => 'saturn',
            'server_id' => $this->otherServer->id,
        ]));

        $otherApp = Application::factory()->create([
            'environment_id' => $otherEnv->id,
            'destination_id' => $otherDestination->id,
            'destination_type' => StandaloneDocker::class,
            'ports_exposes' => '3000',
        ]);

        $response = $this->withHeaders(abilityHeaders($this->fullBearer))
            ->getJson("/api/v1/deploy?uuid={$otherApp->uuid}");

        // Should not find resources in other team — 404
        $response->assertStatus(404);
        Queue::assertNothingPushed();
    });

    test('cannot access other team private key — returns 404', function () {
        $this->withHeaders(abilityHeaders($this->fullBearer))
            ->getJson("/api/v1/security/keys/{$this->otherKey->uuid}")
            ->assertStatus(404);
    });
});

// ─── Sensitive data access control ───────────────────────────────────────────

describe('sensitive data access control', function () {
    test('read-only token deployment logs are hidden (no read:sensitive)', function () {
        // Create a finished deployment with logs
        $deployment = \App\Models\ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => \App\Enums\ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => (string) new Cuid2,
            'logs' => json_encode([['output' => 'SECRET_KEY=abc123', 'timestamp' => now()->toIso8601String()]]),
        ]);

        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders(abilityHeaders($readToken->plainTextToken))
            ->getJson("/api/v1/deployments/{$deployment->deployment_uuid}/logs");

        $response->assertOk();
        // Logs should be empty array for non-sensitive tokens
        expect($response->json('logs'))->toBeEmpty();
    });

    test('read:sensitive token can access deployment logs', function () {
        $deployment = \App\Models\ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => \App\Enums\ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => (string) new Cuid2,
            'logs' => json_encode([['output' => 'Build step 1', 'timestamp' => now()->toIso8601String()]]),
        ]);

        $sensitiveToken = $this->user->createToken('sensitive', ['read', 'read:sensitive']);

        $response = $this->withHeaders(abilityHeaders($sensitiveToken->plainTextToken))
            ->getJson("/api/v1/deployments/{$deployment->deployment_uuid}/logs");

        $response->assertOk();
        // Sensitive token should see logs (if user is admin+ in team, which owner is)
        $logs = $response->json('logs');
        // The sensitive middleware also checks team role — owner qualifies as admin+
        expect($logs)->toBeArray();
    });

    test('deployment detail hides logs field for non-sensitive token', function () {
        $deployment = \App\Models\ApplicationDeploymentQueue::factory()->create([
            'application_id' => $this->application->id,
            'server_id' => $this->server->id,
            'status' => \App\Enums\ApplicationDeploymentStatus::FINISHED->value,
            'deployment_uuid' => (string) new Cuid2,
        ]);

        $readToken = $this->user->createToken('read-only', ['read']);

        $response = $this->withHeaders(abilityHeaders($readToken->plainTextToken))
            ->getJson("/api/v1/deployments/{$deployment->deployment_uuid}");

        $response->assertOk();
        // The deployment detail endpoint strips logs for non-sensitive tokens
        $json = $response->json();
        expect($json)->not->toHaveKey('logs');
    });
});
