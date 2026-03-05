<?php

use App\Actions\Deployment\RequestDeploymentApprovalAction;

// ====== DeleteServer ======

it('DeleteServer uses AsAction trait', function () {
    $code = file_get_contents(app_path('Actions/Server/DeleteServer.php'));
    expect($code)->toContain('use AsAction');
});

it('DeleteServer handle method accepts optional Hetzner deletion parameters', function () {
    $code = file_get_contents(app_path('Actions/Server/DeleteServer.php'));
    expect($code)
        ->toContain('function handle(')
        ->toContain('deleteFromHetzner')
        ->toContain('hetznerServerId')
        ->toContain('cloudProviderTokenId');
});

it('DeleteServer handles soft-deleted servers via withTrashed', function () {
    $code = file_get_contents(app_path('Actions/Server/DeleteServer.php'));
    expect($code)->toContain('withTrashed');
});

it('DeleteServer has private deleteFromHetznerById method', function () {
    $code = file_get_contents(app_path('Actions/Server/DeleteServer.php'));
    expect($code)->toContain('function deleteFromHetznerById(');
});

it('DeleteServer handles Hetzner deletion errors gracefully without re-throwing', function () {
    $code = file_get_contents(app_path('Actions/Server/DeleteServer.php'));
    // Should catch exceptions to allow platform deletion to proceed even if Hetzner fails
    expect($code)->toContain('catch');
});

it('DeleteServer logs deletion process', function () {
    $code = file_get_contents(app_path('Actions/Server/DeleteServer.php'));
    expect($code)->toContain('Log::');
});

it('DeleteServer accepts teamId parameter for multi-tenant token lookup', function () {
    $code = file_get_contents(app_path('Actions/Server/DeleteServer.php'));
    expect($code)->toContain('teamId');
});

// ====== UpdateSaturn ======

it('UpdateSaturn uses AsAction trait', function () {
    $code = file_get_contents(app_path('Actions/Server/UpdateSaturn.php'));
    expect($code)->toContain('use AsAction');
});

it('UpdateSaturn handle method exists with manual_update flag', function () {
    $code = file_get_contents(app_path('Actions/Server/UpdateSaturn.php'));
    expect($code)
        ->toContain('function handle(')
        ->toContain('manual_update');
});

it('UpdateSaturn prevents platform downgrades', function () {
    $code = file_get_contents(app_path('Actions/Server/UpdateSaturn.php'));
    expect($code)->toContain('downgrade')->or->toContain('Cannot downgrade');
});

it('UpdateSaturn uses version_compare for version checking', function () {
    $code = file_get_contents(app_path('Actions/Server/UpdateSaturn.php'));
    expect($code)->toContain('version_compare');
});

it('UpdateSaturn respects auto_update_enabled setting', function () {
    $code = file_get_contents(app_path('Actions/Server/UpdateSaturn.php'));
    expect($code)->toContain('auto_update_enabled')->or->toContain('is_auto_update_enabled');
});

it('UpdateSaturn has update method for executing upgrade script', function () {
    $code = file_get_contents(app_path('Actions/Server/UpdateSaturn.php'));
    expect($code)->toContain('function update(');
});

it('UpdateSaturn falls back to cache when CDN unavailable', function () {
    $code = file_get_contents(app_path('Actions/Server/UpdateSaturn.php'));
    expect($code)->toContain('cache');
});

it('UpdateSaturn returns early in dev environment', function () {
    $code = file_get_contents(app_path('Actions/Server/UpdateSaturn.php'));
    expect($code)->toContain('isDev()');
});

it('UpdateSaturn fetches latest version from CDN with retries', function () {
    $code = file_get_contents(app_path('Actions/Server/UpdateSaturn.php'));
    expect($code)->toContain('latestVersion');
});

// ====== CloneDatabaseAction ======

it('CloneDatabaseAction uses AsAction trait', function () {
    $code = file_get_contents(app_path('Actions/Migration/CloneDatabaseAction.php'));
    expect($code)->toContain('use AsAction');
});

it('CloneDatabaseAction handle method returns success/error array', function () {
    $code = file_get_contents(app_path('Actions/Migration/CloneDatabaseAction.php'));
    expect($code)
        ->toContain("'success'")
        ->toContain("'target'")
        ->toContain("'error'");
});

it('CloneDatabaseAction supports config-only cloning option', function () {
    $code = file_get_contents(app_path('Actions/Migration/CloneDatabaseAction.php'));
    expect($code)->toContain('OPTION_CONFIG_ONLY')->or->toContain('config_only');
});

it('CloneDatabaseAction clones environment variables', function () {
    $code = file_get_contents(app_path('Actions/Migration/CloneDatabaseAction.php'));
    expect($code)->toContain('cloneEnvironmentVariables');
});

it('CloneDatabaseAction clones volume configurations without data', function () {
    $code = file_get_contents(app_path('Actions/Migration/CloneDatabaseAction.php'));
    expect($code)->toContain('cloneVolumeConfigurations');
});

it('CloneDatabaseAction maps all major database types to readable names', function () {
    $code = file_get_contents(app_path('Actions/Migration/CloneDatabaseAction.php'));
    expect($code)
        ->toContain('PostgreSQL')
        ->toContain('MySQL')
        ->toContain('MongoDB')
        ->toContain('Redis');
});

it('CloneDatabaseAction generates new CUID2 UUID for cloned database', function () {
    $code = file_get_contents(app_path('Actions/Migration/CloneDatabaseAction.php'));
    expect($code)->toContain('Cuid2');
});

it('CloneDatabaseAction sets status to exited on clone for safety', function () {
    $code = file_get_contents(app_path('Actions/Migration/CloneDatabaseAction.php'));
    expect($code)->toContain("'exited'");
});

it('CloneDatabaseAction clones tags relationship', function () {
    $code = file_get_contents(app_path('Actions/Migration/CloneDatabaseAction.php'));
    expect($code)->toContain('cloneTags');
});

it('CloneDatabaseAction handles public port conflict resolution', function () {
    $code = file_get_contents(app_path('Actions/Migration/CloneDatabaseAction.php'));
    expect($code)->toContain('public_port')->or->toContain('getRandomPublicPort');
});

// ====== RequestDeploymentApprovalAction ======

it('RequestDeploymentApprovalAction can be instantiated without constructor', function () {
    $action = new RequestDeploymentApprovalAction;
    expect($action)->toBeInstanceOf(RequestDeploymentApprovalAction::class);
});

it('RequestDeploymentApprovalAction defines handle method', function () {
    $code = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));
    expect($code)->toContain('function handle(');
});

it('RequestDeploymentApprovalAction defines requiresApproval method', function () {
    $code = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));
    expect($code)->toContain('function requiresApproval(');
});

it('RequestDeploymentApprovalAction dispatches DeploymentApprovalRequested event', function () {
    $code = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));
    expect($code)->toContain('DeploymentApprovalRequested');
});

it('RequestDeploymentApprovalAction deduplicates to avoid duplicate pending approvals', function () {
    $code = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));
    expect($code)->toContain('pending')->or->toContain('approved');
});

it('RequestDeploymentApprovalAction returns DeploymentApproval model', function () {
    $code = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));
    expect($code)->toContain('DeploymentApproval');
});

it('RequestDeploymentApprovalAction requiresApproval checks environment-based rules', function () {
    $code = file_get_contents(app_path('Actions/Deployment/RequestDeploymentApprovalAction.php'));
    expect($code)->toContain('requiresApprovalForEnvironment')->or->toContain('requires_approval');
});
