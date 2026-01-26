<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a team with owner, admin, and member
    $this->team = Team::factory()->create([
        'name' => 'Test Workspace',
        'timezone' => 'UTC',
        'default_environment' => 'production',
    ]);

    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->member = User::factory()->create();

    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->team->members()->attach($this->admin->id, ['role' => 'admin']);
    $this->team->members()->attach($this->member->id, ['role' => 'member']);
});

describe('GET /settings/workspace', function () {
    test('authenticated user can view workspace settings', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->get('/settings/workspace');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Workspace')
            ->has('workspace')
            ->has('timezones')
            ->has('environmentOptions')
        );
    });

    test('workspace data contains expected fields', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->get('/settings/workspace');

        $response->assertInertia(fn ($page) => $page
            ->where('workspace.name', $this->team->name)
            ->where('workspace.timezone', 'UTC')
            ->where('workspace.defaultEnvironment', 'production')
            ->has('workspace.id')
            ->has('workspace.slug')
        );
    });

    test('timezones list contains valid timezones', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->get('/settings/workspace');

        $response->assertInertia(fn ($page) => $page
            ->where('timezones', function ($timezones) {
                // Convert to array if Collection
                $tzArray = is_array($timezones) ? $timezones : $timezones->toArray();

                // Check that common timezones are present
                return in_array('UTC', $tzArray)
                    && in_array('America/New_York', $tzArray)
                    && in_array('Europe/London', $tzArray)
                    && in_array('Asia/Tokyo', $tzArray);
            })
        );
    });

    test('unauthenticated user is redirected to login', function () {
        $response = $this->get('/settings/workspace');

        $response->assertRedirect('/login');
    });
});

describe('POST /settings/workspace', function () {
    test('owner can update workspace settings', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->post('/settings/workspace', [
            'name' => 'Updated Workspace',
            'description' => 'New description',
            'timezone' => 'America/New_York',
            'defaultEnvironment' => 'staging',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->team->refresh();
        expect($this->team->name)->toBe('Updated Workspace');
        expect($this->team->description)->toBe('New description');
        expect($this->team->timezone)->toBe('America/New_York');
        expect($this->team->default_environment)->toBe('staging');
    });

    test('admin can update workspace settings', function () {
        $this->actingAs($this->admin);
        session(['currentTeam' => $this->team]);

        $response = $this->post('/settings/workspace', [
            'name' => 'Admin Updated Workspace',
            'timezone' => 'Europe/London',
            'defaultEnvironment' => 'development',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->team->refresh();
        expect($this->team->name)->toBe('Admin Updated Workspace');
        expect($this->team->timezone)->toBe('Europe/London');
        expect($this->team->default_environment)->toBe('development');
    });

    test('member cannot update workspace settings', function () {
        $this->actingAs($this->member);
        session(['currentTeam' => $this->team]);

        $response = $this->post('/settings/workspace', [
            'name' => 'Member Attempt',
            'timezone' => 'UTC',
            'defaultEnvironment' => 'production',
        ]);

        $response->assertSessionHasErrors('workspace');

        $this->team->refresh();
        expect($this->team->name)->toBe('Test Workspace');
    });

    test('name is required', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->post('/settings/workspace', [
            'name' => '',
            'timezone' => 'UTC',
            'defaultEnvironment' => 'production',
        ]);

        $response->assertSessionHasErrors('name');
    });

    test('invalid timezone is rejected', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->post('/settings/workspace', [
            'name' => 'Test',
            'timezone' => 'Invalid/Timezone',
            'defaultEnvironment' => 'production',
        ]);

        $response->assertSessionHasErrors('timezone');
    });

    test('invalid environment is rejected', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->post('/settings/workspace', [
            'name' => 'Test',
            'timezone' => 'UTC',
            'defaultEnvironment' => 'invalid_env',
        ]);

        $response->assertSessionHasErrors('defaultEnvironment');
    });
});

describe('DELETE /settings/workspace', function () {
    test('owner can delete non-personal workspace', function () {
        $nonPersonalTeam = Team::factory()->create([
            'personal_team' => false,
        ]);
        $nonPersonalTeam->members()->attach($this->owner->id, ['role' => 'owner']);

        // Create a personal team for the owner to switch to
        $personalTeam = Team::factory()->create([
            'personal_team' => true,
        ]);
        $personalTeam->members()->attach($this->owner->id, ['role' => 'owner']);

        $this->actingAs($this->owner);
        session(['currentTeam' => $nonPersonalTeam]);

        $response = $this->delete('/settings/workspace');

        $response->assertRedirect('/');
        expect(Team::find($nonPersonalTeam->id))->toBeNull();
    });

    test('cannot delete personal workspace', function () {
        $personalTeam = Team::factory()->create([
            'personal_team' => true,
        ]);
        $personalTeam->members()->attach($this->owner->id, ['role' => 'owner']);

        $this->actingAs($this->owner);
        session(['currentTeam' => $personalTeam]);

        $response = $this->delete('/settings/workspace');

        $response->assertSessionHasErrors('workspace');
        expect(Team::find($personalTeam->id))->not->toBeNull();
    });

    test('member cannot delete workspace', function () {
        $this->actingAs($this->member);
        session(['currentTeam' => $this->team]);

        $response = $this->delete('/settings/workspace');

        $response->assertSessionHasErrors('workspace');
        expect(Team::find($this->team->id))->not->toBeNull();
    });

    test('admin cannot delete workspace', function () {
        $this->actingAs($this->admin);
        session(['currentTeam' => $this->team]);

        $response = $this->delete('/settings/workspace');

        $response->assertSessionHasErrors('workspace');
        expect(Team::find($this->team->id))->not->toBeNull();
    });
});
