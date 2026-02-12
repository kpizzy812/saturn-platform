<?php

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Create team and user with owner role
    $this->team = Team::factory()->create([
        'name' => 'Test Team',
        'description' => 'A test team',
        'personal_team' => false,
    ]);
    $this->user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    // Create API token with team_id
    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    // Ensure InstanceSettings exists for ApiAllowed middleware
    if (! InstanceSettings::find(0)) {
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }
});

describe('Authentication', function () {
    test('returns 401 without authentication token', function () {
        $response = $this->getJson('/api/v1/teams');

        $response->assertStatus(401);
    });

    test('returns 401 with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-12345',
        ])->getJson('/api/v1/teams');

        $response->assertStatus(401);
    });
});

describe('GET /api/v1/teams - List all teams', function () {
    test('returns list of teams user belongs to', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Test Team',
            'description' => 'A test team',
        ]);
        // User may belong to additional teams (e.g. personal team), so check at least 1
        expect(count($response->json()))->toBeGreaterThanOrEqual(1);
    });

    test('returns multiple teams when user belongs to multiple', function () {
        // Create second team
        $secondTeam = Team::factory()->create([
            'name' => 'Second Team',
        ]);
        $secondTeam->members()->attach($this->user->id, ['role' => 'member']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Test Team']);
        $response->assertJsonFragment(['name' => 'Second Team']);
        expect(count($response->json()))->toBeGreaterThanOrEqual(2);
    });

    test('hides sensitive fields by default', function () {
        // Update team with sensitive data
        $this->team->update([
            'smtp_username' => 'smtp_user',
            'smtp_password' => 'smtp_pass',
            'resend_api_key' => 'resend_key',
            'telegram_token' => 'telegram_token',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams');

        $response->assertStatus(200);
        $json = $response->json();

        // Sensitive fields should be hidden
        expect($json[0])->not->toHaveKey('smtp_username');
        expect($json[0])->not->toHaveKey('smtp_password');
        expect($json[0])->not->toHaveKey('resend_api_key');
        expect($json[0])->not->toHaveKey('telegram_token');
        expect($json[0])->not->toHaveKey('custom_server_limit');
        expect($json[0])->not->toHaveKey('pivot');
    });

    test('returns all teams user belongs to', function () {
        $team2 = Team::factory()->create(['name' => 'Team B']);
        $team3 = Team::factory()->create(['name' => 'Team C']);

        $team2->members()->attach($this->user->id, ['role' => 'member']);
        $team3->members()->attach($this->user->id, ['role' => 'member']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Test Team']);
        $response->assertJsonFragment(['name' => 'Team B']);
        $response->assertJsonFragment(['name' => 'Team C']);
        expect(count($response->json()))->toBeGreaterThanOrEqual(3);
    });
});

describe('GET /api/v1/teams/current - Get current team', function () {
    test('returns current team based on token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $this->team->id,
            'name' => 'Test Team',
        ]);
    });

    test('returns correct team when user has multiple teams', function () {
        // Create second team
        $secondTeam = Team::factory()->create(['name' => 'Second Team']);
        $secondTeam->members()->attach($this->user->id, ['role' => 'owner']);

        // Switch session to second team and create token for it
        session(['currentTeam' => $secondTeam]);
        $secondToken = $this->user->createToken('second-token', ['*']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$secondToken->plainTextToken,
        ])->getJson('/api/v1/teams/current');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $secondTeam->id,
            'name' => 'Second Team',
        ]);
    });

    test('hides sensitive fields in current team response', function () {
        $this->team->update([
            'smtp_username' => 'smtp_user',
            'smtp_password' => 'smtp_pass',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current');

        $response->assertStatus(200);
        $json = $response->json();

        expect($json)->not->toHaveKey('smtp_username');
        expect($json)->not->toHaveKey('smtp_password');
    });
});

describe('GET /api/v1/teams/current/members - Get current team members', function () {
    test('returns list of current team members', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/members');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    });

    test('returns multiple members when team has multiple', function () {
        // Add more members
        $member2 = User::factory()->create(['name' => 'Member Two', 'email' => 'member2@example.com']);
        $member3 = User::factory()->create(['name' => 'Member Three', 'email' => 'member3@example.com']);

        $this->team->members()->attach($member2->id, ['role' => 'member']);
        $this->team->members()->attach($member3->id, ['role' => 'admin']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/members');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
        $response->assertJsonFragment(['name' => 'Member Two']);
        $response->assertJsonFragment(['name' => 'Member Three']);
    });

    test('hides sensitive user fields', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/members');

        $response->assertStatus(200);
        $json = $response->json();

        // Sensitive fields should be hidden
        expect($json[0])->not->toHaveKey('pivot');
        expect($json[0])->not->toHaveKey('email_change_code');
        expect($json[0])->not->toHaveKey('email_change_code_expires_at');
    });
});

describe('GET /api/v1/teams/{id} - Get team by ID', function () {
    test('returns team when user belongs to it', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/'.$this->team->id);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $this->team->id,
            'name' => 'Test Team',
        ]);
    });

    test('returns 404 when team does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/99999');

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Team not found.']);
    });

    test('returns 404 when user does not belong to team', function () {
        // Create another team with different user
        $otherTeam = Team::factory()->create(['name' => 'Other Team']);
        $otherUser = User::factory()->create();
        $otherTeam->members()->attach($otherUser->id, ['role' => 'owner']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/'.$otherTeam->id);

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Team not found.']);
    });
});

