<?php

/**
 * E2E Permission Set Enforcement Tests
 *
 * Covers the full permission set CRUD lifecycle and cross-resource enforcement:
 * - Full CRUD lifecycle (create → list → get → update → delete)
 * - Permission sync and assignment to permission sets
 * - User assignment/removal on permission sets
 * - System set protection (cannot delete/rename)
 * - Duplicate name/slug prevention
 * - Cross-team isolation (team A cannot access team B permission sets)
 * - Token ability enforcement (read vs write)
 * - Permission inheritance via parent_id
 * - My-permissions endpoint for effective permission resolution
 * - Manage roles permission enforcement for non-owner users
 */

use App\Models\InstanceSettings;
use App\Models\Permission;
use App\Models\PermissionSet;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(DatabaseTransactions::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function permSetHeaders(string $bearer): array
{
    return [
        'Authorization' => 'Bearer '.$bearer,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

/**
 * Ensure required permissions exist in the permissions table.
 * Permissions may or may not be seeded in the test DB.
 *
 * @return array<string, Permission> Map of key => Permission model
 */
function ensurePermissionsExist(): array
{
    $definitions = [
        ['key' => 'server.view', 'name' => 'View Servers', 'resource' => 'server', 'action' => 'view', 'category' => 'server', 'sort_order' => 1],
        ['key' => 'server.create', 'name' => 'Create Servers', 'resource' => 'server', 'action' => 'create', 'category' => 'server', 'sort_order' => 2],
        ['key' => 'server.delete', 'name' => 'Delete Servers', 'resource' => 'server', 'action' => 'delete', 'category' => 'server', 'sort_order' => 3],
        ['key' => 'application.view', 'name' => 'View Applications', 'resource' => 'application', 'action' => 'view', 'category' => 'application', 'sort_order' => 10],
        ['key' => 'application.deploy', 'name' => 'Deploy Applications', 'resource' => 'application', 'action' => 'deploy', 'category' => 'application', 'sort_order' => 11],
        ['key' => 'application.create', 'name' => 'Create Applications', 'resource' => 'application', 'action' => 'create', 'category' => 'application', 'sort_order' => 12],
        ['key' => 'database.view', 'name' => 'View Databases', 'resource' => 'database', 'action' => 'view', 'category' => 'database', 'sort_order' => 20],
        ['key' => 'team.manage_roles', 'name' => 'Manage Roles', 'resource' => 'team', 'action' => 'manage_roles', 'category' => 'team', 'sort_order' => 30],
        ['key' => 'team.manage_members', 'name' => 'Manage Members', 'resource' => 'team', 'action' => 'manage_members', 'category' => 'team', 'sort_order' => 31],
    ];

    $map = [];
    foreach ($definitions as $def) {
        $map[$def['key']] = Permission::firstOrCreate(
            ['key' => $def['key']],
            $def
        );
    }

    return $map;
}

// ─── Setup ────────────────────────────────────────────────────────────────────

beforeEach(function () {
    Queue::fake();
    Cache::flush();
    try {
        Cache::store('redis')->flush();
    } catch (\Throwable $e) {
        // Redis may not be available in some test environments
    }

    $this->team = Team::factory()->create();
    $this->user = User::factory()->create();
    // Owner role grants team.manage_roles via hardcoded role hierarchy
    $this->team->members()->attach($this->user->id, ['role' => 'owner']);

    session(['currentTeam' => $this->team]);
    $this->token = $this->user->createToken('test-token', ['*']);
    $this->bearerToken = $this->token->plainTextToken;

    if (! InstanceSettings::first()) {
        InstanceSettings::create(['id' => 0, 'is_api_enabled' => true]);
    }

    // Ensure permissions catalog is populated
    $this->permissions = ensurePermissionsExist();
});

// ─── 1. Full CRUD Lifecycle ──────────────────────────────────────────────────

describe('Full CRUD lifecycle', function () {
    test('create → list → get → update → delete permission set', function () {
        // Step 1: Create
        $createResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', [
                'name' => 'Release Manager',
                'description' => 'Manages release processes',
                'color' => 'indigo',
                'icon' => 'rocket',
            ]);

        $createResponse->assertStatus(201)
            ->assertJson(['message' => 'Permission set created successfully.'])
            ->assertJsonPath('data.name', 'Release Manager')
            ->assertJsonPath('data.slug', 'release-manager')
            ->assertJsonPath('data.description', 'Manages release processes')
            ->assertJsonPath('data.color', 'indigo')
            ->assertJsonPath('data.icon', 'rocket')
            ->assertJsonPath('data.is_system', false);

        $setId = $createResponse->json('data.id');
        expect($setId)->toBeInt();

        // Step 2: Verify it appears in the list
        $listResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->getJson('/api/v1/permission-sets');

        $listResponse->assertStatus(200);
        $names = collect($listResponse->json('data'))->pluck('name')->all();
        expect($names)->toContain('Release Manager');

        // Step 3: Get by ID
        $showResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->getJson("/api/v1/permission-sets/{$setId}");

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.id', $setId)
            ->assertJsonPath('data.name', 'Release Manager')
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'description', 'is_system', 'permissions', 'users']]);

        // Step 4: Update name and description
        $updateResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->putJson("/api/v1/permission-sets/{$setId}", [
                'name' => 'Senior Release Manager',
                'description' => 'Senior release management role',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson(['message' => 'Permission set updated successfully.'])
            ->assertJsonPath('data.name', 'Senior Release Manager')
            ->assertJsonPath('data.slug', 'senior-release-manager');

        // Step 5: Delete
        $deleteResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->deleteJson("/api/v1/permission-sets/{$setId}");

        $deleteResponse->assertStatus(200)
            ->assertJson(['message' => 'Permission set deleted successfully.']);

        // Step 6: Verify it is gone
        $verifyResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->getJson("/api/v1/permission-sets/{$setId}");

        $verifyResponse->assertStatus(404);
    });
});

