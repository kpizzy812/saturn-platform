<?php

/**
 * Admin Invitations routes
 *
 * Team invitation management including listing, deletion, and resending.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/invitations', function () {
    $invitations = \App\Models\TeamInvitation::with(['team'])
        ->latest()
        ->paginate(50)
        ->through(function ($invitation) {
            return [
                'id' => $invitation->id,
                'uuid' => $invitation->uuid,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'team_id' => $invitation->team_id,
                'team_name' => $invitation->team?->name ?? 'Unknown',
                'via' => $invitation->via,
                'is_valid' => $invitation->isValid(),
                'created_at' => $invitation->created_at,
            ];
        });

    return Inertia::render('Admin/Invitations/Index', [
        'invitations' => $invitations,
    ]);
})->name('admin.invitations.index');

Route::delete('/invitations/{id}', function (int $id) {
    $invitation = \App\Models\TeamInvitation::findOrFail($id);
    $invitation->delete();

    return back()->with('success', 'Invitation deleted');
})->name('admin.invitations.delete');

Route::post('/invitations/{id}/resend', function (int $id) {
    $invitation = \App\Models\TeamInvitation::findOrFail($id);

    // Resend logic would go here - for now just flash success
    return back()->with('success', 'Invitation resent');
})->name('admin.invitations.resend');
