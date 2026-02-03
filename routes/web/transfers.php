<?php

/**
 * Resource Transfer routes for Saturn Platform
 *
 * These routes handle database and resource transfers between environments.
 * All routes require authentication and email verification.
 */

use App\Http\Controllers\Inertia\TransferController;
use Illuminate\Support\Facades\Route;

// Transfers
Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
Route::get('/transfers/{uuid}', [TransferController::class, 'show'])->name('transfers.show');
Route::post('/transfers/{uuid}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');

// API endpoints for transfer UI
Route::get('/_internal/transfers/targets', [TransferController::class, 'targets'])->name('transfers.targets.api');
Route::get('/_internal/databases/{uuid}/structure', [TransferController::class, 'structure'])->name('transfers.structure.api');
