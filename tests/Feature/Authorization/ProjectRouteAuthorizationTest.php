<?php

use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::factory()->create(['id' => 0]);

    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->viewer = User::factory()->create();

    $this->team->members()->attach($this->owner->id, ['role' => 'owner']);
    $this->team->members()->attach($this->viewer->id, ['role' => 'viewer']);

    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
});

describe('POST /projects (create)', function () {
    test('viewer can create project (policy allows all authenticated)', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $response = $this->postJson('/projects', [
            'name' => 'Test Project',
            'description' => 'Description',
        ]);

        // ProjectPolicy::create() returns true for any authenticated user
        expect($response->status())->not->toBe(403);
    });
});

describe('PATCH /projects/{uuid} (update)', function () {
    test('viewer cannot update project', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/projects/{$this->project->uuid}", [
            'name' => 'Renamed',
        ])->assertStatus(403);
    });

    test('owner can update project', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        $response = $this->patchJson("/projects/{$this->project->uuid}", [
            'name' => 'Renamed',
        ]);

        expect($response->status())->not->toBe(403);
    });
});

describe('DELETE /projects/{uuid}', function () {
    test('viewer cannot delete project', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->deleteJson("/projects/{$this->project->uuid}")
            ->assertStatus(403);
    });

    test('owner can delete project', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->deleteJson("/projects/{$this->project->uuid}")->status())
            ->not->toBe(403);
    });
});

describe('shared variables CRUD (update)', function () {
    test('viewer cannot create shared variable', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/projects/{$this->project->uuid}/shared-variables", [
            'key' => 'TEST_KEY',
            'value' => 'test_value',
        ])->assertStatus(403);
    });

    test('viewer cannot update shared variable', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/projects/{$this->project->uuid}/shared-variables/1", [
            'key' => 'UPDATED_KEY',
            'value' => 'updated',
        ])->assertStatus(403);
    });

    test('viewer cannot delete shared variable', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->deleteJson("/projects/{$this->project->uuid}/shared-variables/1")
            ->assertStatus(403);
    });

    test('owner can create shared variable (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson("/projects/{$this->project->uuid}/shared-variables", [
            'key' => 'TEST_KEY',
            'value' => 'test_value',
        ])->status())->not->toBe(403);
    });
});

describe('POST /projects/{uuid}/clone (update)', function () {
    test('viewer cannot clone project', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/projects/{$this->project->uuid}/clone", [
            'name' => 'Cloned Project',
        ])->assertStatus(403);
    });

    test('owner can clone project (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson("/projects/{$this->project->uuid}/clone", [
            'name' => 'Cloned Project',
        ])->status())->not->toBe(403);
    });
});

describe('POST /projects/{uuid}/transfer (update)', function () {
    test('viewer cannot transfer project', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/projects/{$this->project->uuid}/transfer", [
            'target_team_id' => 999,
        ])->assertStatus(403);
    });
});

describe('archive/unarchive (update)', function () {
    test('viewer cannot archive project', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/projects/{$this->project->uuid}/archive")
            ->assertStatus(403);
    });

    test('viewer cannot unarchive project', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->project->update(['is_archived' => true, 'archived_at' => now()]);

        $this->postJson("/projects/{$this->project->uuid}/unarchive")
            ->assertStatus(403);
    });

    test('owner can archive project (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson("/projects/{$this->project->uuid}/archive")->status())
            ->not->toBe(403);
    });
});

describe('tags management (update)', function () {
    test('viewer cannot attach tag to project', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->postJson("/projects/{$this->project->uuid}/tags", [
            'name' => 'test-tag',
        ])->assertStatus(403);
    });

    test('viewer cannot detach tag from project', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->deleteJson("/projects/{$this->project->uuid}/tags/1")
            ->assertStatus(403);
    });

    test('owner can attach tag (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->postJson("/projects/{$this->project->uuid}/tags", [
            'name' => 'test-tag',
        ])->status())->not->toBe(403);
    });
});

describe('PATCH notification-overrides (update)', function () {
    test('viewer cannot update notification overrides', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/projects/{$this->project->uuid}/notification-overrides", [
            'deployment_success' => true,
        ])->assertStatus(403);
    });

    test('owner can update notification overrides (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->patchJson("/projects/{$this->project->uuid}/notification-overrides", [
            'deployment_success' => true,
        ])->status())->not->toBe(403);
    });
});

describe('settings routes (update)', function () {
    test('viewer cannot update project quotas', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/projects/{$this->project->uuid}/settings/quotas", [
            'max_applications' => 10,
        ])->assertStatus(403);
    });

    test('viewer cannot update deployment defaults', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/projects/{$this->project->uuid}/settings/deployment-defaults", [
            'default_auto_deploy' => true,
        ])->assertStatus(403);
    });

    test('viewer cannot update environment branches', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/projects/{$this->project->uuid}/settings/environment-branches", [
            'branches' => [],
        ])->assertStatus(403);
    });

    test('viewer cannot update default server', function () {
        $this->actingAs($this->viewer);
        session(['currentTeam' => $this->team]);

        $this->patchJson("/projects/{$this->project->uuid}/settings/default-server", [
            'default_server_id' => null,
        ])->assertStatus(403);
    });

    test('owner can update project settings (not 403)', function () {
        $this->actingAs($this->owner);
        session(['currentTeam' => $this->team]);

        expect($this->patchJson("/projects/{$this->project->uuid}/settings/quotas", [
            'max_applications' => 10,
        ])->status())->not->toBe(403);
    });
});
