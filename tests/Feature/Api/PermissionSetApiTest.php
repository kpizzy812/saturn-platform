<?php

use App\Models\InstanceSettings;
use App\Models\PermissionSet;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(DatabaseTransactions::class);

beforeEach(function () {
    Queue::fake();

    // Flush both default and Redis cache stores to prevent stale permission/team data.
    // The PermissionService caches authorization results; stale entries cause 403 errors.
    Cache::flush();
    try {
        Cache::store('redis')->flush();
    } catch (\Throwable $e) {
        // Redis may not be available in some test environments
    }

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    // Owner role is required for manage_roles permission
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    // Set session-based team context (used by currentTeam() helper)
    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    // InstanceSettings
    if (! InstanceSettings::first()) {
        InstanceSettings::create([
            'id' => 0,
            'is_api_enabled' => true,
        ]);
    }
});

describe('Authentication', function () {
    test('rejects list permission sets request without authentication', function () {
        $response = $this->getJson('/api/v1/permission-sets');
        $response->assertStatus(401);
    });

    test('rejects create permission set request without authentication', function () {
        $response = $this->postJson('/api/v1/permission-sets', ['name' => 'Test Set']);
        $response->assertStatus(401);
    });

    test('rejects update permission set request without authentication', function () {
        $response = $this->putJson('/api/v1/permission-sets/1', ['name' => 'Updated']);
        $response->assertStatus(401);
    });

    test('rejects delete permission set request without authentication', function () {
        $response = $this->deleteJson('/api/v1/permission-sets/1');
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/permission-sets - List permission sets', function () {
    test('returns empty data when team has no permission sets', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/permission-sets');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        expect($response->json('data'))->toBeEmpty();
    });

    test('returns list of permission sets for the team', function () {
        PermissionSet::create([
            'name' => 'Developer',
            'slug' => 'developer',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/permission-sets');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0])->toHaveKeys(['id', 'name', 'slug', 'is_system', 'users_count']);
    });

    test('does not include permission sets from other teams', function () {
        // Create a permission set for this team
        PermissionSet::create([
            'name' => 'Team Role',
            'slug' => 'team-role',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => false,
        ]);

        // Create another team with its own permission set
        $otherTeam = Team::factory()->create();
        PermissionSet::create([
            'name' => 'Other Team Role',
            'slug' => 'other-team-role',
            'scope_type' => 'team',
            'scope_id' => $otherTeam->id,
            'is_system' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/permission-sets');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Team Role');
    });
});

describe('GET /api/v1/permission-sets/permissions - List all permissions', function () {
    test('returns all available permissions grouped by category', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/permission-sets/permissions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    });

    test('returns 401 without authentication', function () {
        $response = $this->getJson('/api/v1/permission-sets/permissions');
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/permission-sets/my-permissions - Get my permissions', function () {
    test('returns current user effective permissions', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/permission-sets/my-permissions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['permission_set', 'permissions']]);
    });

    test('returns 401 without authentication', function () {
        $response = $this->getJson('/api/v1/permission-sets/my-permissions');
        $response->assertStatus(401);
    });
});

describe('GET /api/v1/permission-sets/{id} - Get permission set', function () {
    test('returns permission set details', function () {
        $permissionSet = PermissionSet::create([
            'name' => 'QA Engineer',
            'slug' => 'qa-engineer',
            'description' => 'Quality assurance role',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/permission-sets/{$permissionSet->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['id', 'name', 'slug', 'description', 'is_system', 'permissions', 'users']]);
        $response->assertJsonPath('data.name', 'QA Engineer');
        $response->assertJsonPath('data.description', 'Quality assurance role');
    });

    test('returns 404 for non-existent permission set', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson('/api/v1/permission-sets/999999');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Permission set not found.']);
    });

    test('returns 404 for permission set from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherPermissionSet = PermissionSet::create([
            'name' => 'Other Role',
            'slug' => 'other-role',
            'scope_type' => 'team',
            'scope_id' => $otherTeam->id,
            'is_system' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->getJson("/api/v1/permission-sets/{$otherPermissionSet->id}");

        $response->assertStatus(404);
    });
});

describe('POST /api/v1/permission-sets - Create permission set', function () {
    test('creates a permission set successfully', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/permission-sets', [
            'name' => 'Backend Developer',
            'description' => 'Backend team member',
            'color' => 'blue',
            'icon' => 'code',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['message' => 'Permission set created successfully.']);
        $response->assertJsonStructure(['data' => ['id', 'name', 'slug']]);
        $response->assertJsonPath('data.name', 'Backend Developer');

        $this->assertDatabaseHas('permission_sets', [
            'name' => 'Backend Developer',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => false,
        ]);
    });

    test('returns 422 when name is missing', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/permission-sets', [
            'description' => 'Missing name field',
        ]);

        $response->assertStatus(422);
    });

    test('returns 422 when permission set name already exists in the team', function () {
        PermissionSet::create([
            'name' => 'Existing Role',
            'slug' => 'existing-role',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/permission-sets', [
            'name' => 'Existing Role',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'A permission set with this name already exists.']);
    });

    test('returns 403 for non-owner team member', function () {
        $memberUser = User::factory()->create();
        $this->team->members()->attach($memberUser->id, ['role' => 'member']);

        // Clear cache to ensure fresh authorization check
        Cache::flush();

        session(['currentTeam' => $this->team]);
        $memberToken = $memberUser->createToken('member-token', ['*']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$memberToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->postJson('/api/v1/permission-sets', [
            'name' => 'New Role',
        ]);

        $response->assertStatus(403);
    });
});