// ─── 2. Permission Assignment Lifecycle ──────────────────────────────────────

describe('Permission assignment lifecycle', function () {
    test('create set → list available permissions → sync permissions → verify → clear', function () {
        // Create a permission set
        $createResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Viewer Plus']);

        $createResponse->assertStatus(201);
        $setId = $createResponse->json('data.id');

        // List available permissions (should include ones we ensured)
        $permResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->getJson('/api/v1/permission-sets/permissions');

        $permResponse->assertStatus(200)
            ->assertJsonStructure(['data']);

        // The data should be grouped by category — check that at least 'server' category exists
        $categories = array_keys($permResponse->json('data'));
        expect($categories)->toContain('server');

        // Sync specific permissions to the set
        $serverView = $this->permissions['server.view'];
        $appDeploy = $this->permissions['application.deploy'];

        $syncResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson("/api/v1/permission-sets/{$setId}/permissions", [
                'permissions' => [
                    ['permission_id' => $serverView->id],
                    ['permission_id' => $appDeploy->id, 'environment_restrictions' => ['production' => false]],
                ],
            ]);

        $syncResponse->assertStatus(200)
            ->assertJson(['message' => 'Permissions synced successfully.']);

        // Verify they are attached
        $showResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->getJson("/api/v1/permission-sets/{$setId}");

        $showResponse->assertStatus(200);
        $attachedKeys = collect($showResponse->json('data.permissions'))->pluck('key')->all();
        expect($attachedKeys)->toContain('server.view')
            ->toContain('application.deploy');

        // Clear permissions by syncing empty array — re-sync to only server.view
        $reSyncResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson("/api/v1/permission-sets/{$setId}/permissions", [
                'permissions' => [
                    ['permission_id' => $serverView->id],
                ],
            ]);

        $reSyncResponse->assertStatus(200);

        // Verify application.deploy is removed
        $verifyResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->getJson("/api/v1/permission-sets/{$setId}");

        $attachedKeys = collect($verifyResponse->json('data.permissions'))->pluck('key')->all();
        expect($attachedKeys)->toContain('server.view')
            ->not->toContain('application.deploy');
    });
});

// ─── 3. User Assignment ─────────────────────────────────────────────────────

