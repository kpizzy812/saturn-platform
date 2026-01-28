<?php

/**
 * Deployment Approvals routes for Saturn Platform
 *
 * These routes handle viewing and managing pending deployment approvals.
 * All routes require authentication and email verification.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Approvals list page
Route::get('/approvals', function () {
    return Inertia::render('Approvals/Index');
})->name('approvals.index');
