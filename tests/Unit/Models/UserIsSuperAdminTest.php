<?php

declare(strict_types=1);

use App\Models\User;

test('isSuperAdmin returns true for user with id 0', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 0;
    $user->is_superadmin = false;

    expect($user->isSuperAdmin())->toBeTrue();
});

test('isSuperAdmin returns true for user with is_superadmin flag', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 123;
    $user->is_superadmin = true;

    expect($user->isSuperAdmin())->toBeTrue();
});

test('isSuperAdmin returns false for regular user', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 456;
    $user->is_superadmin = false;

    expect($user->isSuperAdmin())->toBeFalse();
});

test('isSuperAdmin returns false for user with null is_superadmin', function () {
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 789;
    $user->is_superadmin = null;

    expect($user->isSuperAdmin())->toBeFalse();
});

test('isSuperAdmin checks instance id not auth id', function () {
    // This test ensures isSuperAdmin checks $this->id, not Auth::id()
    // Create two users - one regular, one with id=0
    $regularUser = Mockery::mock(User::class)->makePartial();
    $regularUser->id = 100;
    $regularUser->is_superadmin = false;

    $rootUser = Mockery::mock(User::class)->makePartial();
    $rootUser->id = 0;
    $rootUser->is_superadmin = false;

    // Regular user should NOT be superadmin regardless of who is logged in
    expect($regularUser->isSuperAdmin())->toBeFalse();

    // Root user (id=0) SHOULD be superadmin
    expect($rootUser->isSuperAdmin())->toBeTrue();
});
