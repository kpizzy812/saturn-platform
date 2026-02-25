<?php

use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::factory()->create(['id' => 0]);

    $this->team = Team::factory()->create();

    // Regular user (not instance admin)
    $this->regularUser = User::factory()->create([
        'is_superadmin' => false,
        'platform_role' => null,
    ]);
    $this->team->members()->attach($this->regularUser->id, ['role' => 'owner']);

    // Instance admin
    $this->adminUser = User::factory()->create([
        'is_superadmin' => true,
    ]);
    $this->team->members()->attach($this->adminUser->id, ['role' => 'owner']);
});

describe('POST /settings/security/ip-allowlist', function () {
    test('non-admin cannot add IP to allowlist', function () {
        $this->actingAs($this->regularUser);
        session(['currentTeam' => $this->team]);

        $this->postJson('/settings/security/ip-allowlist', [
            'ip_address' => '192.168.1.1',
            'description' => 'Test IP',
        ])->assertStatus(403);
    });

    test('instance admin can add IP to allowlist (not 403)', function () {
        $this->actingAs($this->adminUser);
        session(['currentTeam' => $this->team]);

        expect($this->postJson('/settings/security/ip-allowlist', [
            'ip_address' => '192.168.1.1',
            'description' => 'Test IP',
        ])->status())->not->toBe(403);
    });
});

describe('DELETE /settings/security/ip-allowlist/{id}', function () {
    test('non-admin cannot remove IP from allowlist', function () {
        $this->actingAs($this->regularUser);
        session(['currentTeam' => $this->team]);

        $this->deleteJson('/settings/security/ip-allowlist/0')
            ->assertStatus(403);
    });

    test('instance admin can remove IP from allowlist (not 403)', function () {
        $this->actingAs($this->adminUser);
        session(['currentTeam' => $this->team]);

        // First add an IP so we can try to delete
        $settings = InstanceSettings::get();
        $settings->update(['allowed_ips' => json_encode([
            ['ip' => '10.0.0.1', 'description' => 'test', 'created_at' => now()->toISOString()],
        ])]);

        expect($this->deleteJson('/settings/security/ip-allowlist/0')->status())
            ->not->toBe(403);
    });
});
