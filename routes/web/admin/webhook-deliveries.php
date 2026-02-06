<?php

/**
 * Admin Webhook Delivery Logs routes
 *
 * Webhook delivery history and debugging across all teams.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/webhook-deliveries', function (Request $request) {
    $query = \App\Models\WebhookDelivery::with('webhook.team')
        ->orderByDesc('created_at');

    // Filter by status
    if ($request->filled('status')) {
        $query->where('status', $request->input('status'));
    }

    // Filter by event type
    if ($request->filled('event')) {
        $query->where('event', $request->input('event'));
    }

    // Filter by team (via webhook relationship)
    if ($request->filled('team_id')) {
        $query->whereHas('webhook', function ($q) use ($request) {
            $q->where('team_id', $request->integer('team_id'));
        });
    }

    $deliveries = $query->paginate(50)->through(function ($delivery) {
        return [
            'id' => $delivery->id,
            'uuid' => $delivery->uuid,
            'event' => $delivery->event,
            'status' => $delivery->status,
            'status_code' => $delivery->status_code,
            'response_time_ms' => $delivery->response_time_ms,
            'attempts' => $delivery->attempts,
            'webhook_url' => $delivery->webhook?->url ?? null,
            'team_name' => $delivery->webhook?->team?->name ?? null,
            'team_id' => $delivery->webhook?->team_id,
            'created_at' => $delivery->created_at,
        ];
    });

    // Stats
    $last24h = now()->subHours(24);
    $totalLast24h = \App\Models\WebhookDelivery::where('created_at', '>=', $last24h)->count();
    $failedLast24h = \App\Models\WebhookDelivery::where('created_at', '>=', $last24h)
        ->where('status', 'failed')->count();
    $avgResponseTime = \App\Models\WebhookDelivery::where('created_at', '>=', $last24h)
        ->whereNotNull('response_time_ms')
        ->avg('response_time_ms');

    // Unique event types for filter dropdown
    $eventTypes = \App\Models\WebhookDelivery::distinct('event')
        ->pluck('event')
        ->filter()
        ->values();

    return Inertia::render('Admin/WebhookDeliveries/Index', [
        'deliveries' => $deliveries,
        'stats' => [
            'totalLast24h' => $totalLast24h,
            'failedLast24h' => $failedLast24h,
            'avgResponseTime' => round($avgResponseTime ?? 0),
            'successRate' => $totalLast24h > 0 ? round((($totalLast24h - $failedLast24h) / $totalLast24h) * 100) : 100,
        ],
        'eventTypes' => $eventTypes,
        'filters' => $request->only(['status', 'event', 'team_id']),
    ]);
})->name('admin.webhook-deliveries');
