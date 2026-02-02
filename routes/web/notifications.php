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
    $userId = auth()->id();
    // Exclude system notifications (type='info') - those are shown in admin panel only
    // Show notifications that are either team-wide (user_id IS NULL) or targeted to current user
    $notifications = \App\Models\UserNotification::where('team_id', $team->id)
        ->where('type', '!=', 'info')
        ->where(function ($query) use ($userId) {
            $query->whereNull('user_id')
                ->orWhere('user_id', $userId);
        })
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
    $userId = auth()->id();
    $notification = \App\Models\UserNotification::where('team_id', $team->id)
        ->where('id', $id)
        ->where(function ($query) use ($userId) {
            $query->whereNull('user_id')
                ->orWhere('user_id', $userId);
        })
        ->firstOrFail();

    $notification->markAsRead();

    return back();
})->name('notifications.read');

Route::post('/notifications/read-all', function () {
    $team = auth()->user()->currentTeam();
    $userId = auth()->id();
    \App\Models\UserNotification::where('team_id', $team->id)
        ->where('is_read', false)
        ->where(function ($query) use ($userId) {
            $query->whereNull('user_id')
                ->orWhere('user_id', $userId);
        })
        ->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

    return back();
})->name('notifications.read-all');

Route::delete('/notifications/{id}', function (string $id) {
    $team = auth()->user()->currentTeam();
    $userId = auth()->id();
    $notification = \App\Models\UserNotification::where('team_id', $team->id)
        ->where('id', $id)
        ->where(function ($query) use ($userId) {
            $query->whereNull('user_id')
                ->orWhere('user_id', $userId);
        })
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
