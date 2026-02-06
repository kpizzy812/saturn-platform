<?php

/**
 * Admin System Notifications routes
 *
 * System notifications are internal platform alerts (job failures, system errors, etc.)
 * They are stored in user_notifications with team_id=0 and type='info'
 */

use App\Models\Team;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Notification channels overview across all teams
Route::get('/notifications/overview', function () {
    $channelMap = [
        'discord' => \App\Models\DiscordNotificationSettings::class,
        'slack' => \App\Models\SlackNotificationSettings::class,
        'telegram' => \App\Models\TelegramNotificationSettings::class,
        'email' => \App\Models\EmailNotificationSettings::class,
        'pushover' => \App\Models\PushoverNotificationSettings::class,
        'webhook' => \App\Models\WebhookNotificationSettings::class,
    ];

    $teams = Team::with([
        'discordNotificationSettings',
        'slackNotificationSettings',
        'telegramNotificationSettings',
        'emailNotificationSettings',
        'pushoverNotificationSettings',
        'webhookNotificationSettings',
    ])->get();

    $channelPopularity = [];
    $configuredTeamIds = [];

    $teamsData = $teams->map(function (Team $team) use ($channelMap, &$channelPopularity, &$configuredTeamIds) {
        $channels = [];
        $enabledCount = 0;

        foreach ($channelMap as $name => $class) {
            $relationName = $name.'NotificationSettings';
            $settings = $team->{$relationName};

            $enabled = $settings ? $settings->isEnabled() : false;

            // Count event flags dynamically from fillable
            $eventsTotal = 0;
            $eventsEnabled = 0;

            if ($settings) {
                $suffix = "_{$name}_notifications";
                $eventFields = array_filter(
                    $settings->getFillable(),
                    fn ($f) => str_ends_with($f, $suffix)
                );
                $eventsTotal = count($eventFields);

                foreach ($eventFields as $field) {
                    if ($settings->{$field}) {
                        $eventsEnabled++;
                    }
                }
            }

            $channels[$name] = [
                'enabled' => $enabled,
                'events_enabled' => $eventsEnabled,
                'events_total' => $eventsTotal,
            ];

            if ($enabled) {
                $enabledCount++;
                $channelPopularity[$name] = ($channelPopularity[$name] ?? 0) + 1;
            }
        }

        if ($enabledCount > 0) {
            $configuredTeamIds[] = $team->id;
        }

        return [
            'id' => $team->id,
            'name' => $team->name,
            'channels' => $channels,
            'enabled_channels_count' => $enabledCount,
        ];
    });

    $totalTeams = $teams->count();
    $configuredTeams = count($configuredTeamIds);
    $mostPopularChannel = null;
    $mostPopularCount = 0;

    foreach ($channelPopularity as $ch => $count) {
        if ($count > $mostPopularCount) {
            $mostPopularChannel = $ch;
            $mostPopularCount = $count;
        }
    }

    return Inertia::render('Admin/Notifications/Overview', [
        'teams' => $teamsData,
        'stats' => [
            'total_teams' => $totalTeams,
            'configured_teams' => $configuredTeams,
            'unconfigured_teams' => $totalTeams - $configuredTeams,
            'most_popular_channel' => $mostPopularChannel,
            'most_popular_count' => $mostPopularCount,
            'channel_popularity' => $channelPopularity,
        ],
    ]);
})->name('admin.notifications.overview');

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
