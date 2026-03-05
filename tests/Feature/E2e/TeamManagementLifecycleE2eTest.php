<?php

/**
 * E2E Team Management Lifecycle Tests
 *
 * Tests multi-step integration scenarios for team management that go BEYOND
 * the individual API tests in TeamApiTest.php. These tests verify:
 *
 * - Multi-team token isolation (Team A vs Team B resources)
 * - Team member role-based access across endpoints
 * - Activity audit trail lifecycle (create → update → delete → verify log)
 * - Cross-team resource isolation chains (project/env/server/app)
 * - Team with multiple members and role verification
 * - Activity export with real data (JSON and CSV)
 * - Activity date filtering accuracy
 * - Team context switching via different tokens
 *
 * All tests use DatabaseTransactions to ensure isolation.
 */

use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Project;
use App\Models\Server;
use App\Models\ServerSetting;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Visus\Cuid2\Cuid2;

uses(DatabaseTransactions::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function teamE2eHeaders(string $bearerToken): array
{
    return [
        'Authorization' => 'Bearer '.$bearerToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

/**
 * Create a full infrastructure stack for a given team:
 * PrivateKey → Server → ServerSetting → StandaloneDocker → Project → Environment
 */
function createTeamInfrastructure(Team $team): array
{
    $privateKey = PrivateKey::factory()->create(['team_id' => $team->id]);

    $server = Server::withoutEvents(fn () => Server::factory()->create([
        'uuid' => (string) new Cuid2,
        'team_id' => $team->id,
        'private_key_id' => $privateKey->id,
    ]));
    ServerSetting::firstOrCreate(['server_id' => $server->id]);

    $destination = StandaloneDocker::withoutEvents(fn () => StandaloneDocker::create([
        'uuid' => (string) new Cuid2,
        'name' => 'default-'.Str::random(6),
        'network' => 'saturn',
        'server_id' => $server->id,
    ]));

    $project = Project::factory()->create(['team_id' => $team->id]);
    $environment = Environment::factory()->create(['project_id' => $project->id]);

    return compact('privateKey', 'server', 'destination', 'project', 'environment');
}

/**
 * Create an application within a given infrastructure stack.
 */
function createAppInInfra(array $infra, string $name = 'Test App'): Application
{
    return Application::factory()->create([
        'name' => $name,
        'environment_id' => $infra['environment']->id,
        'destination_id' => $infra['destination']->id,
        'destination_type' => StandaloneDocker::class,
        'ports_exposes' => '3000',
    ]);
}

/**
 * Create a Spatie activity log entry for a given user and optional subject.
 */
function createActivity(User $user, string $event, string $description, $subject = null, ?array $properties = null): Activity
{
    $activity = new Activity;
    $activity->log_name = 'default';
    $activity->description = $description;
    $activity->event = $event;
    $activity->causer_type = 'App\\Models\\User';
    $activity->causer_id = $user->id;
    if ($subject) {
        $activity->subject_type = get_class($subject);
        $activity->subject_id = $subject->id;
    }
    if ($properties) {
        $activity->properties = collect($properties);
    }
    $activity->save();

    return $activity;
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Queue::fake();
    Cache::flush();
    try {
        Cache::store('redis')->flush();
    } catch (\Throwable $e) {
    }

    $this->team = Team::factory()->create(['name' => 'Primary Team']);
    $this->user = User::factory()->create(['name' => 'Team Owner', 'email' => 'owner@example.com']);
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! InstanceSettings::first()) {
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }
});

// ─── Multi-team token isolation ──────────────────────────────────────────────

describe('Multi-team token isolation', function () {
    test('user with two teams only sees resources scoped to their token team', function () {
        // Setup: Team A infrastructure with an application
        $teamA = $this->team;
        $infraA = createTeamInfrastructure($teamA);
        $appA = createAppInInfra($infraA, 'App Alpha');

        // Setup: Team B with its own infrastructure
        $teamB = Team::factory()->create(['name' => 'Team Beta']);
        $this->user->teams()->attach($teamB->id, ['role' => 'owner']);
        $infraB = createTeamInfrastructure($teamB);
        $appB = createAppInInfra($infraB, 'App Beta');

        // Token for Team A (already created in beforeEach)
        $tokenA = $this->bearerToken;

        // Token for Team B
        session(['currentTeam' => $teamB]);
        Cache::flush();
        $tokenB = $this->user->createToken('team-b-token', ['*'])->plainTextToken;

        // -- Team A token sees only Team A projects --
        $responseA = $this->withHeaders(teamE2eHeaders($tokenA))
            ->getJson('/api/v1/projects');
        $responseA->assertOk();
        $projectNames = collect($responseA->json())->pluck('name')->toArray();
        expect($projectNames)->toContain($infraA['project']->name);
        expect($projectNames)->not->toContain($infraB['project']->name);

        // -- Team B token sees only Team B projects --
        $responseB = $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson('/api/v1/projects');
        $responseB->assertOk();
        $projectNamesB = collect($responseB->json())->pluck('name')->toArray();
        expect($projectNamesB)->toContain($infraB['project']->name);
        expect($projectNamesB)->not->toContain($infraA['project']->name);

        // -- Team A token sees only Team A servers --
        $serversA = $this->withHeaders(teamE2eHeaders($tokenA))
            ->getJson('/api/v1/servers');
        $serversA->assertOk();
        $serverUuids = collect($serversA->json())->pluck('uuid')->toArray();
        expect($serverUuids)->toContain($infraA['server']->uuid);
        expect($serverUuids)->not->toContain($infraB['server']->uuid);

        // -- Team B token sees only Team B servers --
        $serversB = $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson('/api/v1/servers');
        $serversB->assertOk();
        $serverUuidsB = collect($serversB->json())->pluck('uuid')->toArray();
        expect($serverUuidsB)->toContain($infraB['server']->uuid);
        expect($serverUuidsB)->not->toContain($infraA['server']->uuid);
    });

    test('token for Team A cannot access Team B application by UUID', function () {
        $teamB = Team::factory()->create(['name' => 'Team Beta']);
        $otherUser = User::factory()->create();
        $teamB->members()->attach($otherUser->id, ['role' => 'owner']);
        $infraB = createTeamInfrastructure($teamB);
        $appB = createAppInInfra($infraB, 'Secret App');

        // Try to access Team B app with Team A token
        $response = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson("/api/v1/applications/{$appB->uuid}");

        $response->assertStatus(404);
    });
});

// ─── Team context switching ──────────────────────────────────────────────────

describe('Team context switching via different tokens', function () {
    test('/teams/current returns correct team per token', function () {
        $teamA = $this->team;
        $teamB = Team::factory()->create(['name' => 'Context Team B']);
        $this->user->teams()->attach($teamB->id, ['role' => 'owner']);

        $tokenA = $this->bearerToken;

        session(['currentTeam' => $teamB]);
        Cache::flush();
        $tokenB = $this->user->createToken('ctx-b-token', ['*'])->plainTextToken;

        // Token A → current team is Team A
        $responseA = $this->withHeaders(teamE2eHeaders($tokenA))
            ->getJson('/api/v1/teams/current');
        $responseA->assertOk();
        $responseA->assertJsonFragment(['name' => 'Primary Team']);

        // Token B → current team is Team B
        $responseB = $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson('/api/v1/teams/current');
        $responseB->assertOk();
        $responseB->assertJsonFragment(['name' => 'Context Team B']);
    });

    test('/teams/current/members returns correct members per token team', function () {
        // Team A has the owner
        $memberA = User::factory()->create(['name' => 'Alice from Team A', 'email' => 'alice-a@example.com']);
        $this->team->members()->attach($memberA->id, ['role' => 'member']);

        // Team B has a different member
        $teamB = Team::factory()->create(['name' => 'Switch Team B']);
        $this->user->teams()->attach($teamB->id, ['role' => 'owner']);
        $memberB = User::factory()->create(['name' => 'Bob from Team B', 'email' => 'bob-b@example.com']);
        $teamB->members()->attach($memberB->id, ['role' => 'admin']);

        session(['currentTeam' => $teamB]);
        Cache::flush();
        $tokenB = $this->user->createToken('switch-b', ['*'])->plainTextToken;

        // Team A token → members include Alice, not Bob
        $respA = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/members');
        $respA->assertOk();
        $namesA = collect($respA->json())->pluck('name')->toArray();
        expect($namesA)->toContain('Alice from Team A');
        expect($namesA)->not->toContain('Bob from Team B');

        // Team B token → members include Bob, not Alice
        $respB = $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson('/api/v1/teams/current/members');
        $respB->assertOk();
        $namesB = collect($respB->json())->pluck('name')->toArray();
        expect($namesB)->toContain('Bob from Team B');
        expect($namesB)->not->toContain('Alice from Team A');
    });

    test('user sees all teams in /teams but each token scopes /teams/current correctly', function () {
        $teamB = Team::factory()->create(['name' => 'List Team B']);
        $teamC = Team::factory()->create(['name' => 'List Team C']);
        $this->user->teams()->attach($teamB->id, ['role' => 'member']);
        $this->user->teams()->attach($teamC->id, ['role' => 'admin']);

        // /teams endpoint shows all teams regardless of which token is used
        $response = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams');
        $response->assertOk();
        $teamNames = collect($response->json())->pluck('name')->toArray();
        expect($teamNames)->toContain('Primary Team');
        expect($teamNames)->toContain('List Team B');
        expect($teamNames)->toContain('List Team C');

        // But /teams/current is scoped to the token's team
        $currentResp = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current');
        $currentResp->assertOk();
        $currentResp->assertJsonFragment(['name' => 'Primary Team']);
    });
});

// ─── Cross-team resource isolation chain ─────────────────────────────────────

describe('Cross-team resource isolation chain', function () {
    test('Team B token cannot access any of Team A resources across all endpoints', function () {
        // Team A infrastructure (created for the default team)
        $infraA = createTeamInfrastructure($this->team);
        $appA = createAppInInfra($infraA, 'Team A App');

        // Team B with its own token
        $teamB = Team::factory()->create(['name' => 'Isolation Team B']);
        $userB = User::factory()->create(['name' => 'User B', 'email' => 'userb@example.com']);
        $teamB->members()->attach($userB->id, ['role' => 'owner']);

        session(['currentTeam' => $teamB]);
        Cache::flush();
        $tokenB = $userB->createToken('team-b-isolation', ['*'])->plainTextToken;

        // Cannot access Team A server
        $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson("/api/v1/servers/{$infraA['server']->uuid}")
            ->assertStatus(404);

        // Cannot access Team A project
        $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson("/api/v1/projects/{$infraA['project']->uuid}")
            ->assertStatus(404);

        // Cannot access Team A application
        $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson("/api/v1/applications/{$appA->uuid}")
            ->assertStatus(404);

        // Cannot access Team A private key
        $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson("/api/v1/security/keys/{$infraA['privateKey']->uuid}")
            ->assertStatus(404);

        // Team A resources do NOT appear in Team B lists
        $projectsList = $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson('/api/v1/projects');
        $projectsList->assertOk();
        $projNames = collect($projectsList->json())->pluck('name')->toArray();
        expect($projNames)->not->toContain($infraA['project']->name);

        $serversList = $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson('/api/v1/servers');
        $serversList->assertOk();
        $srvUuids = collect($serversList->json())->pluck('uuid')->toArray();
        expect($srvUuids)->not->toContain($infraA['server']->uuid);
    });

    test('Team B cannot access Team A team or members by ID endpoint', function () {
        // Reuse the same Team B token from above scenario to also test team-level endpoints
        $teamB = Team::factory()->create(['name' => 'Cross Team B']);
        $userB = User::factory()->create();
        $teamB->members()->attach($userB->id, ['role' => 'owner']);

        session(['currentTeam' => $teamB]);
        Cache::flush();
        $tokenB = $userB->createToken('cross-b', ['*'])->plainTextToken;

        // Team B tries to access Team A by ID → 404
        $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson("/api/v1/teams/{$this->team->id}")
            ->assertStatus(404);

        // Team B tries to access Team A members by ID → 404
        $this->withHeaders(teamE2eHeaders($tokenB))
            ->getJson("/api/v1/teams/{$this->team->id}/members")
            ->assertStatus(404);
    });
});

// ─── Team with multiple members ──────────────────────────────────────────────

describe('Team with multiple members and role verification', function () {
    test('team with 3 members shows all via /current/members and /{id}/members with hidden sensitive fields', function () {
        // Add 2 more members with different roles
        $member = User::factory()->create([
            'name' => 'Regular Member',
            'email' => 'member@example.com',
        ]);
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $this->team->members()->attach($member->id, ['role' => 'member']);
        $this->team->members()->attach($admin->id, ['role' => 'admin']);

        // Step 1: Verify via /teams/current/members
        $currentResp = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/members');

        $currentResp->assertOk();
        $currentResp->assertJsonCount(3);
        $currentResp->assertJsonFragment(['name' => 'Team Owner']);
        $currentResp->assertJsonFragment(['name' => 'Regular Member']);
        $currentResp->assertJsonFragment(['name' => 'Admin User']);

        // Verify sensitive fields hidden
        foreach ($currentResp->json() as $m) {
            expect($m)->not->toHaveKey('pivot');
            expect($m)->not->toHaveKey('email_change_code');
            expect($m)->not->toHaveKey('email_change_code_expires_at');
        }

        // Step 2: Verify via /teams/{id}/members — same result
        $byIdResp = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson("/api/v1/teams/{$this->team->id}/members");

        $byIdResp->assertOk();
        $byIdResp->assertJsonCount(3);
        $byIdResp->assertJsonFragment(['name' => 'Team Owner']);
        $byIdResp->assertJsonFragment(['name' => 'Regular Member']);
        $byIdResp->assertJsonFragment(['name' => 'Admin User']);

        foreach ($byIdResp->json() as $m) {
            expect($m)->not->toHaveKey('pivot');
            expect($m)->not->toHaveKey('email_change_code');
        }
    });
});

// ─── Activity audit trail lifecycle ──────────────────────────────────────────

describe('Activity audit trail lifecycle', function () {
    test('create → update → delete cycle produces activity entries with correct user info', function () {
        // Create activities simulating a project lifecycle
        $project = Project::factory()->create(['team_id' => $this->team->id, 'name' => 'Audit Project']);

        createActivity($this->user, 'created', 'Project "Audit Project" was created', $project);
        createActivity($this->user, 'updated', 'Project "Audit Project" was updated', $project, [
            'changes' => ['name' => ['old' => 'Old Name', 'new' => 'Audit Project']],
        ]);
        createActivity($this->user, 'deleted', 'Project "Audit Project" was deleted', $project);

        // Fetch activities
        $response = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/activities');

        $response->assertOk();
        $data = $response->json('data');

        // Should have at least 3 entries (may have more from model observers)
        expect(count($data))->toBeGreaterThanOrEqual(3);

        // Verify user info on each activity
        foreach ($data as $activity) {
            expect($activity['user']['name'])->toBe('Team Owner');
            expect($activity['user']['email'])->toBe('owner@example.com');
            expect($activity)->toHaveKey('timestamp');
            expect($activity)->toHaveKey('id');
        }

        // Verify all three event types are present in the descriptions
        $descriptions = collect($data)->pluck('description')->toArray();
        expect($descriptions)->toContain('Project "Audit Project" was created');
        expect($descriptions)->toContain('Project "Audit Project" was updated');
        expect($descriptions)->toContain('Project "Audit Project" was deleted');
    });

    test('activities include resource info and multiple team members activities appear together', function () {
        $project = Project::factory()->create(['team_id' => $this->team->id, 'name' => 'Resource Project']);
        $member = User::factory()->create(['name' => 'Another Member', 'email' => 'another@example.com']);
        $this->team->members()->attach($member->id, ['role' => 'admin']);

        // Activities from both owner and member
        createActivity($this->user, 'created', 'Project created', $project);
        createActivity($member, 'updated', 'Member updated a resource');

        $response = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/activities');

        $response->assertOk();
        $data = $response->json('data');

        // Verify resource info on owner's activity
        $ownerActivity = collect($data)->first(fn ($a) => $a['description'] === 'Project created');
        expect($ownerActivity)->not->toBeNull();
        expect($ownerActivity['resource'])->not->toBeNull();
        expect($ownerActivity['resource']['type'])->toBe('project');
        expect($ownerActivity['resource']['name'])->toBe('Resource Project');

        // Verify member's activity also appears with correct attribution
        $memberActivity = collect($data)->first(fn ($a) => $a['description'] === 'Member updated a resource');
        expect($memberActivity)->not->toBeNull();
        expect($memberActivity['user']['name'])->toBe('Another Member');
        expect($memberActivity['user']['email'])->toBe('another@example.com');
    });
});

// ─── Activity date filtering accuracy ────────────────────────────────────────

describe('Activity date filtering accuracy', function () {
    test('filter by today returns today activities only', function () {
        // Create activity for today
        createActivity($this->user, 'created', 'Today activity');

        // Create old activity manually
        $oldActivity = new Activity;
        $oldActivity->log_name = 'default';
        $oldActivity->description = 'Old activity from last month';
        $oldActivity->event = 'updated';
        $oldActivity->causer_type = 'App\\Models\\User';
        $oldActivity->causer_id = $this->user->id;
        $oldActivity->save();
        // Manually backdate
        Activity::where('id', $oldActivity->id)->update(['created_at' => now()->subDays(45)]);

        // Filter by today
        $response = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/activities?date_range=today');

        $response->assertOk();
        $data = $response->json('data');
        $descriptions = collect($data)->pluck('description')->toArray();
        expect($descriptions)->toContain('Today activity');
        expect($descriptions)->not->toContain('Old activity from last month');
    });

    test('filter by week returns activities from last 7 days', function () {
        createActivity($this->user, 'created', 'Recent week activity');

        $oldActivity = new Activity;
        $oldActivity->log_name = 'default';
        $oldActivity->description = 'Old activity from 2 weeks ago';
        $oldActivity->event = 'updated';
        $oldActivity->causer_type = 'App\\Models\\User';
        $oldActivity->causer_id = $this->user->id;
        $oldActivity->save();
        Activity::where('id', $oldActivity->id)->update(['created_at' => now()->subDays(15)]);

        $response = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/activities?date_range=week');

        $response->assertOk();
        $data = $response->json('data');
        $descriptions = collect($data)->pluck('description')->toArray();
        expect($descriptions)->toContain('Recent week activity');
        expect($descriptions)->not->toContain('Old activity from 2 weeks ago');
    });

    test('custom date range in export filters correctly', function () {
        createActivity($this->user, 'created', 'Activity in range');

        $oldActivity = new Activity;
        $oldActivity->log_name = 'default';
        $oldActivity->description = 'Activity out of range';
        $oldActivity->event = 'updated';
        $oldActivity->causer_type = 'App\\Models\\User';
        $oldActivity->causer_id = $this->user->id;
        $oldActivity->save();
        Activity::where('id', $oldActivity->id)->update(['created_at' => now()->subYear()]);

        $dateFrom = now()->subDays(1)->format('Y-m-d');
        $dateTo = now()->addDay()->format('Y-m-d');

        $response = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson("/api/v1/teams/current/activities/export?format=json&date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertOk();
        $data = $response->json('data');
        $descriptions = collect($data)->pluck('description')->toArray();
        expect($descriptions)->toContain('Activity in range');
        expect($descriptions)->not->toContain('Activity out of range');
    });
});

// ─── Activity export with real data ──────────────────────────────────────────

describe('Activity export with real data', function () {
    test('JSON export contains all activity entries with correct structure', function () {
        // Create several activities
        $project = Project::factory()->create(['team_id' => $this->team->id, 'name' => 'Export Project']);

        createActivity($this->user, 'created', 'Project created for export', $project);
        createActivity($this->user, 'updated', 'Project updated for export', $project);
        createActivity($this->user, 'deleted', 'Project deleted for export', $project);

        $response = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/activities/export?format=json');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'timestamp',
                    'user_name',
                    'user_email',
                    'action',
                    'description',
                    'resource_type',
                    'resource_name',
                    'properties',
                ],
            ],
        ]);

        $data = $response->json('data');
        expect(count($data))->toBeGreaterThanOrEqual(3);

        // Verify our specific entries exist
        $descriptions = collect($data)->pluck('description')->toArray();
        expect($descriptions)->toContain('Project created for export');
        expect($descriptions)->toContain('Project updated for export');
        expect($descriptions)->toContain('Project deleted for export');

        // Verify Content-Disposition header for JSON download
        expect($response->headers->get('Content-Disposition'))->toContain('attachment');
        expect($response->headers->get('Content-Disposition'))->toContain('audit-log-');
        expect($response->headers->get('Content-Disposition'))->toContain('.json');
    });

    test('CSV export returns correct Content-Type and attachment header', function () {
        createActivity($this->user, 'created', 'CSV export test activity');

        $response = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->get('/api/v1/teams/current/activities/export?format=csv');

        $response->assertOk();
        // Content-Type may include charset depending on Laravel version
        $contentType = $response->headers->get('Content-Type');
        expect($contentType)->toContain('text/csv');
        expect($response->headers->get('Content-Disposition'))->toContain('attachment');
        expect($response->headers->get('Content-Disposition'))->toContain('audit-log-');
        expect($response->headers->get('Content-Disposition'))->toContain('.csv');
    });

    test('JSON export filtered by action only includes matching activities', function () {
        createActivity($this->user, 'created', 'Created activity for filter');
        createActivity($this->user, 'updated', 'Updated activity for filter');
        createActivity($this->user, 'deleted', 'Deleted activity for filter');

        $response = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/activities/export?format=json&action=created');

        $response->assertOk();
        $data = $response->json('data');
        $actions = collect($data)->pluck('action')->unique()->toArray();

        // All returned entries should have 'created' as their event
        foreach ($data as $entry) {
            expect($entry['action'])->toBe('created');
        }
    });
});

