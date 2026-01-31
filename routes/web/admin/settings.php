<?php

/**
 * Admin Settings routes
 *
 * Instance settings management.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/settings', function () {
    // Fetch instance settings (admin view)
    $settings = \App\Models\InstanceSettings::get();

    return Inertia::render('Admin/Settings/Index', [
        'settings' => $settings,
    ]);
})->name('admin.settings.index');
