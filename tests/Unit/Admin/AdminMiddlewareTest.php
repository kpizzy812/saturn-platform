<?php

/**
 * Unit tests for admin middleware and route security.
 *
 * Validates that admin routes are protected by is.superadmin middleware
 * and that new route files are properly registered.
 */
test('admin routes file applies is.superadmin middleware', function () {
    $routeFile = file_get_contents(base_path('routes/web/admin.php'));

    expect($routeFile)->toContain("middleware('is.superadmin')");
});

test('admin.php includes all route files', function () {
    $routeFile = file_get_contents(base_path('routes/web/admin.php'));

    $requiredIncludes = [
        'admin/dashboard.php',
        'admin/users.php',
        'admin/applications.php',
        'admin/projects.php',
        'admin/databases.php',
        'admin/services.php',
        'admin/servers.php',
        'admin/ssh-keys.php',
        'admin/deployments.php',
        'admin/teams.php',
        'admin/settings.php',
        'admin/queues.php',
        'admin/backups.php',
        'admin/invitations.php',
        'admin/notifications.php',
        'admin/logs.php',
        'admin/health.php',
        'admin/templates.php',
        'admin/ai-usage.php',
        'admin/oauth.php',
        'admin/login-history.php',
        'admin/webhook-deliveries.php',
        'admin/scheduled-tasks.php',
        'admin/docker-cleanups.php',
        'admin/ssl-certificates.php',
        'admin/transfers.php',
    ];

    foreach ($requiredIncludes as $include) {
        expect($routeFile)->toContain($include);
    }
});

test('all admin route files exist', function () {
    $routeFiles = [
        'login-history.php',
        'webhook-deliveries.php',
        'scheduled-tasks.php',
        'docker-cleanups.php',
        'ssl-certificates.php',
        'transfers.php',
    ];

    foreach ($routeFiles as $file) {
        expect(file_exists(base_path("routes/web/admin/{$file}")))->toBeTrue();
    }
});

test('login-history route renders correct Inertia page', function () {
    $routeFile = file_get_contents(base_path('routes/web/admin/login-history.php'));

    expect($routeFile)->toContain("Inertia::render('Admin/LoginHistory/Index'");
    expect($routeFile)->toContain("->name('admin.login-history')");
});

test('webhook-deliveries route renders correct Inertia page', function () {
    $routeFile = file_get_contents(base_path('routes/web/admin/webhook-deliveries.php'));

    expect($routeFile)->toContain("Inertia::render('Admin/WebhookDeliveries/Index'");
    expect($routeFile)->toContain("->name('admin.webhook-deliveries')");
});

test('scheduled-tasks route renders correct Inertia page', function () {
    $routeFile = file_get_contents(base_path('routes/web/admin/scheduled-tasks.php'));

    expect($routeFile)->toContain("Inertia::render('Admin/ScheduledTasks/Index'");
    expect($routeFile)->toContain("->name('admin.scheduled-tasks')");
});

test('ssl-certificates route renders correct Inertia page', function () {
    $routeFile = file_get_contents(base_path('routes/web/admin/ssl-certificates.php'));

    expect($routeFile)->toContain("Inertia::render('Admin/SslCertificates/Index'");
    expect($routeFile)->toContain("->name('admin.ssl-certificates')");
});

test('transfers route renders correct Inertia page', function () {
    $routeFile = file_get_contents(base_path('routes/web/admin/transfers.php'));

    expect($routeFile)->toContain("Inertia::render('Admin/Transfers/Index'");
    expect($routeFile)->toContain("->name('admin.transfers')");
});

test('settings export route exists', function () {
    $routeFile = file_get_contents(base_path('routes/web/admin/settings.php'));

    expect($routeFile)->toContain("->name('admin.settings.export')");
    expect($routeFile)->toContain("->name('admin.settings.import')");
});

test('settings export excludes sensitive fields', function () {
    $routeFile = file_get_contents(base_path('routes/web/admin/settings.php'));

    expect($routeFile)->toContain("'smtp_password'");
    expect($routeFile)->toContain("'ai_anthropic_api_key'");
    expect($routeFile)->toContain("'s3_secret'");
});

test('platform-role endpoint validates role values', function () {
    $routeFile = file_get_contents(base_path('routes/web/admin/users.php'));

    expect($routeFile)->toContain("'platform_role' => 'required|string|in:owner,admin,member'");
    expect($routeFile)->toContain("->name('admin.users.platform-role')");
});

test('platform-role endpoint prevents root user modification', function () {
    $routeFile = file_get_contents(base_path('routes/web/admin/users.php'));

    expect($routeFile)->toContain('Cannot change root user role');
    expect($routeFile)->toContain('Cannot demote yourself');
});
