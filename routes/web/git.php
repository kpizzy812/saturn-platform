<?php

/**
 * Git Repository Analysis routes for Saturn Platform
 *
 * These routes handle repository analysis and auto-provisioning.
 * All routes require authentication.
 */

use App\Http\Controllers\Api\GitAnalyzerController;
use Illuminate\Support\Facades\Route;

// Git Repository Analysis & Auto-Provisioning
// These endpoints are used by the frontend MonorepoAnalyzer component
Route::post('/git/analyze', [GitAnalyzerController::class, 'analyze'])
    ->name('git.analyze');

Route::post('/git/provision', [GitAnalyzerController::class, 'provision'])
    ->name('git.provision');
