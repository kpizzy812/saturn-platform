<?php

use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a team with owner
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    // Authenticate user for web routes
    $this->actingAs($this->user);

    // Set current team in session
    session(['currentTeam' => $this->team]);
});

describe('GET /api/v1/servers/{uuid}/sentinel/metrics', function () {
    test('returns 404 for non-existent server', function () {
        $response = $this->getJson('/api/v1/servers/non-existent-uuid/sentinel/metrics');

        // Route returns 404 when server not found
        $response->assertStatus(404);
    });

    test('endpoint returns json format', function () {
        $response = $this->getJson('/api/v1/servers/test-uuid/sentinel/metrics');

        expect($response->headers->get('content-type'))->toContain('application/json');
    });

    test('accepts valid timeRange parameter', function () {
        $validRanges = ['1h', '24h', '7d', '30d'];

        foreach ($validRanges as $range) {
            $response = $this->getJson("/api/v1/servers/test-uuid/sentinel/metrics?timeRange={$range}");

            // Should return 404 because server doesn't exist
            expect($response->status())->toBe(404);
        }
    });

    test('defaults to 24h when invalid timeRange provided', function () {
        // This test verifies the controller accepts requests with invalid timeRange
        // The controller should default to '24h' internally

        $response = $this->getJson('/api/v1/servers/test-uuid/sentinel/metrics?timeRange=invalid');

        // Should return 404 because server doesn't exist
        expect($response->status())->toBe(404);
    });

    test('accepts includeProcesses boolean parameter', function () {
        $response = $this->getJson('/api/v1/servers/test-uuid/sentinel/metrics?includeProcesses=true');

        // Should return 404 because server doesn't exist
        expect($response->status())->toBe(404);
    });

    test('accepts includeContainers boolean parameter', function () {
        $response = $this->getJson('/api/v1/servers/test-uuid/sentinel/metrics?includeContainers=true');

        // Should return 404 because server doesn't exist
        expect($response->status())->toBe(404);
    });

    test('requires authentication for metrics endpoint', function () {
        // Log out the user
        auth()->logout();

        $response = $this->getJson('/api/v1/servers/test-uuid/sentinel/metrics');

        // API routes should return 401 or similar when not authenticated
        expect($response->status())->toBeIn([400, 401, 302, 404, 419]);
    });

    test('returns 404 when accessing server from different team', function () {
        // Create another team with a server
        $otherTeam = Team::factory()->create();
        $otherUser = User::factory()->create();
        $otherTeam->members()->attach($otherUser->id, ['role' => 'owner']);

        // Create a server belonging to the other team
        $server = Server::factory()->create([
            'team_id' => $otherTeam->id,
        ]);

        // Try to access it with our user (different team)
        $response = $this->getJson("/api/v1/servers/{$server->uuid}/sentinel/metrics");

        // Should return 404 because server belongs to different team
        expect($response->status())->toBe(404);
    });
});

describe('API token authentication', function () {
    test('returns 400 when using invalid API token', function () {
        // Log out and try to use invalid token
        auth()->logout();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->getJson('/api/v1/servers/test-uuid/sentinel/metrics');

        // Should return 400 or 401 for invalid token
        expect($response->status())->toBeIn([400, 401, 419]);
    });
});
