<?php

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    // Ensure InstanceSettings exists for ApiAllowed middleware.
    // Eloquent create() ignores explicit id=0 on PostgreSQL bigserial columns;
    // use raw DB insert to guarantee the singleton record exists.
    if (! DB::table('instance_settings')->where('id', 0)->exists()) {
        DB::table('instance_settings')->insert([
            'id' => 0,
            'is_api_enabled' => true,
            'is_registration_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
});

describe('GET /api/health - Healthcheck (no auth required)', function () {
    test('returns 200 without authentication via /api/health', function () {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'checks']);
    });

    test('returns 200 via versioned path without authentication', function () {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'checks']);
    });

    test('returns 200 with authentication', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'checks']);
    });
});

describe('GET /api/v1/version - Get version', function () {
    test('rejects request without authentication', function () {
        $response = $this->getJson('/api/v1/version');
        $response->assertStatus(401);
    });

    test('rejects request with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-12345',
        ])->getJson('/api/v1/version');

        $response->assertStatus(401);
    });

    test('returns version string with valid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->get('/api/v1/version');

        $response->assertStatus(200);
        $content = $response->getContent();
        // Version is a non-empty string
        expect($content)->not->toBeEmpty();
    });

    test('read-only token can access version', function () {
        $limitedToken = $this->user->createToken('read-only-token', ['read']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$limitedToken->plainTextToken,
        ])->get('/api/v1/version');

        $response->assertStatus(200);
        expect($response->getContent())->not->toBeEmpty();
    });
});

describe('GET /api/v1/enable - Enable API', function () {
    test('rejects request without authentication', function () {
        $response = $this->getJson('/api/v1/enable');
        $response->assertStatus(401);
    });

    test('returns 403 for non-root team token', function () {
        // Regular team tokens have teamId != '0', so they are forbidden
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/enable');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You are not allowed to enable the API.']);
    });

    test('rejects request with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-12345',
        ])->getJson('/api/v1/enable');

        $response->assertStatus(401);
    });
});

describe('GET /api/v1/disable - Disable API', function () {
    test('rejects request without authentication', function () {
        $response = $this->getJson('/api/v1/disable');
        $response->assertStatus(401);
    });

    test('returns 403 for non-root team token', function () {
        // Regular team tokens have teamId != '0', so they are forbidden
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/disable');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You are not allowed to disable the API.']);
    });

    test('rejects request with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token-12345',
        ])->getJson('/api/v1/disable');

        $response->assertStatus(401);
    });
});

describe('API state', function () {
    test('returns 200 when API is enabled', function () {
        $settings = InstanceSettings::find(0);
        $settings->update(['is_api_enabled' => true]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->getJson('/api/v1/version');

        $response->assertStatus(200);
    });
});

describe('POST /api/feedback - Feedback endpoint (requires auth)', function () {
    test('rejects feedback without authentication', function () {
        $response = $this->postJson('/api/feedback', [
            'content' => 'Test feedback message',
        ]);

        $response->assertStatus(401);
    });

    test('accepts feedback with valid authentication', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/feedback', [
            'content' => 'Test feedback message',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Feedback sent.']);
    });

    test('rejects empty feedback content with 422 validation error', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
        ])->postJson('/api/feedback', [
            'content' => '',
        ]);

        $response->assertStatus(422);
    });
});
