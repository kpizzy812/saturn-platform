<?php

/**
 * Unit tests for Role enum.
 *
 * Tests cover:
 * - Case values (string contract)
 * - rank() — hierarchy order (1-6)
 * - lt() / gt() — comparison by rank, with enum and string arguments
 */

use App\Enums\Role;

// ─── Case values ──────────────────────────────────────────────────────────────

test('VIEWER value is viewer', function () {
    expect(Role::VIEWER->value)->toBe('viewer');
});

test('MEMBER value is member', function () {
    expect(Role::MEMBER->value)->toBe('member');
});

test('DEVELOPER value is developer', function () {
    expect(Role::DEVELOPER->value)->toBe('developer');
});

test('ADMIN value is admin', function () {
    expect(Role::ADMIN->value)->toBe('admin');
});

test('OWNER value is owner', function () {
    expect(Role::OWNER->value)->toBe('owner');
});

test('SUPERADMIN value is superadmin', function () {
    expect(Role::SUPERADMIN->value)->toBe('superadmin');
});

// ─── rank() ───────────────────────────────────────────────────────────────────

test('VIEWER has rank 1', function () {
    expect(Role::VIEWER->rank())->toBe(1);
});

test('MEMBER has rank 2', function () {
    expect(Role::MEMBER->rank())->toBe(2);
});

test('DEVELOPER has rank 3', function () {
    expect(Role::DEVELOPER->rank())->toBe(3);
});

test('ADMIN has rank 4', function () {
    expect(Role::ADMIN->rank())->toBe(4);
});

test('OWNER has rank 5', function () {
    expect(Role::OWNER->rank())->toBe(5);
});

test('SUPERADMIN has rank 6', function () {
    expect(Role::SUPERADMIN->rank())->toBe(6);
});

test('roles are in ascending rank order', function () {
    $roles = [Role::VIEWER, Role::MEMBER, Role::DEVELOPER, Role::ADMIN, Role::OWNER, Role::SUPERADMIN];
    $ranks = array_map(fn ($r) => $r->rank(), $roles);
    $sorted = $ranks;
    sort($sorted);
    expect($ranks)->toBe($sorted);
});

// ─── lt() — less than ────────────────────────────────────────────────────────

test('VIEWER is less than ADMIN', function () {
    expect(Role::VIEWER->lt(Role::ADMIN))->toBeTrue();
});

test('MEMBER is less than OWNER', function () {
    expect(Role::MEMBER->lt(Role::OWNER))->toBeTrue();
});

test('ADMIN is not less than MEMBER', function () {
    expect(Role::ADMIN->lt(Role::MEMBER))->toBeFalse();
});

test('OWNER is not less than OWNER', function () {
    expect(Role::OWNER->lt(Role::OWNER))->toBeFalse();
});

test('lt accepts string argument for VIEWER vs admin', function () {
    expect(Role::VIEWER->lt('admin'))->toBeTrue();
});

test('lt accepts string argument for OWNER vs member', function () {
    expect(Role::OWNER->lt('member'))->toBeFalse();
});

// ─── gt() — greater than ──────────────────────────────────────────────────────

test('ADMIN is greater than VIEWER', function () {
    expect(Role::ADMIN->gt(Role::VIEWER))->toBeTrue();
});

test('OWNER is greater than DEVELOPER', function () {
    expect(Role::OWNER->gt(Role::DEVELOPER))->toBeTrue();
});

test('VIEWER is not greater than ADMIN', function () {
    expect(Role::VIEWER->gt(Role::ADMIN))->toBeFalse();
});

test('MEMBER is not greater than MEMBER', function () {
    expect(Role::MEMBER->gt(Role::MEMBER))->toBeFalse();
});

test('gt accepts string argument for SUPERADMIN vs owner', function () {
    expect(Role::SUPERADMIN->gt('owner'))->toBeTrue();
});

test('gt accepts string argument for VIEWER vs admin', function () {
    expect(Role::VIEWER->gt('admin'))->toBeFalse();
});

// ─── lt() and gt() are inverse ───────────────────────────────────────────────

test('lt and gt are inverse for different roles', function () {
    expect(Role::VIEWER->lt(Role::ADMIN))->toBeTrue();
    expect(Role::ADMIN->gt(Role::VIEWER))->toBeTrue();
    expect(Role::VIEWER->gt(Role::ADMIN))->toBeFalse();
    expect(Role::ADMIN->lt(Role::VIEWER))->toBeFalse();
});