// ─── Activity filtering by action, member, and search ────────────────────────

describe('Activity filtering by action, member, and search', function () {
    test('combined filters: action, member email, and search all scope results correctly', function () {
        $member = User::factory()->create(['name' => 'Filter Member', 'email' => 'filter@example.com']);
        $this->team->members()->attach($member->id, ['role' => 'member']);

        // Create diverse activities
        createActivity($this->user, 'created', 'Owner created something');
        createActivity($this->user, 'updated', 'Owner updated something');
        createActivity($member, 'created', 'Member created something');
        createActivity($this->user, 'created', 'Deployed microservice alpha');
        createActivity($this->user, 'updated', 'Updated database config');

        // Step 1: Filter by action=created — only created events
        $byAction = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/activities?action=created');
        $byAction->assertOk();
        $actionDescs = collect($byAction->json('data'))->pluck('description')->toArray();
        expect($actionDescs)->toContain('Owner created something');
        expect($actionDescs)->not->toContain('Owner updated something');

        // Step 2: Filter by member email — only that member's activities
        $byMember = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/activities?member=filter@example.com');
        $byMember->assertOk();
        $memberDescs = collect($byMember->json('data'))->pluck('description')->toArray();
        expect($memberDescs)->toContain('Member created something');
        expect($memberDescs)->not->toContain('Owner created something');

        // Step 3: Search by keyword — only matching descriptions
        $bySearch = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/activities?search=microservice');
        $bySearch->assertOk();
        $searchDescs = collect($bySearch->json('data'))->pluck('description')->toArray();
        expect($searchDescs)->toContain('Deployed microservice alpha');
        expect($searchDescs)->not->toContain('Updated database config');
    });
});

