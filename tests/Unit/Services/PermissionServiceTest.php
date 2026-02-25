<?php

/**
 * Unit tests for PermissionService.
 *
 * Tests permission checking logic for permission sets.
 * Tests the hardcoded role-based fallback logic that doesn't require database.
 */

use App\Services\Authorization\PermissionService;
use Mockery as m;

beforeEach(function () {
    $this->service = new PermissionService;
});

afterEach(function () {
    m::close();
});

describe('getHardcodedRolePermission', function () {
    it('grants all permissions to owner role', function () {
        $service = new PermissionService;

        // Use reflection to test private method
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        // Owner should have all permissions
        expect($method->invoke($service, 'owner', 'applications.view'))->toBeTrue();
        expect($method->invoke($service, 'owner', 'applications.delete'))->toBeTrue();
        expect($method->invoke($service, 'owner', 'team.manage_roles'))->toBeTrue();
        expect($method->invoke($service, 'owner', 'settings.billing'))->toBeTrue();
    });

    it('grants most permissions to admin role except billing and manage_roles', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        // Admin should have most permissions
        expect($method->invoke($service, 'admin', 'applications.view'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'applications.delete'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'team.invite'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'team.manage_members'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'servers.security'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'applications.env_vars_sensitive'))->toBeTrue();

        // Admin should NOT have owner-only permissions
        expect($method->invoke($service, 'admin', 'team.manage_roles'))->toBeFalse();
        expect($method->invoke($service, 'admin', 'settings.billing'))->toBeFalse();
    });

    it('grants development permissions to developer role', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        // Developer should have development permissions
        expect($method->invoke($service, 'developer', 'applications.view'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'applications.create'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'applications.update'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'applications.env_vars'))->toBeTrue();

        // Developer should NOT have admin permissions
        expect($method->invoke($service, 'developer', 'applications.delete'))->toBeFalse();
        expect($method->invoke($service, 'developer', 'team.manage_members'))->toBeFalse();
        expect($method->invoke($service, 'developer', 'servers.security'))->toBeFalse();
        expect($method->invoke($service, 'developer', 'applications.env_vars_sensitive'))->toBeFalse();
    });

    it('grants limited permissions to member role', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        // Member should have basic operational permissions
        expect($method->invoke($service, 'member', 'applications.view'))->toBeTrue();
        expect($method->invoke($service, 'member', 'applications.logs'))->toBeTrue();
        expect($method->invoke($service, 'member', 'applications.deploy'))->toBeTrue();
        expect($method->invoke($service, 'member', 'applications.manage'))->toBeTrue();

        // Member should NOT have create/update/delete permissions
        expect($method->invoke($service, 'member', 'applications.create'))->toBeFalse();
        expect($method->invoke($service, 'member', 'applications.update'))->toBeFalse();
        expect($method->invoke($service, 'member', 'applications.delete'))->toBeFalse();
    });

    it('grants read-only permissions to viewer role', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        // Viewer should have view-only permissions
        expect($method->invoke($service, 'viewer', 'applications.view'))->toBeTrue();
        expect($method->invoke($service, 'viewer', 'applications.logs'))->toBeTrue();
        expect($method->invoke($service, 'viewer', 'databases.view'))->toBeTrue();
        expect($method->invoke($service, 'viewer', 'servers.view'))->toBeTrue();

        // Viewer should NOT have any write permissions
        expect($method->invoke($service, 'viewer', 'applications.deploy'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'applications.create'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'applications.update'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'applications.delete'))->toBeFalse();
    });

    it('denies all permissions to unknown role', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        expect($method->invoke($service, 'unknown_role', 'applications.view'))->toBeFalse();
        expect($method->invoke($service, '', 'applications.view'))->toBeFalse();
    });
});

