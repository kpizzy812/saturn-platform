<?php

/**
 * Admin routes for Saturn Platform
 *
 * These routes handle the admin panel for managing users, servers, deployments, etc.
 * All routes require authentication and admin privileges.
 *
 * Route modules are organized by feature:
 * - dashboard.php      - Main admin dashboard with stats and health checks
 * - users.php          - User management (listing, impersonation, suspension, bulk ops)
 * - applications.php   - Application management (listing, restart, stop, redeploy)
 * - projects.php       - Project management (listing, viewing, deletion)
 * - databases.php      - Database management (all 8 types: PostgreSQL, MySQL, etc.)
 * - services.php       - Service management (listing, restart, stop, start)
 * - servers.php        - Server management (listing, validation, health, tags)
 * - deployments.php    - Deployment history and approvals
 * - teams.php          - Team management (listing, members, roles)
 * - settings.php       - Instance settings
 * - queues.php         - Queue monitoring (failed jobs, retry, flush)
 * - backups.php        - Backup management (scheduling, execution, restore)
 * - invitations.php    - Team invitations management
 * - logs.php           - System logs and audit logs
 * - health.php         - System health dashboard
 * - templates.php      - Application templates management
 */

use Illuminate\Support\Facades\Route;

// Admin routes group with prefix
Route::prefix('admin')->group(function () {
    // Dashboard - main admin page
    require __DIR__.'/admin/dashboard.php';

    // User management
    require __DIR__.'/admin/users.php';

    // Application management
    require __DIR__.'/admin/applications.php';

    // Project management
    require __DIR__.'/admin/projects.php';

    // Database management (all 8 types)
    require __DIR__.'/admin/databases.php';

    // Service management
    require __DIR__.'/admin/services.php';

    // Server management
    require __DIR__.'/admin/servers.php';

    // Deployment management
    require __DIR__.'/admin/deployments.php';

    // Team management
    require __DIR__.'/admin/teams.php';

    // Instance settings
    require __DIR__.'/admin/settings.php';

    // Queue monitoring
    require __DIR__.'/admin/queues.php';

    // Backup management
    require __DIR__.'/admin/backups.php';

    // Invitations management
    require __DIR__.'/admin/invitations.php';

    // System notifications
    require __DIR__.'/admin/notifications.php';

    // System logs and Audit logs
    require __DIR__.'/admin/logs.php';

    // System health dashboard
    require __DIR__.'/admin/health.php';

    // Application templates
    require __DIR__.'/admin/templates.php';

    // AI Usage statistics
    require __DIR__.'/admin/ai-usage.php';
});
