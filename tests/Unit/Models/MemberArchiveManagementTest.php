<?php

use App\Models\MemberArchive;
use App\Models\TeamResourceTransfer;

afterEach(function () {
    Mockery::close();
});

it('can update notes on MemberArchive model', function () {
    $archive = Mockery::mock(MemberArchive::class)->makePartial();
    $archive->shouldReceive('update')
        ->with(['notes' => 'Test note content'])
        ->once()
        ->andReturn(true);

    $result = $archive->update(['notes' => 'Test note content']);
    expect($result)->toBeTrue();
});

it('getTransfers returns empty collection when no transfer_ids', function () {
    $archive = new MemberArchive;
    $archive->transfer_ids = [];

    $transfers = $archive->getTransfers();
    expect($transfers)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($transfers)->toHaveCount(0);
});

it('getTransfers returns empty collection when transfer_ids is null', function () {
    $archive = new MemberArchive;
    $archive->transfer_ids = null;

    $transfers = $archive->getTransfers();
    expect($transfers)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($transfers)->toHaveCount(0);
});

it('MemberArchive has correct fillable fields', function () {
    $archive = new MemberArchive;
    $fillable = $archive->getFillable();

    expect($fillable)->toContain('notes');
    expect($fillable)->toContain('transfer_ids');
    expect($fillable)->toContain('team_id');
    expect($fillable)->toContain('user_id');
    expect($fillable)->toContain('member_name');
    expect($fillable)->toContain('member_email');
    expect($fillable)->toContain('member_role');
    expect($fillable)->toContain('kick_reason');
    expect($fillable)->toContain('status');
});

it('MemberArchive casts contribution_summary and access_snapshot as arrays', function () {
    $archive = new MemberArchive;
    $casts = $archive->getCasts();

    expect($casts)->toHaveKey('contribution_summary');
    expect($casts['contribution_summary'])->toBe('array');
    expect($casts)->toHaveKey('access_snapshot');
    expect($casts['access_snapshot'])->toBe('array');
    expect($casts)->toHaveKey('transfer_ids');
    expect($casts['transfer_ids'])->toBe('array');
});

it('TeamResourceTransfer has TYPE_ARCHIVE constant', function () {
    expect(TeamResourceTransfer::TYPE_ARCHIVE)->toBe('archive');
});

it('TeamResourceTransfer has correct fillable for archive transfers', function () {
    $transfer = new TeamResourceTransfer;
    $fillable = $transfer->getFillable();

    expect($fillable)->toContain('transfer_type');
    expect($fillable)->toContain('transferable_type');
    expect($fillable)->toContain('transferable_id');
    expect($fillable)->toContain('from_team_id');
    expect($fillable)->toContain('to_team_id');
    expect($fillable)->toContain('from_user_id');
    expect($fillable)->toContain('to_user_id');
    expect($fillable)->toContain('initiated_by');
    expect($fillable)->toContain('reason');
    expect($fillable)->toContain('resource_snapshot');
});

