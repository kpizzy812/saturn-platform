<?php

/**
 * Admin Cloud Providers routes
 *
 * Manage cloud provider API tokens (Hetzner, DigitalOcean) for server provisioning.
 * All routes require superadmin privileges (applied by parent group in admin.php).
 */

use App\Http\Controllers\Inertia\AdminCloudProvidersController;
use App\Http\Controllers\Web\WebAutoProvisioningController;
use Illuminate\Support\Facades\Route;

// Cloud Providers management
Route::get('/cloud-providers', [AdminCloudProvidersController::class, 'index'])
    ->name('admin.cloud-providers.index');

Route::post('/cloud-providers', [AdminCloudProvidersController::class, 'store'])
    ->name('admin.cloud-providers.store');

Route::delete('/cloud-providers/{uuid}', [AdminCloudProvidersController::class, 'destroy'])
    ->name('admin.cloud-providers.destroy');

Route::post('/cloud-providers/{uuid}/validate', [AdminCloudProvidersController::class, 'checkToken'])
    ->name('admin.cloud-providers.validate');

// Auto-Provisioning settings (global, handled by WebAutoProvisioningController)
Route::post('/auto-provisioning', [WebAutoProvisioningController::class, 'update'])
    ->name('admin.auto-provisioning.update');
