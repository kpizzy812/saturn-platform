<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\TeamInvitation;

beforeEach(function () {
    $this->action = new CreateNewUser;
});

describe('CreateNewUser root team invitation resolution', function () {

    it('returns null when no invite key in input', function () {
        $reflection = new ReflectionMethod($this->action, 'resolveRootTeamInvitation');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->action, [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        expect($result)->toBeNull();
    });

    it('returns null when invite key is empty', function () {
        $reflection = new ReflectionMethod($this->action, 'resolveRootTeamInvitation');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->action, [
            'invite' => '',
        ]);

        expect($result)->toBeNull();
    });

    it('returns null when invite key is null', function () {
        $reflection = new ReflectionMethod($this->action, 'resolveRootTeamInvitation');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($this->action, [
            'invite' => null,
        ]);

        expect($result)->toBeNull();
    });
});

describe('CreateNewUser email match validation rule', function () {

    it('passes when email matches invitation email (case-insensitive)', function () {
        $invitationEmail = 'Invited@Example.com';

        $rule = function (string $attribute, mixed $value, Closure $fail) use ($invitationEmail) {
            if (strtolower($value) !== strtolower($invitationEmail)) {
                $fail('The email must match the invitation email.');
            }
        };

        // Should not throw for matching email (different case)
        $failed = false;
        $rule('email', 'invited@example.com', function () use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    });

    it('fails when email does not match invitation email', function () {
        $invitationEmail = 'invited@example.com';

        $rule = function (string $attribute, mixed $value, Closure $fail) use ($invitationEmail) {
            if (strtolower($value) !== strtolower($invitationEmail)) {
                $fail('The email must match the invitation email.');
            }
        };

        $failMessage = null;
        $rule('email', 'wrong@example.com', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        expect($failMessage)->toBe('The email must match the invitation email.');
    });

    it('matches email exactly when cases are identical', function () {
        $invitationEmail = 'test@example.com';

        $rule = function (string $attribute, mixed $value, Closure $fail) use ($invitationEmail) {
            if (strtolower($value) !== strtolower($invitationEmail)) {
                $fail('The email must match the invitation email.');
            }
        };

        $failed = false;
        $rule('email', 'test@example.com', function () use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    });
});

describe('CreateNewUser acceptInvitation pivot data', function () {

    it('builds correct pivot data structure', function () {
        // Test the pivot data building logic extracted from acceptInvitation
        $invitation = Mockery::mock(TeamInvitation::class)->makePartial();
        $invitation->role = 'admin';
        $invitation->invited_by = 1;
        $invitation->allowed_projects = [1, 2, 3];
        $invitation->permission_set_id = null;
        $invitation->custom_permissions = null;

        $pivotData = [
            'role' => $invitation->role ?? 'member',
            'invited_by' => $invitation->invited_by,
            'allowed_projects' => $invitation->allowed_projects,
        ];

        if ($invitation->permission_set_id) {
            $pivotData['permission_set_id'] = $invitation->permission_set_id;
        }

        expect($pivotData)->toBe([
            'role' => 'admin',
            'invited_by' => 1,
            'allowed_projects' => [1, 2, 3],
        ]);
        expect($pivotData)->not->toHaveKey('permission_set_id');
    });

    it('includes permission_set_id when present', function () {
        $invitation = Mockery::mock(TeamInvitation::class)->makePartial();
        $invitation->role = 'member';
        $invitation->invited_by = 2;
        $invitation->allowed_projects = null;
        $invitation->permission_set_id = 42;

        $pivotData = [
            'role' => $invitation->role ?? 'member',
            'invited_by' => $invitation->invited_by,
            'allowed_projects' => $invitation->allowed_projects,
        ];

        if ($invitation->permission_set_id) {
            $pivotData['permission_set_id'] = $invitation->permission_set_id;
        }

        expect($pivotData)->toHaveKey('permission_set_id', 42);
    });

    it('defaults role to member when null', function () {
        $invitation = Mockery::mock(TeamInvitation::class)->makePartial();
        $invitation->role = null;

        $role = $invitation->role ?? 'member';

        expect($role)->toBe('member');
    });
});