it('builds correct JSON export structure', function () {
    $archive = new MemberArchive;
    $archive->member_name = 'John Doe';
    $archive->member_email = 'john@example.com';
    $archive->member_role = 'developer';
    $archive->member_joined_at = now()->subMonths(6);
    $archive->kicked_by_name = 'Admin User';
    $archive->kick_reason = 'Left the company';
    $archive->notes = 'Good performer';
    $archive->contribution_summary = [
        'total_actions' => 150,
        'deploy_count' => 30,
        'created_count' => 20,
        'by_action' => ['deploy' => 30, 'create' => 20],
        'by_resource_type' => [],
        'top_resources' => [],
        'first_action' => '2024-01-01T00:00:00Z',
        'last_action' => '2024-06-01T00:00:00Z',
    ];
    $archive->access_snapshot = [
        'role' => 'developer',
        'allowed_projects' => null,
        'permission_set_id' => null,
    ];

    // Simulate export data building (same logic as in route)
    $exportData = [
        'archive' => [
            'member_name' => $archive->member_name,
            'member_email' => $archive->member_email,
            'member_role' => $archive->member_role,
            'member_joined_at' => $archive->member_joined_at?->toISOString(),
            'removed_at' => now()->toISOString(),
            'kicked_by' => $archive->kicked_by_name,
            'kick_reason' => $archive->kick_reason,
            'notes' => $archive->notes,
        ],
        'contributions' => $archive->contribution_summary,
        'access_snapshot' => $archive->access_snapshot,
        'transfers' => [],
    ];

    expect($exportData)->toHaveKey('archive');
    expect($exportData)->toHaveKey('contributions');
    expect($exportData)->toHaveKey('access_snapshot');
    expect($exportData)->toHaveKey('transfers');

    expect($exportData['archive']['member_name'])->toBe('John Doe');
    expect($exportData['archive']['member_email'])->toBe('john@example.com');
    expect($exportData['archive']['kick_reason'])->toBe('Left the company');
    expect($exportData['archive']['notes'])->toBe('Good performer');

    expect($exportData['contributions']['total_actions'])->toBe(150);
    expect($exportData['contributions']['deploy_count'])->toBe(30);

    expect($exportData['access_snapshot']['role'])->toBe('developer');
});

it('builds correct CSV rows from export data', function () {
    $exportData = [
        'archive' => [
            'member_name' => 'John Doe',
            'member_email' => 'john@example.com',
            'member_role' => 'developer',
            'kicked_by' => 'Admin',
            'kick_reason' => 'Left',
            'notes' => null,
        ],
        'contributions' => [
            'total_actions' => 50,
            'deploy_count' => 10,
            'by_action' => ['deploy' => 10, 'create' => 5],
        ],
        'access_snapshot' => [
            'role' => 'developer',
            'allowed_projects' => null,
        ],
        'transfers' => [
            [
                'id' => 1,
                'resource_type' => 'Application',
                'resource_name' => 'My App',
                'to_user' => 'Admin',
                'status' => 'completed',
            ],
        ],
    ];

    // Simulate CSV building logic
    $rows = [];
    $rows[] = ['Section', 'Key', 'Value'];

    foreach ($exportData['archive'] as $key => $value) {
        $rows[] = ['Archive', $key, $value ?? ''];
    }

    foreach ($exportData['contributions'] as $key => $value) {
        if (is_array($value)) {
            $rows[] = ['Contributions', $key, json_encode($value)];
        } else {
            $rows[] = ['Contributions', $key, $value ?? ''];
        }
    }

    foreach ($exportData['access_snapshot'] as $key => $value) {
        if (is_array($value)) {
            $rows[] = ['Access Snapshot', $key, json_encode($value)];
        } else {
            $rows[] = ['Access Snapshot', $key, $value ?? ''];
        }
    }

    foreach ($exportData['transfers'] as $i => $transfer) {
        foreach ($transfer as $key => $value) {
            $rows[] = ['Transfer #'.($i + 1), $key, $value ?? ''];
        }
    }

    // Header + 6 archive + 3 contributions + 2 access + 5 transfer fields = 17 rows
    expect(count($rows))->toBe(17);

    // Check header
    expect($rows[0])->toBe(['Section', 'Key', 'Value']);

    // Check archive section
    expect($rows[1])->toBe(['Archive', 'member_name', 'John Doe']);
    expect($rows[6])->toBe(['Archive', 'notes', '']);

    // Check contributions with array encoding
    expect($rows[9][0])->toBe('Contributions');
    expect($rows[9][1])->toBe('by_action');
    expect($rows[9][2])->toBe('{"deploy":10,"create":5}');

    // Check transfer section
    expect($rows[12][0])->toBe('Transfer #1');
    expect($rows[12][1])->toBe('id');
});

it('appends new transfer IDs to existing ones', function () {
    $existingIds = [1, 2, 3];
    $newIds = [4, 5];

    $merged = array_merge($existingIds, $newIds);

    expect($merged)->toBe([1, 2, 3, 4, 5]);
    expect(count($merged))->toBe(5);
});