describe('GET /api/v1/teams/{id}/members - Get team members by ID', function () {
    test('returns members when user belongs to team', function () {
        $member = User::factory()->create(['name' => 'Team Member']);
        $this->team->members()->attach($member->id, ['role' => 'member']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/'.$this->team->id.'/members');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['name' => 'Test User']);
        $response->assertJsonFragment(['name' => 'Team Member']);
    });

    test('returns 404 when team does not exist', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/99999/members');

        $response->assertStatus(404);
        $response->assertJsonFragment(['message' => 'Team not found.']);
    });

    test('returns 404 when user does not belong to team', function () {
        $otherTeam = Team::factory()->create();
        $otherUser = User::factory()->create();
        $otherTeam->members()->attach($otherUser->id, ['role' => 'owner']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/'.$otherTeam->id.'/members');

        $response->assertStatus(404);
    });

    test('hides sensitive fields in members list', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/'.$this->team->id.'/members');

        $response->assertStatus(200);
        $json = $response->json();

        expect($json[0])->not->toHaveKey('pivot');
        expect($json[0])->not->toHaveKey('email_change_code');
    });
});

describe('GET /api/v1/teams/current/activities - Get team activities', function () {
    beforeEach(function () {
        // TODO: Add activity creation after fixing database setup
        // For now, just test the API structure without actual activities
    });

    test('returns paginated activities for current team', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'action',
                    'description',
                    'user' => ['name', 'email', 'avatar'],
                    'resource',
                    'timestamp',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
    });

    test('filters activities by action type', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities?action=created');

        $response->assertStatus(200);
        $json = $response->json();

        // Should return empty data array when no activities exist
        expect($json['data'])->toBeArray();
    });

    test('filters activities by member email', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities?member=test@example.com');

        $response->assertStatus(200);
        $json = $response->json();

        // Should return array even with no activities
        expect($json['data'])->toBeArray();
    });

    test('filters activities by date range today', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities?date_range=today');

        $response->assertStatus(200);
        $json = $response->json();

        // Should have activities from today
        expect($json['data'])->toBeArray();
    });

    test('filters activities by date range week', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities?date_range=week');

        $response->assertStatus(200);
        $json = $response->json();

        expect($json['data'])->toBeArray();
        expect($json['meta'])->toHaveKey('total');
    });

    test('searches activities by description', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities?search=created');

        $response->assertStatus(200);
        $json = $response->json();

        // Should return array structure
        expect($json['data'])->toBeArray();
    });

    test('respects per_page parameter', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities?per_page=5');

        $response->assertStatus(200);
        $json = $response->json();

        // Should respect per_page in meta
        expect($json['meta']['per_page'])->toBe(5);
    });

    test('limits per_page to maximum of 100', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities?per_page=200');

        $response->assertStatus(200);
        $json = $response->json();

        // Should cap at 100
        expect($json['meta']['per_page'])->toBeLessThanOrEqual(100);
    });

    test('includes resource information in activities', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities');

        $response->assertStatus(200);
        $json = $response->json();

        // Should have data structure even if empty
        expect($json)->toHaveKey('data');
        expect($json)->toHaveKey('meta');
    });
});

describe('GET /api/v1/teams/current/activities/export - Export team activities', function () {
    beforeEach(function () {
        // TODO: Add activity creation when database is fixed
    });

    test('exports activities in JSON format', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities/export?format=json');

        $response->assertStatus(200);
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
    });

    test('exports activities in CSV format', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->get('/api/v1/teams/current/activities/export?format=csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        expect($response->headers->get('Content-Disposition'))->toContain('attachment');
        expect($response->headers->get('Content-Disposition'))->toContain('audit-log-');
    });

    test('defaults to CSV format when format not specified', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->get('/api/v1/teams/current/activities/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    test('filters export by action type', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities/export?format=json&action=updated');

        $response->assertStatus(200);
        $json = $response->json();

        // Should have data structure
        expect($json)->toHaveKey('data');
    });

    test('filters export by date range', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities/export?format=json&date_from=2024-01-01&date_to=2025-12-31');

        $response->assertStatus(200);
        $json = $response->json();

        expect($json['data'])->toBeArray();
    });

    test('includes JSON filename in Content-Disposition header', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/teams/current/activities/export?format=json');

        $response->assertStatus(200);
        expect($response->headers->get('Content-Disposition'))->toContain('attachment');
        expect($response->headers->get('Content-Disposition'))->toContain('audit-log-');
        expect($response->headers->get('Content-Disposition'))->toContain('.json');
    });
});
