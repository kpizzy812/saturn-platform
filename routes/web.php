<?php

use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file serves as the main entry point for all web routes.
| Routes are organized into logical groups and loaded from separate files.
|
| Route Files:
| - superadmin.php      - Super admin management routes
| - web/auth.php        - Authentication, OAuth, invitations
| - web/legacy.php      - Legacy route redirects for backwards compatibility
| - web/uploads.php     - File upload/download routes
| - web/servers.php     - Server management routes
| - web/projects.php    - Project management routes
| - web/applications.php - Application management routes
| - web/services.php    - Service management routes
| - web/databases.php   - Database management routes
| - web/settings.php    - User and team settings routes
| - web/admin.php       - Admin panel routes
| - web/approvals.php   - Deployment approval routes
| - web/git.php         - Git repository analysis routes
| - web/templates.php   - One-click template routes
| - web/deployments.php - Deployment management routes
| - web/observability.php - Metrics, logs, traces, alerts
| - web/volumes.php     - Persistent volume routes
| - web/storage-routes.php - S3 storage and backups
| - web/domains.php     - Domain and SSL management
| - web/scheduled-tasks.php - Cron jobs and scheduled tasks
| - web/activity.php    - Activity log routes
| - web/notifications.php - Notification management
| - web/integrations.php - Webhooks and integrations
| - web/sources.php     - Git sources (GitHub, GitLab, Bitbucket)
| - web/web-api.php     - Web API endpoints (session auth)
| - web/boarding.php    - Onboarding wizard
| - web/misc.php        - Dashboard, destinations, tags, etc.
|
*/

// SuperAdmin routes
require __DIR__.'/superadmin.php';

// Authentication routes (public and authenticated)
require __DIR__.'/web/auth.php';

// Legacy routes - redirects for backwards compatibility
require __DIR__.'/web/legacy.php';

// File upload/download routes
require __DIR__.'/web/uploads.php';

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
|
| All routes below require authentication and email verification.
|
*/
Route::middleware(['web', 'auth', 'verified'])->group(function () {
    // Core resource routes (already modularized)
    require __DIR__.'/web/servers.php';
    require __DIR__.'/web/projects.php';
    require __DIR__.'/web/applications.php';
    require __DIR__.'/web/services.php';
    require __DIR__.'/web/databases.php';
    require __DIR__.'/web/settings.php';
    require __DIR__.'/web/admin.php';
    require __DIR__.'/web/approvals.php';
    require __DIR__.'/web/git.php';

    // Templates
    require __DIR__.'/web/templates.php';

    // Deployments
    require __DIR__.'/web/deployments.php';

    // Observability (metrics, logs, traces, alerts)
    require __DIR__.'/web/observability.php';

    // Volumes
    require __DIR__.'/web/volumes.php';

    // Storage (S3, backups, snapshots)
    require __DIR__.'/web/storage-routes.php';

    // Domains and SSL
    require __DIR__.'/web/domains.php';

    // Scheduled tasks and cron jobs
    require __DIR__.'/web/scheduled-tasks.php';

    // Activity logs
    require __DIR__.'/web/activity.php';

    // Notifications
    require __DIR__.'/web/notifications.php';

    // Integrations (webhooks)
    require __DIR__.'/web/integrations.php';

    // Git sources (GitHub, GitLab, Bitbucket)
    require __DIR__.'/web/sources.php';

    // Web API endpoints (session auth)
    require __DIR__.'/web/web-api.php';

    // Onboarding wizard
    require __DIR__.'/web/boarding.php';

    // Miscellaneous routes (dashboard, destinations, tags, demo, etc.)
    require __DIR__.'/web/misc.php';
});

/*
|--------------------------------------------------------------------------
| Catch-all Route
|--------------------------------------------------------------------------
|
| Redirect any unmatched routes to the appropriate page.
|
*/
Route::any('/{any}', function () {
    if (auth()->user()) {
        return redirect(RouteServiceProvider::HOME);
    }

    return redirect()->route('login');
})->where('any', '.*');
