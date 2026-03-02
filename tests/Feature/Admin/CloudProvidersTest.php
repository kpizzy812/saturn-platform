<?php

use App\Models\CloudProviderToken;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superadmin = User::factory()->create([
        'is_superadmin' => true,
        'status' => 'active',
    ]);

    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->superadmin->id, ['role' => 'owner']);

    $this->regularUser = User::factory()->create(['status' => 'active']);
    $this->regularTeam = Team::factory()->create();
    $this->regularTeam->members()->attach($this->regularUser->id, ['role' => 'member']);
});

describe('GET /admin/cloud-providers', function () {
    test('superadmin can view cloud providers index', function () {
        $this->actingAs($this->superadmin);

        $response = $this->get('/admin/cloud-providers');

        $response->assertStatus(200);
    });

    test('non-superadmin cannot access admin cloud providers', function () {
        $this->actingAs($this->regularUser);

        $response = $this->get('/admin/cloud-providers');

        // Should redirect (superadmin middleware redirects non-admins)
        $response->assertRedirect();
    });
});

describe('POST /admin/cloud-providers', function () {
    test('superadmin can create cloud token for any team', function () {
        $this->actingAs($this->superadmin);
        session(['currentTeam' => $this->team]);

        $otherTeam = Team::factory()->create();

        $response = $this->post('/admin/cloud-providers', [
            'team_id' => $otherTeam->id,
            'name' => 'Admin Hetzner Token',
            'provider' => 'hetzner',
            'token' => 'hetzner-api-key-12345',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('cloud_provider_tokens', [
            'team_id' => $otherTeam->id,
            'name' => 'Admin Hetzner Token',
            'provider' => 'hetzner',
        ]);
    });

    test('superadmin cannot create token with invalid provider', function () {
        $this->actingAs($this->superadmin);
        session(['currentTeam' => $this->team]);

        $response = $this->post('/admin/cloud-providers', [
            'team_id' => $this->team->id,
            'name' => 'Invalid Token',
            'provider' => 'invalid-provider',
            'token' => 'some-token',
        ]);

        $response->assertSessionHasErrors(['provider']);
    });
});

describe('DELETE /admin/cloud-providers/{uuid}', function () {
    test('superadmin can delete cloud token', function () {
        $this->actingAs($this->superadmin);
        session(['currentTeam' => $this->team]);

        $token = CloudProviderToken::factory()->create([
            'team_id' => $this->team->id,
            'provider' => 'hetzner',
        ]);

        $response = $this->delete("/admin/cloud-providers/{$token->uuid}");

        $response->assertRedirect();

        $this->assertDatabaseMissing('cloud_provider_tokens', [
            'uuid' => $token->uuid,
        ]);
    });
});

describe('POST /settings/cloud-tokens authorization', function () {
    test('team member without permission gets 403 on settings route', function () {
        $this->actingAs($this->regularUser);
        session(['currentTeam' => $this->regularTeam]);

        $response = $this->post('/settings/cloud-tokens', [
            'name' => 'Test Token',
            'provider' => 'hetzner',
            'token' => 'test-token-value',
        ]);

        $response->assertStatus(403);
    });
});
