<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Notifications Routes
|--------------------------------------------------------------------------
|
| Routes for viewing and managing user notifications.
|
*/

Route::get('/notifications', function () {
    $team = auth()->user()->currentTeam();
    // Exclude system notifications (type='info') - those are shown in admin panel only
    $notifications = \App\Models\UserNotification::where('team_id', $team->id)
        ->where('type', '!=', 'info')
        ->orderBy('created_at', 'desc')
        ->take(50)
        ->get()
        ->map(fn ($n) => $n->toFrontendArray());

    return Inertia::render('Notifications/Index', [
        'notifications' => $notifications,
    ]);
})->name('notifications.index');

Route::post('/notifications/{id}/read', function (string $id) {
    $team = auth()->user()->currentTeam();
    $notification = \App\Models\UserNotification::where('team_id', $team->id)
        ->where('id', $id)
        ->firstOrFail();

    $notification->markAsRead();

    return back();
})->name('notifications.read');

Route::post('/notifications/read-all', function () {
    $team = auth()->user()->currentTeam();
    \App\Models\UserNotification::where('team_id', $team->id)
        ->where('is_read', false)
        ->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

    return back();
})->name('notifications.read-all');

Route::delete('/notifications/{id}', function (string $id) {
    $team = auth()->user()->currentTeam();
    $notification = \App\Models\UserNotification::where('team_id', $team->id)
        ->where('id', $id)
        ->firstOrFail();

    $notification->delete();

    return back();
})->name('notifications.destroy');

// NOTE: preferences route must be before {uuid} to avoid conflict
Route::get('/notifications/preferences', function () {
    $user = auth()->user();
    $preferences = \App\Models\UserNotificationPreference::getOrCreateForUser($user->id);

    return Inertia::render('Notifications/Preferences', [
        'preferences' => $preferences->toFrontendFormat(),
    ]);
})->name('notifications.preferences');

Route::get('/notifications/{uuid}', function (string $uuid) {
    $team = auth()->user()->currentTeam();
    $notification = \App\Models\UserNotification::where('team_id', $team->id)
        ->where('id', $uuid)
        ->first();

    return Inertia::render('Notifications/NotificationDetail', [
        'notification' => $notification?->toFrontendArray(),
    ]);
})->name('notifications.detail');
