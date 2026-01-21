<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| SuperAdmin Routes
|--------------------------------------------------------------------------
|
| Routes for Super Admin panel - accessible only by root team admins/owners.
| All routes require authentication and super admin privileges.
|
*/

Route::middleware(['auth', 'verified', 'is.superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/', fn () => Inertia::render('Admin/Index'))->name('dashboard');
    Route::get('/users', fn () => Inertia::render('Admin/Users/Index'))->name('users');
    Route::get('/projects', fn () => Inertia::render('Admin/Projects/Index'))->name('projects');
    Route::get('/servers', fn () => Inertia::render('Admin/Servers/Index'))->name('servers');
    Route::get('/audit', fn () => Inertia::render('Admin/Logs/Index'))->name('audit');
    Route::get('/metrics', fn () => Inertia::render('Admin/Metrics/Index'))->name('metrics');
});