describe('PUT /api/v1/permission-sets/{id} - Update permission set', function () {
    test('updates a custom permission set successfully', function () {
        $permissionSet = PermissionSet::create([
            'name' => 'Old Name',
            'slug' => 'old-name',
            'description' => 'Old description',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->putJson("/api/v1/permission-sets/{$permissionSet->id}", [
            'name' => 'New Name',
            'description' => 'Updated description',
            'color' => 'green',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Permission set updated successfully.']);

        $this->assertDatabaseHas('permission_sets', [
            'id' => $permissionSet->id,
            'name' => 'New Name',
            'description' => 'Updated description',
            'color' => 'green',
        ]);
    });

    test('allows updating description and icon on system permission sets', function () {
        $systemSet = PermissionSet::create([
            'name' => 'System Role',
            'slug' => 'system-role',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->putJson("/api/v1/permission-sets/{$systemSet->id}", [
            'description' => 'Updated system description',
            'icon' => 'star',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('permission_sets', [
            'id' => $systemSet->id,
            'description' => 'Updated system description',
            'icon' => 'star',
        ]);
    });

    test('returns 404 for non-existent permission set', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->putJson('/api/v1/permission-sets/999999', [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Permission set not found.']);
    });

    test('returns 422 when updating to an already existing name', function () {
        PermissionSet::create([
            'name' => 'Taken Name',
            'slug' => 'taken-name',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => false,
        ]);

        $permissionSet = PermissionSet::create([
            'name' => 'Another Role',
            'slug' => 'another-role',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->putJson("/api/v1/permission-sets/{$permissionSet->id}", [
            'name' => 'Taken Name',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'A permission set with this name already exists.']);
    });

    test('returns 403 for non-owner team member', function () {
        $permissionSet = PermissionSet::create([
            'name' => 'Role To Update',
            'slug' => 'role-to-update',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => false,
        ]);

        $memberUser = User::factory()->create();
        $this->team->members()->attach($memberUser->id, ['role' => 'member']);

        Cache::flush();

        session(['currentTeam' => $this->team]);
        $memberToken = $memberUser->createToken('member-token', ['*']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$memberToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->putJson("/api/v1/permission-sets/{$permissionSet->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(403);
    });
});

describe('DELETE /api/v1/permission-sets/{id} - Delete permission set', function () {
    test('deletes a custom permission set successfully', function () {
        $permissionSet = PermissionSet::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/permission-sets/{$permissionSet->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Permission set deleted successfully.']);

        $this->assertDatabaseMissing('permission_sets', [
            'id' => $permissionSet->id,
        ]);
    });

    test('returns 403 when trying to delete a system permission set', function () {
        $systemSet = PermissionSet::create([
            'name' => 'System Set',
            'slug' => 'system-set',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/permission-sets/{$systemSet->id}");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'System permission sets cannot be deleted.']);
    });

    test('returns 404 for non-existent permission set', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson('/api/v1/permission-sets/999999');

        $response->assertStatus(404);
        $response->assertJson(['message' => 'Permission set not found.']);
    });

    test('returns 404 for permission set from another team', function () {
        $otherTeam = Team::factory()->create();
        $otherPermissionSet = PermissionSet::create([
            'name' => 'Other Team Role',
            'slug' => 'other-team-role',
            'scope_type' => 'team',
            'scope_id' => $otherTeam->id,
            'is_system' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->bearerToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/permission-sets/{$otherPermissionSet->id}");

        $response->assertStatus(404);
    });

    test('returns 403 for non-owner team member', function () {
        $permissionSet = PermissionSet::create([
            'name' => 'Role To Delete',
            'slug' => 'role-to-delete',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => false,
        ]);

        $memberUser = User::factory()->create();
        $this->team->members()->attach($memberUser->id, ['role' => 'member']);

        Cache::flush();

        session(['currentTeam' => $this->team]);
        $memberToken = $memberUser->createToken('member-token', ['*']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$memberToken->plainTextToken,
            'Content-Type' => 'application/json',
        ])->deleteJson("/api/v1/permission-sets/{$permissionSet->id}");

        $response->assertStatus(403);
    });
});
