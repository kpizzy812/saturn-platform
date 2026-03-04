<?php

use App\Models\CliAuthSession;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

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

describe('POST /api/v1/cli/auth/init', function () {
    test('creates a new CLI auth session and returns code and secret', function () {
        $response = $this->postJson('/api/v1/cli/auth/init');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'secret',
                'verification_url',
                'expires_in',
            ]);

        $data = $response->json();
        expect($data['expires_in'])->toBe(300);
        expect($data['code'])->toMatch('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/');
        expect(strlen($data['secret']))->toBe(40);
    });

    test('stores session in database after init', function () {
        $response = $this->postJson('/api/v1/cli/auth/init');
        $response->assertStatus(200);

        $code = $response->json('code');
        $this->assertDatabaseHas('cli_auth_sessions', [
            'code' => $code,
            'status' => 'pending',
        ]);
    });

    test('verification_url contains the code', function () {
        $response = $this->postJson('/api/v1/cli/auth/init');
        $response->assertStatus(200);

        $data = $response->json();
        expect($data['verification_url'])->toContain($data['code']);
    });
});

describe('GET /api/v1/cli/auth/check', function () {
    test('returns 404 when session not found', function () {
        $response = $this->getJson('/api/v1/cli/auth/check?secret=nonexistent-secret-that-does-not-exist-at-all');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Session not found.']);
    });

    test('returns pending status for new session', function () {
        $initResponse = $this->postJson('/api/v1/cli/auth/init');
        $secret = $initResponse->json('secret');

        $response = $this->getJson('/api/v1/cli/auth/check?secret='.$secret);

        $response->assertStatus(200)
            ->assertJson(['status' => 'pending']);
    });

    test('returns expired status for expired session', function () {
        $session = CliAuthSession::create([
            'code' => 'XXXX-YYYY',
            'secret' => 'expired-secret-token-test-string-here-xx',
            'status' => 'pending',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'expires_at' => now()->subMinutes(10),
        ]);

        $response = $this->getJson('/api/v1/cli/auth/check?secret='.$session->secret);

        $response->assertStatus(200)
            ->assertJson(['status' => 'expired']);
    });

    test('returns denied status for denied session', function () {
        $session = CliAuthSession::create([
            'code' => 'DENI-EDDD',
            'secret' => 'denied-secret-token-test-string-here-xxx',
            'status' => 'denied',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->getJson('/api/v1/cli/auth/check?secret='.$session->secret);

        $response->assertStatus(200)
            ->assertJson(['status' => 'denied']);
    });

    test('returns token and clears it on approved status', function () {
        $session = CliAuthSession::create([
            'code' => 'APPR-OVED',
            'secret' => 'approved-secret-token-test-string-here-x',
            'status' => 'approved',
            'token_plain' => 'myplaintoken',
            'user_id' => $this->user->id,
            'team_id' => $this->team->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->getJson('/api/v1/cli/auth/check?secret='.$session->secret);

        $response->assertStatus(200)
            ->assertJson(['status' => 'approved'])
            ->assertJsonStructure(['token', 'team_name', 'user_name']);

        // Token should be cleared after retrieval
        $this->assertDatabaseHas('cli_auth_sessions', [
            'id' => $session->id,
            'token_plain' => null,
        ]);
    });
});
