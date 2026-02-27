<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a superadmin user
    $this->superadmin = User::factory()->create([
        'is_superadmin' => true,
        'status' => 'active',
    ]);

    // Create a regular admin team
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->superadmin->id, ['role' => 'owner']);

    // Create regular users for testing
    $this->regularUsers = User::factory()->count(3)->create([
        'status' => 'active',
    ]);

    // Authenticate as superadmin
    $this->actingAs($this->superadmin);
});

describe('Bulk Suspend Users', function () {
    test('superadmin can bulk suspend users', function () {
        $userIds = $this->regularUsers->pluck('id')->toArray();

        $response = $this->post('/admin/users/bulk-suspend', [
            'user_ids' => $userIds,
            'reason' => 'Test bulk suspension',
        ]);

        $response->assertRedirect();

        // Verify users are suspended
        foreach ($this->regularUsers as $user) {
            $user->refresh();
            expect($user->status)->toBe('suspended');
            expect($user->suspension_reason)->toBe('Test bulk suspension');
        }
    });

    test('cannot bulk suspend superadmins', function () {
        $anotherSuperadmin = User::factory()->create([
            'is_superadmin' => true,
            'status' => 'active',
        ]);

        $response = $this->post('/admin/users/bulk-suspend', [
            'user_ids' => [$anotherSuperadmin->id],
            'reason' => 'Test',
        ]);

        $response->assertRedirect();

        // Superadmin should not be suspended
        $anotherSuperadmin->refresh();
        expect($anotherSuperadmin->status)->toBe('active');
    });

    test('regular user cannot bulk suspend', function () {
        $regularUser = User::factory()->create(['status' => 'active']);
        $this->actingAs($regularUser);

        $response = $this->post('/admin/users/bulk-suspend', [
            'user_ids' => $this->regularUsers->pluck('id')->toArray(),
        ]);

        // is.superadmin middleware returns 403 for non-superadmin users
        $response->assertForbidden();
    });

    test('returns error when no users selected', function () {
        $response = $this->post('/admin/users/bulk-suspend', [
            'user_ids' => [],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'No users selected');
    });
});

describe('Bulk Activate Users', function () {
    test('superadmin can bulk activate suspended users', function () {
        // Suspend users first
        foreach ($this->regularUsers as $user) {
            $user->suspend('Test reason');
        }

        $userIds = $this->regularUsers->pluck('id')->toArray();

        $response = $this->post('/admin/users/bulk-activate', [
            'user_ids' => $userIds,
        ]);

        $response->assertRedirect();

        // Verify users are activated
        foreach ($this->regularUsers as $user) {
            $user->refresh();
            expect($user->status)->toBe('active');
        }
    });

    test('skips already active users', function () {
        $userIds = $this->regularUsers->pluck('id')->toArray();

        $response = $this->post('/admin/users/bulk-activate', [
            'user_ids' => $userIds,
        ]);

        $response->assertRedirect();
        // Should succeed but activate count would be 0
    });
});

describe('Bulk Delete Users', function () {
    test('superadmin can bulk delete users', function () {
        $userIds = $this->regularUsers->pluck('id')->toArray();

        $response = $this->delete('/admin/users/bulk-delete', [
            'user_ids' => $userIds,
        ]);

        $response->assertRedirect();

        // Verify users are deleted
        foreach ($userIds as $userId) {
            expect(User::find($userId))->toBeNull();
        }
    });

    test('cannot bulk delete superadmins', function () {
        $anotherSuperadmin = User::factory()->create([
            'is_superadmin' => true,
        ]);

        $response = $this->delete('/admin/users/bulk-delete', [
            'user_ids' => [$anotherSuperadmin->id],
        ]);

        $response->assertRedirect();

        // Superadmin should still exist
        expect(User::find($anotherSuperadmin->id))->not->toBeNull();
    });
});

describe('Export Users', function () {
    test('superadmin can export users to CSV', function () {
        $response = $this->get('/admin/users/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');

        // Check CSV content
        $content = $response->getContent();
        expect($content)->toContain('ID,Name,Email,Status,Teams,Created At,Last Login');
    });

    test('export respects search filter', function () {
        // Create a user with unique email
        $specialUser = User::factory()->create([
            'email' => 'unique-test-export@example.com',
            'status' => 'active',
        ]);

        $response = $this->get('/admin/users/export?search=unique-test-export');

        $response->assertStatus(200);
        $content = $response->getContent();
        expect($content)->toContain('unique-test-export@example.com');
    });

    test('export respects status filter', function () {
        // Create a suspended user
        $suspendedUser = User::factory()->create([
            'status' => 'suspended',
        ]);

        $response = $this->get('/admin/users/export?status=suspended');

        $response->assertStatus(200);
        $content = $response->getContent();
        expect($content)->toContain($suspendedUser->email);
    });

    test('regular user cannot export', function () {
        $regularUser = User::factory()->create(['status' => 'active']);
        $this->actingAs($regularUser);

        $response = $this->get('/admin/users/export');

        $response->assertStatus(403);
    });
});

describe('Audit Logging', function () {
    test('bulk operations are logged to audit log', function () {
        $userIds = $this->regularUsers->pluck('id')->toArray();

        $this->post('/admin/users/bulk-suspend', [
            'user_ids' => $userIds,
            'reason' => 'Test audit',
        ]);

        // Check audit log
        $auditLog = \App\Models\AuditLog::where('action', 'users_bulk_suspended')->first();
        expect($auditLog)->not->toBeNull();
        expect($auditLog->user_id)->toBe($this->superadmin->id);
    });
});