describe('User assignment lifecycle', function () {
    test('create set → assign user → verify → remove user → verify gone', function () {
        // Create a separate member user to assign
        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);

        // Create a permission set
        $createResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'DevOps']);

        $createResponse->assertStatus(201);
        $setId = $createResponse->json('data.id');

        // Assign user to the set
        $assignResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson("/api/v1/permission-sets/{$setId}/users", [
                'user_id' => $member->id,
            ]);

        $assignResponse->assertStatus(200)
            ->assertJson(['message' => 'User assigned to permission set successfully.']);

        // Verify user appears in the permission set detail
        $showResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->getJson("/api/v1/permission-sets/{$setId}");

        $showResponse->assertStatus(200);
        $userIds = collect($showResponse->json('data.users'))->pluck('id')->all();
        expect($userIds)->toContain($member->id);

        // Remove user from the set
        $removeResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->deleteJson("/api/v1/permission-sets/{$setId}/users/{$member->id}");

        $removeResponse->assertStatus(200)
            ->assertJson(['message' => 'User removed from permission set successfully.']);

        // Verify user is gone
        $verifyResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->getJson("/api/v1/permission-sets/{$setId}");

        $userIds = collect($verifyResponse->json('data.users'))->pluck('id')->all();
        expect($userIds)->not->toContain($member->id);
    });

    test('cannot assign non-team-member to a permission set', function () {
        $outsider = User::factory()->create(); // Not attached to team

        $createResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'TestRole']);

        $setId = $createResponse->json('data.id');

        $assignResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson("/api/v1/permission-sets/{$setId}/users", [
                'user_id' => $outsider->id,
            ]);

        $assignResponse->assertStatus(404)
            ->assertJson(['message' => 'User is not a member of this team.']);
    });
});

// ─── 4. System Set Protection ────────────────────────────────────────────────

describe('System set protection', function () {
    test('cannot delete a system permission set', function () {
        $systemSet = PermissionSet::create([
            'name' => 'Owner System',
            'slug' => 'owner-system',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => true,
        ]);

        $response = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->deleteJson("/api/v1/permission-sets/{$systemSet->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'System permission sets cannot be deleted.']);

        // Confirm it still exists
        $this->assertDatabaseHas('permission_sets', ['id' => $systemSet->id]);
    });

    test('system set name cannot be changed via update', function () {
        $systemSet = PermissionSet::create([
            'name' => 'Admin System',
            'slug' => 'admin-system',
            'scope_type' => 'team',
            'scope_id' => $this->team->id,
            'is_system' => true,
        ]);

        $response = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->putJson("/api/v1/permission-sets/{$systemSet->id}", [
                'name' => 'Hacked Admin Name',
                'description' => 'Updated description is allowed',
            ]);

        // Update succeeds for allowed fields, but name stays unchanged
        $response->assertStatus(200);

        $systemSet->refresh();
        expect($systemSet->name)->toBe('Admin System');
        expect($systemSet->slug)->toBe('admin-system');
        expect($systemSet->description)->toBe('Updated description is allowed');
    });
});

// ─── 5. Duplicate Name Prevention ────────────────────────────────────────────

describe('Duplicate name prevention', function () {
    test('creating a permission set with a duplicate name returns 422', function () {
        // Create first set
        $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'QA Engineer'])
            ->assertStatus(201);

        // Attempt duplicate
        $dupResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'QA Engineer']);

        $dupResponse->assertStatus(422)
            ->assertJson(['message' => 'A permission set with this name already exists.']);
    });

    test('updating to an existing name returns 422', function () {
        $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Alpha Team'])
            ->assertStatus(201);

        $betaResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Beta Team']);

        $betaId = $betaResponse->json('data.id');

        $updateResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->putJson("/api/v1/permission-sets/{$betaId}", ['name' => 'Alpha Team']);

        $updateResponse->assertStatus(422)
            ->assertJson(['message' => 'A permission set with this name already exists.']);
    });
});

// ─── 6. Cross-Team Isolation ─────────────────────────────────────────────────

