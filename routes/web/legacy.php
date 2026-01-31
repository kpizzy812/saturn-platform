<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Legacy Routes
|--------------------------------------------------------------------------
|
| Redirect routes from old Livewire-based frontend to new Inertia routes.
| These ensure backwards compatibility for bookmarked URLs.
|
*/

Route::middleware(['auth', 'verified'])->group(function () {
    // Root redirect to dashboard
    Route::get('/', fn () => redirect()->route('dashboard'));

    // Legacy onboarding redirect
    Route::get('/onboarding', fn () => redirect()->route('boarding.index'))->name('onboarding');

    // Legacy subscription redirects
    Route::get('/subscription', fn () => redirect()->route('settings.billing'))->name('subscription.show');
    Route::get('/subscription/new', fn () => redirect()->route('settings.billing'))->name('subscription.legacy');

    // Legacy settings redirects
    Route::get('/settings', fn () => redirect()->route('settings.index'))->name('settings.legacy');
    Route::get('/settings/advanced', fn () => redirect()->route('settings.advanced'))->name('settings.advanced.legacy');
    Route::get('/settings/updates', fn () => redirect()->route('settings.updates'))->name('settings.updates.legacy');
    Route::get('/settings/backup', fn () => redirect()->route('settings.backup'))->name('settings.backup.legacy');
    Route::get('/settings/email', fn () => redirect()->route('settings.email'))->name('settings.email.legacy');
    Route::get('/settings/oauth', fn () => redirect()->route('settings.oauth'))->name('settings.oauth.legacy');

    // Legacy team redirects
    Route::get('/team', fn () => redirect()->route('settings.team'))->name('team.index');
    Route::get('/team/members', fn () => redirect()->route('settings.team.members'))->name('team.member.index');

    // Legacy project redirects
    Route::get('/projects', fn () => redirect()->route('projects.index'))->name('project.index');
    Route::prefix('project/{project_uuid}')->group(function () {
        Route::get('/', fn (string $project_uuid) => redirect()->route('projects.show', $project_uuid))->name('project.show');
        Route::get('/edit', fn (string $project_uuid) => redirect()->route('projects.edit', $project_uuid))->name('project.edit');
    });

    // Legacy server redirects
    Route::get('/servers', fn () => redirect()->route('servers.index'))->name('server.index');
    Route::prefix('server/{server_uuid}')->group(function () {
        Route::get('/', fn (string $server_uuid) => redirect()->route('servers.show', $server_uuid))->name('server.show');
        Route::get('/proxy', fn (string $server_uuid) => redirect()->route('servers.proxy.index', $server_uuid))->name('server.proxy');
        Route::get('/terminal', fn (string $server_uuid) => redirect()->route('servers.terminal', $server_uuid))->name('server.command');
    });

    // Legacy security redirects
    Route::get('/security/private-key', fn () => redirect()->route('settings.ssh-keys'))->name('security.private-key.index');
    Route::get('/security/api-tokens', fn () => redirect()->route('settings.api-tokens'))->name('security.api-tokens');
});
