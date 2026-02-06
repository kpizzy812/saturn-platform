<?php

/**
 * Admin Resource Transfer History routes
 *
 * Audit log of resource transfers between teams.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/transfers', function (Request $request) {
    $query = \App\Models\TeamResourceTransfer::with(['fromTeam', 'toTeam', 'fromUser', 'toUser', 'initiator'])
        ->orderByDesc('created_at');

    // Filter by status
    if ($request->filled('status')) {
        $query->where('status', $request->input('status'));
    }

    // Filter by transfer type
    if ($request->filled('type')) {
        $query->where('transfer_type', $request->input('type'));
    }

    $transfers = $query->paginate(50)->through(function ($transfer) {
        return [
            'id' => $transfer->id,
            'transfer_type' => $transfer->transfer_type,
            'transfer_type_label' => $transfer->transferTypeLabel ?? $transfer->transfer_type,
            'status' => $transfer->status,
            'status_label' => $transfer->statusLabel ?? $transfer->status,
            'transferable_type' => $transfer->transferable_type ? class_basename($transfer->transferable_type) : null,
            'transferable_id' => $transfer->transferable_id,
            'from_team' => $transfer->fromTeam ? [
                'id' => $transfer->fromTeam->id,
                'name' => $transfer->fromTeam->name,
            ] : null,
            'to_team' => $transfer->toTeam ? [
                'id' => $transfer->toTeam->id,
                'name' => $transfer->toTeam->name,
            ] : null,
            'initiated_by_name' => $transfer->initiator?->name ?? 'System',
            'notes' => $transfer->notes,
            'error_message' => $transfer->error_message,
            'created_at' => $transfer->created_at,
            'updated_at' => $transfer->updated_at,
        ];
    });

    // Stats
    $totalTransfers = \App\Models\TeamResourceTransfer::count();
    $completedTransfers = \App\Models\TeamResourceTransfer::where('status', 'completed')->count();
    $failedTransfers = \App\Models\TeamResourceTransfer::where('status', 'failed')->count();
    $recentTransfers = \App\Models\TeamResourceTransfer::where('created_at', '>=', now()->subDays(30))->count();

    return Inertia::render('Admin/Transfers/Index', [
        'transfers' => $transfers,
        'stats' => [
            'total' => $totalTransfers,
            'completed' => $completedTransfers,
            'failed' => $failedTransfers,
            'last30d' => $recentTransfers,
        ],
        'filters' => $request->only(['status', 'type']),
    ]);
})->name('admin.transfers');