describe('Cross-team isolation', function () {
    test('team B cannot list, get, update, or delete team A permission sets', function () {
        // Team A creates a permission set (already using $this->team)
        $createResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Team A Role']);

        $createResponse->assertStatus(201);
        $setId = $createResponse->json('data.id');

        // Create Team B with its own user and token
        $teamB = Team::factory()->create();
        $userB = User::factory()->create();
        $teamB->members()->attach($userB->id, ['role' => 'owner']);
        session(['currentTeam' => $teamB]);
        $tokenB = $userB->createToken('team-b-token', ['*'])->plainTextToken;

        // Team B tries to list — should not see Team A set
        $listResponse = $this->withHeaders(permSetHeaders($tokenB))
            ->getJson('/api/v1/permission-sets');

        $listResponse->assertStatus(200);
        $names = collect($listResponse->json('data'))->pluck('name')->all();
        expect($names)->not->toContain('Team A Role');

        // Team B tries to GET by ID — 404
        $showResponse = $this->withHeaders(permSetHeaders($tokenB))
            ->getJson("/api/v1/permission-sets/{$setId}");

        $showResponse->assertStatus(404);

        // Team B tries to UPDATE — 404
        $updateResponse = $this->withHeaders(permSetHeaders($tokenB))
            ->putJson("/api/v1/permission-sets/{$setId}", ['name' => 'Stolen']);

        $updateResponse->assertStatus(404);

        // Team B tries to DELETE — 404
        $deleteResponse = $this->withHeaders(permSetHeaders($tokenB))
            ->deleteJson("/api/v1/permission-sets/{$setId}");

        $deleteResponse->assertStatus(404);
    });
});

// ─── 7. Token Ability Enforcement ────────────────────────────────────────────

describe('Token ability enforcement', function () {
    test('read-only token can list and get, but cannot create, update, or delete', function () {
        // Create a permission set first (with full-access token)
        $createResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Token Test Role']);

        $createResponse->assertStatus(201);
        $setId = $createResponse->json('data.id');

        // Create a read-only token
        $readToken = $this->user->createToken('read-only', ['read'])->plainTextToken;

        // READ operations succeed
        $listResponse = $this->withHeaders(permSetHeaders($readToken))
            ->getJson('/api/v1/permission-sets');
        $listResponse->assertStatus(200);

        $showResponse = $this->withHeaders(permSetHeaders($readToken))
            ->getJson("/api/v1/permission-sets/{$setId}");
        $showResponse->assertStatus(200);

        $permResponse = $this->withHeaders(permSetHeaders($readToken))
            ->getJson('/api/v1/permission-sets/permissions');
        $permResponse->assertStatus(200);

        $myPermResponse = $this->withHeaders(permSetHeaders($readToken))
            ->getJson('/api/v1/permission-sets/my-permissions');
        $myPermResponse->assertStatus(200);

        // WRITE operations are rejected (403)
        $createFail = $this->withHeaders(permSetHeaders($readToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Blocked']);
        $createFail->assertStatus(403);

        $updateFail = $this->withHeaders(permSetHeaders($readToken))
            ->putJson("/api/v1/permission-sets/{$setId}", ['name' => 'Blocked']);
        $updateFail->assertStatus(403);

        $deleteFail = $this->withHeaders(permSetHeaders($readToken))
            ->deleteJson("/api/v1/permission-sets/{$setId}");
        $deleteFail->assertStatus(403);

        $syncFail = $this->withHeaders(permSetHeaders($readToken))
            ->postJson("/api/v1/permission-sets/{$setId}/permissions", ['permissions' => []]);
        $syncFail->assertStatus(403);
    });

    test('write token can create and update permission sets', function () {
        $writeToken = $this->user->createToken('write-only', ['read', 'write'])->plainTextToken;

        $createResponse = $this->withHeaders(permSetHeaders($writeToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Write Token Role']);

        $createResponse->assertStatus(201);
        $setId = $createResponse->json('data.id');

        $updateResponse = $this->withHeaders(permSetHeaders($writeToken))
            ->putJson("/api/v1/permission-sets/{$setId}", ['description' => 'Updated via write token']);

        $updateResponse->assertStatus(200);
    });
});

// ─── 8. Permission Inheritance via parent_id ─────────────────────────────────

describe('Permission inheritance via parent_id', function () {
    test('child permission set inherits parent permissions', function () {
        $serverView = $this->permissions['server.view'];
        $appDeploy = $this->permissions['application.deploy'];

        // Create parent with server.view permission
        $parentResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', [
                'name' => 'Base Role',
                'permissions' => [
                    ['permission_id' => $serverView->id],
                ],
            ]);

        $parentResponse->assertStatus(201);
        $parentId = $parentResponse->json('data.id');

        // Create child with application.deploy and parent_id
        $childResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', [
                'name' => 'Extended Role',
                'parent_id' => $parentId,
                'permissions' => [
                    ['permission_id' => $appDeploy->id],
                ],
            ]);

        $childResponse->assertStatus(201);
        $childId = $childResponse->json('data.id');

        // Verify parent reference on child
        $showChild = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->getJson("/api/v1/permission-sets/{$childId}");

        $showChild->assertStatus(200)
            ->assertJsonPath('data.parent.id', $parentId)
            ->assertJsonPath('data.parent.name', 'Base Role');

        // Verify model-level inheritance: child hasPermission checks parent too
        $childModel = PermissionSet::with(['permissions', 'parent.permissions'])->find($childId);
        expect($childModel->hasPermission('application.deploy'))->toBeTrue();
        expect($childModel->hasPermission('server.view'))->toBeTrue(); // inherited from parent

        // getAllPermissionKeys includes both own and inherited keys
        $allKeys = $childModel->getAllPermissionKeys();
        expect($allKeys)->toContain('server.view');
        expect($allKeys)->toContain('application.deploy');
    });
});