// ─── Team member role-based access ───────────────────────────────────────────

describe('Team member role-based access', function () {
    test('member with read token can read team data but not write resources', function () {
        // Create a member user
        $member = User::factory()->create(['name' => 'Read Member', 'email' => 'readmember@example.com']);
        $this->team->members()->attach($member->id, ['role' => 'member']);

        // Create a read-only token for the member
        session(['currentTeam' => $this->team]);
        Cache::flush();
        $readToken = $member->createToken('member-read', ['read'])->plainTextToken;

        // Member can read teams
        $this->withHeaders(teamE2eHeaders($readToken))
            ->getJson('/api/v1/teams')
            ->assertOk();

        // Member can read current team
        $this->withHeaders(teamE2eHeaders($readToken))
            ->getJson('/api/v1/teams/current')
            ->assertOk();

        // Member can read team members
        $this->withHeaders(teamE2eHeaders($readToken))
            ->getJson('/api/v1/teams/current/members')
            ->assertOk();

        // Member can read activities
        $this->withHeaders(teamE2eHeaders($readToken))
            ->getJson('/api/v1/teams/current/activities')
            ->assertOk();

        // Member cannot write (create project) with read token
        $this->withHeaders(teamE2eHeaders($readToken))
            ->postJson('/api/v1/projects', ['name' => 'Unauthorized Project'])
            ->assertStatus(403);
    });

    test('owner with wildcard token has full access to team endpoints', function () {
        $infra = createTeamInfrastructure($this->team);
        $app = createAppInInfra($infra, 'Owner App');

        // Owner can read
        $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current')
            ->assertOk();

        // Owner can list applications
        $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/applications')
            ->assertOk();

        // Owner can update application
        $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->patchJson("/api/v1/applications/{$app->uuid}", ['name' => 'Updated by Owner'])
            ->assertOk();

        // Verify update persisted
        $app->refresh();
        expect($app->name)->toBe('Updated by Owner');
    });

    test('admin member can read team data with read token', function () {
        $admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@example.com']);
        $this->team->members()->attach($admin->id, ['role' => 'admin']);

        session(['currentTeam' => $this->team]);
        Cache::flush();
        $adminReadToken = $admin->createToken('admin-read', ['read'])->plainTextToken;

        // Admin can read current team
        $resp = $this->withHeaders(teamE2eHeaders($adminReadToken))
            ->getJson('/api/v1/teams/current');
        $resp->assertOk();
        $resp->assertJsonFragment(['name' => 'Primary Team']);

        // Admin can read members
        $membersResp = $this->withHeaders(teamE2eHeaders($adminReadToken))
            ->getJson('/api/v1/teams/current/members');
        $membersResp->assertOk();
        $names = collect($membersResp->json())->pluck('name')->toArray();
        expect($names)->toContain('Team Owner');
        expect($names)->toContain('Admin User');
    });
});

