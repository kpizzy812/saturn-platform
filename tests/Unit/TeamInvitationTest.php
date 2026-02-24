<?php

use App\Models\TeamInvitation;

it('has correct fillable fields including invitation settings', function () {
    $invitation = new TeamInvitation;
    $fillable = $invitation->getFillable();

    expect($fillable)->toContain('allowed_projects');
    expect($fillable)->toContain('permission_set_id');
    expect($fillable)->toContain('custom_permissions');
    expect($fillable)->toContain('team_id');
    expect($fillable)->toContain('email');
    expect($fillable)->toContain('role');
    expect($fillable)->toContain('uuid');
    expect($fillable)->toContain('invited_by');
});

it('casts allowed_projects as array', function () {
    $invitation = new TeamInvitation;
    $casts = $invitation->getCasts();

    expect($casts)->toHaveKey('allowed_projects');
    expect($casts['allowed_projects'])->toBe('array');
});

it('casts custom_permissions as array', function () {
    $invitation = new TeamInvitation;
    $casts = $invitation->getCasts();

    expect($casts)->toHaveKey('custom_permissions');
    expect($casts['custom_permissions'])->toBe('array');
});

it('sets email to lowercase', function () {
    $invitation = new TeamInvitation;
    $invitation->email = 'Test@Example.COM';

    expect($invitation->email)->toBe('test@example.com');
});

it('can be instantiated with invitation settings', function () {
    $invitation = new TeamInvitation;
    $invitation->team_id = 1;
    $invitation->uuid = 'test-uuid-123';
    $invitation->email = 'test@example.com';
    $invitation->role = 'developer';
    $invitation->link = 'https://example.com/invite/test-uuid-123';
    $invitation->via = 'link';
    $invitation->allowed_projects = [1, 2, 3];
    $invitation->permission_set_id = 5;
    $invitation->custom_permissions = [
        ['permission_id' => 1, 'environment_restrictions' => ['production' => false]],
        ['permission_id' => 2, 'environment_restrictions' => []],
    ];

    expect($invitation->team_id)->toBe(1);
    expect($invitation->email)->toBe('test@example.com');
    expect($invitation->role)->toBe('developer');
    expect($invitation->allowed_projects)->toBe([1, 2, 3]);
    expect($invitation->permission_set_id)->toBe(5);
    expect($invitation->custom_permissions)->toBeArray();
    expect($invitation->custom_permissions)->toHaveCount(2);
    expect($invitation->custom_permissions[0]['permission_id'])->toBe(1);
});

it('allows null for invitation settings (defaults)', function () {
    $invitation = new TeamInvitation;
    $invitation->team_id = 1;
    $invitation->uuid = 'test-uuid-456';
    $invitation->email = 'test@example.com';
    $invitation->role = 'member';
    $invitation->link = 'https://example.com/invite/test-uuid-456';

    // These should all be null by default (no custom settings)
    expect($invitation->allowed_projects)->toBeNull();
    expect($invitation->permission_set_id)->toBeNull();
    expect($invitation->custom_permissions)->toBeNull();
});