// ─── 9. My-Permissions Endpoint ──────────────────────────────────────────────

describe('My-permissions endpoint', function () {
    test('returns effective permissions for current user', function () {
        $response = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->getJson('/api/v1/permission-sets/my-permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'permission_set',
                    'permissions',
                ],
            ]);

        // Owner user — permission_set may be null (fallback to role-based)
        // or a system set. Either way, the structure should be valid.
        $data = $response->json('data');
        expect($data)->toHaveKeys(['permission_set', 'permissions']);
    });

    test('returns permissions for user assigned to custom permission set', function () {
        $serverView = $this->permissions['server.view'];
        $appDeploy = $this->permissions['application.deploy'];

        // Create member user
        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);

        // Create a custom permission set with specific permissions
        $setResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', [
                'name' => 'Custom Viewer',
                'permissions' => [
                    ['permission_id' => $serverView->id],
                    ['permission_id' => $appDeploy->id],
                ],
            ]);
        $setId = $setResponse->json('data.id');

        // Assign member to the set
        $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson("/api/v1/permission-sets/{$setId}/users", [
                'user_id' => $member->id,
            ])
            ->assertStatus(200);

        // Flush cache to pick up new assignment
        Cache::flush();
        try {
            Cache::store('redis')->flush();
        } catch (\Throwable $e) {
        }

        // Get member's own token and check my-permissions
        session(['currentTeam' => $this->team]);
        $memberToken = $member->createToken('member-token', ['*'])->plainTextToken;

        $myPermResponse = $this->withHeaders(permSetHeaders($memberToken))
            ->getJson('/api/v1/permission-sets/my-permissions');

        $myPermResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['permission_set', 'permissions'],
            ]);
    });
});

// ─── 10. Manage Roles Permission Required ────────────────────────────────────

describe('Manage roles permission required', function () {
    test('member without manage_roles permission gets 403 on create', function () {
        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);

        Cache::flush();
        try {
            Cache::store('redis')->flush();
        } catch (\Throwable $e) {
        }

        session(['currentTeam' => $this->team]);
        $memberToken = $member->createToken('member-token', ['*'])->plainTextToken;

        $response = $this->withHeaders(permSetHeaders($memberToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Unauthorized Create']);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized. You do not have permission to manage permission sets.']);
    });

    test('member without manage_roles permission gets 403 on update', function () {
        // Owner creates a set
        $createResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Protected Role']);

        $setId = $createResponse->json('data.id');

        // Member tries to update
        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);

        Cache::flush();
        try {
            Cache::store('redis')->flush();
        } catch (\Throwable $e) {
        }

        session(['currentTeam' => $this->team]);
        $memberToken = $member->createToken('member-token', ['*'])->plainTextToken;

        $response = $this->withHeaders(permSetHeaders($memberToken))
            ->putJson("/api/v1/permission-sets/{$setId}", ['name' => 'Hacked']);

        $response->assertStatus(403);
    });

    test('member without manage_roles permission gets 403 on delete', function () {
        // Owner creates a set
        $createResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Cannot Delete']);

        $setId = $createResponse->json('data.id');

        // Member tries to delete
        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);

        Cache::flush();
        try {
            Cache::store('redis')->flush();
        } catch (\Throwable $e) {
        }

        session(['currentTeam' => $this->team]);
        $memberToken = $member->createToken('member-token', ['*'])->plainTextToken;

        $response = $this->withHeaders(permSetHeaders($memberToken))
            ->deleteJson("/api/v1/permission-sets/{$setId}");

        $response->assertStatus(403);
    });

    test('member without manage_roles permission gets 403 on permission sync', function () {
        $createResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Perm Sync Role']);

        $setId = $createResponse->json('data.id');

        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);

        Cache::flush();
        try {
            Cache::store('redis')->flush();
        } catch (\Throwable $e) {
        }

        session(['currentTeam' => $this->team]);
        $memberToken = $member->createToken('member-token', ['*'])->plainTextToken;

        $serverView = $this->permissions['server.view'];

        $response = $this->withHeaders(permSetHeaders($memberToken))
            ->postJson("/api/v1/permission-sets/{$setId}/permissions", [
                'permissions' => [['permission_id' => $serverView->id]],
            ]);

        $response->assertStatus(403);
    });
});