// ─── Sensitive field hiding across all team endpoints ─────────────────────────

describe('Sensitive field hiding across all team endpoints', function () {
    test('sensitive fields hidden consistently in /teams, /teams/current, and /teams/{id}', function () {
        $this->team->update([
            'smtp_username' => 'smtp_test_user',
            'smtp_password' => 'smtp_test_pass',
            'resend_api_key' => 'resend_test_key',
            'telegram_token' => 'tg_test_token',
        ]);

        // Check all three endpoints return consistent hidden fields
        $endpoints = [
            '/api/v1/teams',
            '/api/v1/teams/current',
            "/api/v1/teams/{$this->team->id}",
        ];

        foreach ($endpoints as $endpoint) {
            $resp = $this->withHeaders(teamE2eHeaders($this->bearerToken))
                ->getJson($endpoint);
            $resp->assertOk();

            // For /teams (list), extract the team entry; for others use root
            $data = str_contains($endpoint, '/teams/current') || str_contains($endpoint, (string) $this->team->id)
                ? $resp->json()
                : collect($resp->json())->firstWhere('name', 'Primary Team');

            expect($data)->not->toHaveKey('smtp_password');
            expect($data)->not->toHaveKey('resend_api_key');
            expect($data)->not->toHaveKey('telegram_token');
            expect($data)->not->toHaveKey('custom_server_limit');
        }
    });
});

// ─── Activity pagination verification ────────────────────────────────────────

describe('Activity pagination verification', function () {
    test('per_page controls page size, meta is correct, and cap at 100 is enforced', function () {
        // Create 8 activities
        for ($i = 1; $i <= 8; $i++) {
            createActivity($this->user, 'created', "Pagination activity {$i}");
        }

        // Step 1: Request with per_page=3
        $response = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/activities?per_page=3');

        $response->assertOk();
        $data = $response->json('data');
        $meta = $response->json('meta');

        expect(count($data))->toBe(3);
        expect($meta['per_page'])->toBe(3);
        expect($meta['total'])->toBeGreaterThanOrEqual(8);
        expect($meta['current_page'])->toBe(1);
        expect($meta['last_page'])->toBeGreaterThanOrEqual(3);

        // Step 2: Verify per_page is capped at 100
        $capResp = $this->withHeaders(teamE2eHeaders($this->bearerToken))
            ->getJson('/api/v1/teams/current/activities?per_page=500');

        $capResp->assertOk();
        expect($capResp->json('meta.per_page'))->toBeLessThanOrEqual(100);
    });
});