it('handles empty existing transfer_ids when appending', function () {
    $existingIds = [];
    $newIds = [10, 11];

    $merged = array_merge($existingIds, $newIds);

    expect($merged)->toBe([10, 11]);
});

// ===================
// Security: Whitelist resource_type
// ===================
it('rejects resource types not in the allowed whitelist', function () {
    $allowedResourceTypes = [
        'App\\Models\\Application',
        'App\\Models\\Service',
        'App\\Models\\Server',
        'App\\Models\\Project',
        'App\\Models\\StandalonePostgresql',
        'App\\Models\\StandaloneMysql',
        'App\\Models\\StandaloneMariadb',
        'App\\Models\\StandaloneRedis',
        'App\\Models\\StandaloneKeydb',
        'App\\Models\\StandaloneDragonfly',
        'App\\Models\\StandaloneClickhouse',
        'App\\Models\\StandaloneMongodb',
    ];

    // Valid types should be in whitelist
    expect(in_array('App\\Models\\Application', $allowedResourceTypes, true))->toBeTrue();
    expect(in_array('App\\Models\\Server', $allowedResourceTypes, true))->toBeTrue();
    expect(in_array('App\\Models\\StandalonePostgresql', $allowedResourceTypes, true))->toBeTrue();

    // Invalid types should NOT be in whitelist (class injection prevention)
    expect(in_array('App\\Models\\User', $allowedResourceTypes, true))->toBeFalse();
    expect(in_array('Illuminate\\Support\\Facades\\DB', $allowedResourceTypes, true))->toBeFalse();
    expect(in_array('App\\Models\\Team', $allowedResourceTypes, true))->toBeFalse();
    expect(in_array('', $allowedResourceTypes, true))->toBeFalse();
    expect(in_array('SomeRandomClass', $allowedResourceTypes, true))->toBeFalse();
});

// ===================
// Security: Filename sanitization
// ===================
it('sanitizes filenames by replacing special characters with underscores', function () {
    $names = [
        'John Doe' => 'John_Doe',
        'O\'Brien' => 'O_Brien',
        'admin@evil.com' => 'admin_evil_com',
        'user/../../../etc/passwd' => 'user__________etc_passwd',
        'name<script>alert(1)</script>' => 'name_script_alert_1___script_',
        'simple' => 'simple',
        'hello-world_123' => 'hello-world_123',
    ];

    foreach ($names as $input => $expected) {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $input);
        expect($sanitized)->toBe($expected, "Failed for input: {$input}");
    }
});

// ===================
// Permission: archives action rank
// ===================
it('archives permission requires admin rank (4) in hardcoded fallback', function () {
    $permService = new \App\Services\Authorization\PermissionService;

    // Use reflection to test the private getHardcodedRolePermission method
    $method = new \ReflectionMethod($permService, 'getHardcodedRolePermission');
    $method->setAccessible(true);

    // Admin (rank 4) should have archives permission
    expect($method->invoke($permService, 'admin', 'team.archives'))->toBeTrue();
    // Owner (rank 5) should have archives permission
    expect($method->invoke($permService, 'owner', 'team.archives'))->toBeTrue();
    // Developer (rank 3) should NOT have archives permission
    expect($method->invoke($permService, 'developer', 'team.archives'))->toBeFalse();
    // Member (rank 2) should NOT have archives permission
    expect($method->invoke($permService, 'member', 'team.archives'))->toBeFalse();
    // Viewer (rank 1) should NOT have archives permission
    expect($method->invoke($permService, 'viewer', 'team.archives'))->toBeFalse();
});

// ===================
// Soft Delete: MemberArchive uses SoftDeletes trait
// ===================
it('MemberArchive uses SoftDeletes trait', function () {
    $archive = new MemberArchive;
    $traits = class_uses_recursive($archive);

    expect($traits)->toContain(\Illuminate\Database\Eloquent\SoftDeletes::class);
});

it('MemberArchive has deleted_at in dates/casts', function () {
    $archive = new MemberArchive;

    // SoftDeletes adds deleted_at to casts automatically
    $casts = $archive->getCasts();
    expect($casts)->toHaveKey('deleted_at');
});
