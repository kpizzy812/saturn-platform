<?php

/**
 * Admin Metrics routes
 *
 * System performance metrics, deployment statistics, resource usage, team performance, and cost analytics.
 */

use App\Http\Controllers\Admin\AdminMetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/metrics', [AdminMetricsController::class, 'index'])->name('admin.metrics.index');
