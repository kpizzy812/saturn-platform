<?php

use App\Models\User;
use Carbon\Carbon;

// Platform Role Tests
test('platformRole returns platform_role when set', function () {
    $user = new User;
    $user->platform_role = 'admin';

    expect($user->platformRole())->toBe('admin');
});

test('platformRole returns member when platform_role is null', function () {
    $user = new User;
    $user->platform_role = null;

    expect($user->platformRole())->toBe('member');
});

// isPlatformOwner Tests
test('isPlatformOwner returns true for user with id 0', function () {
    $user = new User;
    $user->id = 0;
    $user->platform_role = 'member';

    expect($user->isPlatformOwner())->toBeTrue();
});

test('isPlatformOwner returns true for user with owner role', function () {
    $user = new User;
    $user->id = 5;
    $user->platform_role = 'owner';

    expect($user->isPlatformOwner())->toBeTrue();
});

test('isPlatformOwner returns false for regular user', function () {
    $user = new User;
    $user->id = 5;
    $user->platform_role = 'admin';

    expect($user->isPlatformOwner())->toBeFalse();
});

// isPlatformAdmin Tests
test('isPlatformAdmin returns true for owner', function () {
    $user = new User;
    $user->platform_role = 'owner';

    expect($user->isPlatformAdmin())->toBeTrue();
});

test('isPlatformAdmin returns true for admin', function () {
    $user = new User;
    $user->platform_role = 'admin';

    expect($user->isPlatformAdmin())->toBeTrue();
});

test('isPlatformAdmin returns false for member', function () {
    $user = new User;
    $user->platform_role = 'member';

    expect($user->isPlatformAdmin())->toBeFalse();
});

test('isPlatformAdmin returns false for developer', function () {
    $user = new User;
    $user->platform_role = 'developer';

    expect($user->isPlatformAdmin())->toBeFalse();
});

// isSuperAdmin Tests
test('isSuperAdmin returns true for user id 0', function () {
    $user = new User;
    $user->id = 0;
    $user->is_superadmin = false;

    expect($user->isSuperAdmin())->toBeTrue();
});

test('isSuperAdmin returns true when is_superadmin flag is set', function () {
    $user = new User;
    $user->id = 42;
    $user->is_superadmin = true;

    expect($user->isSuperAdmin())->toBeTrue();
});

test('isSuperAdmin returns false for regular user', function () {
    $user = new User;
    $user->id = 42;
    $user->is_superadmin = false;

    expect($user->isSuperAdmin())->toBeFalse();
});

// Status Check Tests
test('isActive returns true when status is active', function () {
    $user = new User;
    $user->status = 'active';

    expect($user->isActive())->toBeTrue();
});

test('isActive returns false when status is not active', function () {
    $user = new User;
    $user->status = 'suspended';

    expect($user->isActive())->toBeFalse();
});

test('isSuspended returns true when status is suspended', function () {
    $user = new User;
    $user->status = 'suspended';

    expect($user->isSuspended())->toBeTrue();
});

test('isSuspended returns false when status is active', function () {
    $user = new User;
    $user->status = 'active';

    expect($user->isSuspended())->toBeFalse();
});

test('isBanned returns true when status is banned', function () {
    $user = new User;
    $user->status = 'banned';

    expect($user->isBanned())->toBeTrue();
});

test('isBanned returns false when status is active', function () {
    $user = new User;
    $user->status = 'active';

    expect($user->isBanned())->toBeFalse();
});

test('isPending returns true when status is pending', function () {
    $user = new User;
    $user->status = 'pending';

    expect($user->isPending())->toBeTrue();
});

test('isPending returns false when status is active', function () {
    $user = new User;
    $user->status = 'active';

    expect($user->isPending())->toBeFalse();
});

// hasPassword Tests
test('hasPassword returns true when password is set', function () {
    $user = new User;
    $user->password = '$2y$10$hashed_password_here';

    expect($user->hasPassword())->toBeTrue();
});

test('hasPassword returns false when password is null', function () {
    $user = new User;
    $user->password = null;

    expect($user->hasPassword())->toBeFalse();
});

test('hasPassword returns false when password is empty string', function () {
    $user = new User;
    $user->password = '';

    expect($user->hasPassword())->toBeFalse();
});

// hasEmailChangeRequest Tests
test('hasEmailChangeRequest returns true when valid request exists', function () {
    $user = new User;
    $user->pending_email = 'new@example.com';
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->addMinutes(10);

    expect($user->hasEmailChangeRequest())->toBeTrue();
});

test('hasEmailChangeRequest returns false when no pending email', function () {
    $user = new User;
    $user->pending_email = null;
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->addMinutes(10);

    expect($user->hasEmailChangeRequest())->toBeFalse();
});

test('hasEmailChangeRequest returns false when code expired', function () {
    $user = new User;
    $user->pending_email = 'new@example.com';
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->subMinutes(5);

    expect($user->hasEmailChangeRequest())->toBeFalse();
});

test('hasEmailChangeRequest returns false when no code', function () {
    $user = new User;
    $user->pending_email = 'new@example.com';
    $user->email_change_code = null;
    $user->email_change_code_expires_at = Carbon::now()->addMinutes(10);

    expect($user->hasEmailChangeRequest())->toBeFalse();
});

// isEmailChangeCodeValid Tests
test('isEmailChangeCodeValid returns true for matching code within expiry', function () {
    $user = new User;
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->addMinutes(10);

    expect($user->isEmailChangeCodeValid('123456'))->toBeTrue();
});

test('isEmailChangeCodeValid returns false for wrong code', function () {
    $user = new User;
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->addMinutes(10);

    expect($user->isEmailChangeCodeValid('654321'))->toBeFalse();
});

test('isEmailChangeCodeValid returns false for expired code', function () {
    $user = new User;
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = Carbon::now()->subMinutes(1);

    expect($user->isEmailChangeCodeValid('123456'))->toBeFalse();
});

test('isEmailChangeCodeValid returns false when no expiry set', function () {
    $user = new User;
    $user->email_change_code = '123456';
    $user->email_change_code_expires_at = null;

    expect($user->isEmailChangeCodeValid('123456'))->toBeFalse();
});

// getRecipients Tests
test('getRecipients returns array with user email', function () {
    $user = new User;
    $user->email = 'test@example.com';

    expect($user->getRecipients())->toBe(['test@example.com']);
});
