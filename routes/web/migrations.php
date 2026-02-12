<?php

/**
 * Migration routes for Saturn Platform
 *
 * These routes handle environment migration detail pages.
 * All routes require authentication and email verification.
 */

use App\Models\EnvironmentMigration;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/migrations/{uuid}', function (string $uuid) {
    $teamId = currentTeam()->id;

    $migration = EnvironmentMigration::where('uuid', $uuid)
        ->where('team_id', $teamId)
        ->with([
            'source',
            'target',
            'sourceEnvironment.project',
            'targetEnvironment',
            'targetServer',
            'requestedBy',
            'approvedBy',
            'history',
        ])
        ->firstOrFail();

    return Inertia::render('Migrations/Show', [
        'migration' => $migration,
    ]);
})->name('migrations.show');