// ─── Additional Edge Cases ───────────────────────────────────────────────────

describe('Edge cases', function () {
    test('cannot delete permission set that has assigned users', function () {
        // Create a member and a permission set
        $member = User::factory()->create();
        $this->team->members()->attach($member->id, ['role' => 'member']);

        $createResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Assigned Role']);

        $setId = $createResponse->json('data.id');

        // Assign user
        $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson("/api/v1/permission-sets/{$setId}/users", [
                'user_id' => $member->id,
            ])
            ->assertStatus(200);

        // Attempt to delete — should fail because users are assigned
        $deleteResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->deleteJson("/api/v1/permission-sets/{$setId}");

        // The canBeDeleted() returns false when users exist
        $deleteResponse->assertStatus(403)
            ->assertJsonFragment(['message' => 'Cannot delete permission set that is assigned to users. Please reassign users first.']);
    });

    test('cannot delete permission set that has child sets', function () {
        // Create parent
        $parentResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Parent Role']);

        $parentId = $parentResponse->json('data.id');

        // Create child
        $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', [
                'name' => 'Child Role',
                'parent_id' => $parentId,
            ])
            ->assertStatus(201);

        // Attempt to delete parent
        $deleteResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->deleteJson("/api/v1/permission-sets/{$parentId}");

        $deleteResponse->assertStatus(403)
            ->assertJsonFragment(['message' => 'Cannot delete permission set that has child sets. Please delete or reassign child sets first.']);
    });

    test('environment restrictions are persisted on permission sync', function () {
        $createResponse = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', ['name' => 'Env Restricted']);

        $setId = $createResponse->json('data.id');
        $appDeploy = $this->permissions['application.deploy'];

        // Sync with environment restrictions
        $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson("/api/v1/permission-sets/{$setId}/permissions", [
                'permissions' => [
                    [
                        'permission_id' => $appDeploy->id,
                        'environment_restrictions' => ['production' => false],
                    ],
                ],
            ])
            ->assertStatus(200);

        // Verify restrictions via the model
        $set = PermissionSet::with('permissions')->find($setId);
        $deployPerm = $set->permissions->firstWhere('key', 'application.deploy');
        expect($deployPerm)->not->toBeNull();

        $restrictions = $deployPerm->pivot->environment_restrictions;
        $decoded = is_string($restrictions) ? json_decode($restrictions, true) : $restrictions;
        expect($decoded)->toHaveKey('production');
        expect($decoded['production'])->toBeFalse();

        // hasPermission should deny for production environment
        expect($set->hasPermission('application.deploy', 'production'))->toBeFalse();
        // but allow for staging
        expect($set->hasPermission('application.deploy', 'staging'))->toBeTrue();
    });

    test('creating permission set with invalid parent_id returns 422', function () {
        $response = $this->withHeaders(permSetHeaders($this->bearerToken))
            ->postJson('/api/v1/permission-sets', [
                'name' => 'Invalid Parent',
                'parent_id' => 999999,
            ]);

        $response->assertStatus(422);
    });
});
