<?php

use App\Models\InstanceSettings;
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

describe('GET /admin/settings (auto-provisioning data)', function () {
    test('superadmin can view admin settings with auto-provisioning data', function () {
        $this->actingAs($this->superadmin);
        session(['currentTeam' => $this->team]);

        $response = $this->get('/admin/settings');

        $response->assertStatus(200);
        // Inertia renders with these props
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/Index')
            ->has('autoProvisioning')
            ->has('cloudTokens')
        );
    });
});

describe('POST /admin/auto-provisioning', function () {
    test('superadmin can update auto-provisioning settings', function () {
        $this->actingAs($this->superadmin);
        session(['currentTeam' => $this->team]);

        $response = $this->post('/admin/auto-provisioning', [
            'auto_provision_enabled' => true,
            'auto_provision_max_servers_per_day' => 5,
            'auto_provision_cooldown_minutes' => 60,
            'resource_monitoring_enabled' => false,
            'resource_warning_cpu_threshold' => 80,
            'resource_critical_cpu_threshold' => 95,
            'resource_warning_memory_threshold' => 85,
            'resource_critical_memory_threshold' => 98,
        ]);

        $response->assertRedirect();

        $settings = InstanceSettings::get();
        expect($settings->auto_provision_enabled)->toBeTrue();
        expect($settings->auto_provision_max_servers_per_day)->toBe(5);
        expect($settings->auto_provision_cooldown_minutes)->toBe(60);
    });

    test('non-superadmin cannot post to admin auto-provisioning', function () {
        $this->actingAs($this->regularUser);
        session(['currentTeam' => $this->regularTeam]);

        $response = $this->post('/admin/auto-provisioning', [
            'auto_provision_enabled' => true,
            'auto_provision_max_servers_per_day' => 5,
            'auto_provision_cooldown_minutes' => 30,
            'resource_monitoring_enabled' => false,
            'resource_warning_cpu_threshold' => 75,
            'resource_critical_cpu_threshold' => 90,
            'resource_warning_memory_threshold' => 80,
            'resource_critical_memory_threshold' => 95,
        ]);

        // Should redirect due to is.superadmin middleware
        $response->assertRedirect();
        $response->assertRedirectContainsPath('/');
    });
});

describe('Redirect routes', function () {
    test('GET /settings/auto-provisioning redirects to admin settings', function () {
        $this->actingAs($this->superadmin);
        session(['currentTeam' => $this->team]);

        $response = $this->get('/settings/auto-provisioning');

        $response->assertRedirect('/admin/settings');
    });

    test('GET /settings/cloud-providers redirects to admin cloud providers', function () {
        $this->actingAs($this->superadmin);
        session(['currentTeam' => $this->team]);

        $response = $this->get('/settings/cloud-providers');

        $response->assertRedirect('/admin/cloud-providers');
    });
});
