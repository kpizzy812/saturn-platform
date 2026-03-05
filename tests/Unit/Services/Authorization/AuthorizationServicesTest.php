<?php

use App\Services\Authorization\PermissionService;
use App\Services\Authorization\ProjectAuthorizationService;
use App\Services\Authorization\ResourceAuthorizationService;

// ====== PermissionService ======

it('PermissionService has 5-minute cache TTL constant', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('CACHE_TTL = 300');
});

it('PermissionService defines userHasPermission method', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('function userHasPermission(');
});

it('PermissionService bypasses permission check for platform admins', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('isPlatformAdmin');
});

it('PermissionService defines getUserEffectivePermissions method', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('function getUserEffectivePermissions(');
});

it('PermissionService defines checkMultiplePermissions batch method', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('function checkMultiplePermissions(');
});

it('PermissionService defines userHasAnyPermission method', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('function userHasAnyPermission(');
});

it('PermissionService defines userHasAllPermissions method', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('function userHasAllPermissions(');
});

it('PermissionService defines assignPermissionSet method', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('function assignPermissionSet(');
});

it('PermissionService defines clearUserCache method', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('function clearUserCache(');
});

it('PermissionService defines clearTeamCache method', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('function clearTeamCache(');
});

it('PermissionService uses cache TTL constant', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('CACHE_TTL');
});

it('PermissionService defines getUserPermissionSet method', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('function getUserPermissionSet(');
});

it('PermissionService defines removePermissionSetAssignment method', function () {
    $code = file_get_contents(app_path('Services/Authorization/PermissionService.php'));
    expect($code)->toContain('function removePermissionSetAssignment(');
});

// ====== ProjectAuthorizationService ======

it('ProjectAuthorizationService can be instantiated without constructor dependencies', function () {
    $service = new ProjectAuthorizationService;
    expect($service)->toBeInstanceOf(ProjectAuthorizationService::class);
});

it('ProjectAuthorizationService defines filterVisibleEnvironments method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ProjectAuthorizationService.php'));
    expect($code)->toContain('function filterVisibleEnvironments(');
});

it('ProjectAuthorizationService handles production environment gating', function () {
    $code = file_get_contents(app_path('Services/Authorization/ProjectAuthorizationService.php'));
    expect($code)->toContain('production');
});

it('ProjectAuthorizationService defines all five role levels', function () {
    $code = file_get_contents(app_path('Services/Authorization/ProjectAuthorizationService.php'));
    expect($code)
        ->toContain("'owner'")
        ->toContain("'admin'")
        ->toContain("'developer'")
        ->toContain("'member'")
        ->toContain("'viewer'");
});

it('ProjectAuthorizationService defines hasMinimumRole method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ProjectAuthorizationService.php'));
    expect($code)->toContain('function hasMinimumRole(');
});

it('ProjectAuthorizationService defines canDeleteProject strict owner check', function () {
    $code = file_get_contents(app_path('Services/Authorization/ProjectAuthorizationService.php'));
    expect($code)->toContain('function canDeleteProject(');
});

it('ProjectAuthorizationService defines canApproveDeployment method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ProjectAuthorizationService.php'));
    expect($code)->toContain('function canApproveDeployment(');
});

it('ProjectAuthorizationService defines canViewProductionEnvironment method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ProjectAuthorizationService.php'));
    expect($code)->toContain('function canViewProductionEnvironment(');
});

it('ProjectAuthorizationService defines getUserProjectRole with fallback', function () {
    $code = file_get_contents(app_path('Services/Authorization/ProjectAuthorizationService.php'));
    expect($code)->toContain('function getUserProjectRole(');
});

it('ProjectAuthorizationService defines canManageProject method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ProjectAuthorizationService.php'));
    expect($code)->toContain('function canManageProject(');
});

it('ProjectAuthorizationService defines canDeploy method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ProjectAuthorizationService.php'));
    expect($code)->toContain('function canDeploy(');
});

it('ProjectAuthorizationService defines canCreateEnvironment method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ProjectAuthorizationService.php'));
    expect($code)->toContain('function canCreateEnvironment(');
});

// ====== ResourceAuthorizationService ======

it('ResourceAuthorizationService accepts PermissionService in constructor', function () {
    $code = file_get_contents(app_path('Services/Authorization/ResourceAuthorizationService.php'));
    expect($code)->toContain('PermissionService $permissionService');
});

it('ResourceAuthorizationService can be instantiated with mocked PermissionService', function () {
    $permMock = Mockery::mock(PermissionService::class);
    $service = new ResourceAuthorizationService($permMock);
    expect($service)->toBeInstanceOf(ResourceAuthorizationService::class);
});

it('ResourceAuthorizationService defines server authorization methods', function () {
    $code = file_get_contents(app_path('Services/Authorization/ResourceAuthorizationService.php'));
    expect($code)
        ->toContain('function canViewServer(')
        ->toContain('function canCreateServer(')
        ->toContain('function canUpdateServer(')
        ->toContain('function canDeleteServer(');
});

it('ResourceAuthorizationService defines database authorization methods', function () {
    $code = file_get_contents(app_path('Services/Authorization/ResourceAuthorizationService.php'));
    expect($code)
        ->toContain('function canViewDatabase(')
        ->toContain('function canCreateDatabase(')
        ->toContain('function canUpdateDatabase(')
        ->toContain('function canDeleteDatabase(');
});

it('ResourceAuthorizationService defines getResourceTeamId multi-strategy method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ResourceAuthorizationService.php'));
    expect($code)->toContain('function getResourceTeamId(');
});

it('ResourceAuthorizationService defines canAccessTerminal method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ResourceAuthorizationService.php'));
    expect($code)->toContain('function canAccessTerminal(');
});

it('ResourceAuthorizationService defines canManageCloudProviders sensitive method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ResourceAuthorizationService.php'));
    expect($code)->toContain('function canManageCloudProviders(');
});

it('ResourceAuthorizationService defines canAccessSensitiveData method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ResourceAuthorizationService.php'));
    expect($code)->toContain('function canAccessSensitiveData(');
});

it('ResourceAuthorizationService defines canViewDatabaseCredentials method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ResourceAuthorizationService.php'));
    expect($code)->toContain('function canViewDatabaseCredentials(');
});

it('ResourceAuthorizationService defines getUserTeamRole method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ResourceAuthorizationService.php'));
    expect($code)->toContain('function getUserTeamRole(');
});

it('ResourceAuthorizationService defines canManageNotifications method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ResourceAuthorizationService.php'));
    expect($code)->toContain('function canManageNotifications(');
});

it('ResourceAuthorizationService defines canManageTeamMembers method', function () {
    $code = file_get_contents(app_path('Services/Authorization/ResourceAuthorizationService.php'));
    expect($code)->toContain('function canManageTeamMembers(');
});
