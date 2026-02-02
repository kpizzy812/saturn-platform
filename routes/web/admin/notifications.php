<?php

/**
 * Admin System Notifications routes
 *
 * System notifications are internal platform alerts (job failures, system errors, etc.)
 * They are stored in user_notifications with team_id=0 and type='info'
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// List system notifications
Route::get('/notifications', function () {
    // System notifications are stored for Team 0 with type 'info'
    $notifications = \App\Models\UserNotification::where('team_id', 0)
        ->orderBy('created_at', 'desc')
        ->take(100)
        ->get()
        ->map(fn ($n) => $n->toFrontendArray());

    $unreadCount = \App\Models\UserNotification::where('team_id', 0)
        ->where('is_read', false)
        ->count();

    return Inertia::render('Admin/Notifications/Index', [
        'notifications' => $notifications,
        'unreadCount' => $unreadCount,
    ]);
})->name('admin.notifications.index');

// Show notification details
Route::get('/notifications/{id}', function (string $id) {
    $notification = \App\Models\UserNotification::where('team_id', 0)
        ->where('id', $id)
        ->first();

    if (! $notification) {
        return Inertia::render('Admin/Notifications/Show', [
            'notification' => null,
        ]);
    }

    return Inertia::render('Admin/Notifications/Show', [
        'notification' => array_merge($notification->toFrontendArray(), [
            'metadata' => $notification->metadata,
        ]),
    ]);
})->name('admin.notifications.show');

// Mark notification as read
Route::post('/notifications/{id}/read', function (string $id) {
    $notification = \App\Models\UserNotification::where('team_id', 0)
        ->where('id', $id)
        ->firstOrFail();

    $notification->markAsRead();

    return back();
})->name('admin.notifications.read');

// Mark notification as unread
Route::post('/notifications/{id}/unread', function (string $id) {
    $notification = \App\Models\UserNotification::where('team_id', 0)
        ->where('id', $id)
        ->firstOrFail();

    $notification->markAsUnread();

    return back();
})->name('admin.notifications.unread');

// Mark all as read
Route::post('/notifications/read-all', function () {
    \App\Models\UserNotification::where('team_id', 0)
        ->where('is_read', false)
        ->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

    return back();
})->name('admin.notifications.read-all');

// Delete notification
Route::delete('/notifications/{id}', function (string $id) {
    $notification = \App\Models\UserNotification::where('team_id', 0)
        ->where('id', $id)
        ->firstOrFail();

    $notification->delete();

    return back();
})->name('admin.notifications.destroy');

// Clear all notifications
Route::delete('/notifications', function () {
    \App\Models\UserNotification::where('team_id', 0)->delete();

    return back()->with('success', 'All system notifications cleared.');
})->name('admin.notifications.clear');