describe('role hierarchy', function () {
    it('maintains correct role ranking', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        // A permission that requires developer level (rank 3): create
        // Should be granted to developer, admin, owner but not member, viewer
        expect($method->invoke($service, 'owner', 'applications.create'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'applications.create'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'applications.create'))->toBeTrue();
        expect($method->invoke($service, 'member', 'applications.create'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'applications.create'))->toBeFalse();
    });

    it('respects delete permission hierarchy (admin and above)', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        // Delete requires admin level (rank 4)
        expect($method->invoke($service, 'owner', 'applications.delete'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'applications.delete'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'applications.delete'))->toBeFalse();
        expect($method->invoke($service, 'member', 'applications.delete'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'applications.delete'))->toBeFalse();
    });

    it('respects manage_roles permission hierarchy (owner only)', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        // manage_roles requires owner level (rank 5)
        expect($method->invoke($service, 'owner', 'team.manage_roles'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'team.manage_roles'))->toBeFalse();
        expect($method->invoke($service, 'developer', 'team.manage_roles'))->toBeFalse();
        expect($method->invoke($service, 'member', 'team.manage_roles'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'team.manage_roles'))->toBeFalse();
    });
});

describe('sensitive permissions', function () {
    it('restricts env_vars_sensitive to admin and above', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        expect($method->invoke($service, 'owner', 'applications.env_vars_sensitive'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'applications.env_vars_sensitive'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'applications.env_vars_sensitive'))->toBeFalse();
        expect($method->invoke($service, 'member', 'applications.env_vars_sensitive'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'applications.env_vars_sensitive'))->toBeFalse();
    });

    it('allows env_vars to developer and above', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        expect($method->invoke($service, 'owner', 'applications.env_vars'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'applications.env_vars'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'applications.env_vars'))->toBeTrue();
        expect($method->invoke($service, 'member', 'applications.env_vars'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'applications.env_vars'))->toBeFalse();
    });

    it('restricts security settings to admin and above', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        expect($method->invoke($service, 'owner', 'servers.security'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'servers.security'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'servers.security'))->toBeFalse();
        expect($method->invoke($service, 'member', 'servers.security'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'servers.security'))->toBeFalse();
    });

    it('restricts billing to owner only', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        expect($method->invoke($service, 'owner', 'settings.billing'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'settings.billing'))->toBeFalse();
        expect($method->invoke($service, 'developer', 'settings.billing'))->toBeFalse();
        expect($method->invoke($service, 'member', 'settings.billing'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'settings.billing'))->toBeFalse();
    });
});

describe('team management permissions', function () {
    it('allows invite to admin and above', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        expect($method->invoke($service, 'owner', 'team.invite'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'team.invite'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'team.invite'))->toBeFalse();
        expect($method->invoke($service, 'member', 'team.invite'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'team.invite'))->toBeFalse();
    });

    it('allows manage_members to admin and above', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        expect($method->invoke($service, 'owner', 'team.manage_members'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'team.manage_members'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'team.manage_members'))->toBeFalse();
        expect($method->invoke($service, 'member', 'team.manage_members'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'team.manage_members'))->toBeFalse();
    });
});

describe('unknown action handling', function () {
    it('defaults to developer level for unknown actions', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        // Unknown action defaults to rank 3 (developer level)
        expect($method->invoke($service, 'owner', 'applications.unknown_action'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'applications.unknown_action'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'applications.unknown_action'))->toBeTrue();
        expect($method->invoke($service, 'member', 'applications.unknown_action'))->toBeFalse();
        expect($method->invoke($service, 'viewer', 'applications.unknown_action'))->toBeFalse();
    });
});

describe('deployment permissions', function () {
    it('allows deploy to member and above', function () {
        $service = new PermissionService;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getHardcodedRolePermission');

        expect($method->invoke($service, 'owner', 'applications.deploy'))->toBeTrue();
        expect($method->invoke($service, 'admin', 'applications.deploy'))->toBeTrue();
        expect($method->invoke($service, 'developer', 'applications.deploy'))->toBeTrue();
        expect($method->invoke($service, 'member', 'applications.deploy'))->toBeTrue();
        expect($method->invoke($service, 'viewer', 'applications.deploy'))->toBeFalse();
    });
});
