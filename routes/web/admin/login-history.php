<?php

/**
 * Admin Login History routes
 *
 * Security audit: login attempts across the platform.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/login-history', function (Request $request) {
    $query = \App\Models\LoginHistory::with('user')
        ->orderByDesc('logged_at');

    // Filter by user
    if ($request->filled('user_id')) {
        $query->where('user_id', $request->integer('user_id'));
    }

    // Filter by status
    if ($request->filled('status')) {
        $query->where('status', $request->input('status'));
    }

    // Filter by IP
    if ($request->filled('ip')) {
        $query->where('ip_address', 'like', '%'.$request->input('ip').'%');
    }

    // Filter by date range
    if ($request->filled('days')) {
        $query->where('logged_at', '>=', now()->subDays($request->integer('days')));
    }

    $entries = $query->paginate(50)->through(function ($entry) {
        return [
            'id' => $entry->id,
            'user_id' => $entry->user_id,
            'user_name' => $entry->user?->name,
            'user_email' => $entry->user?->email,
            'ip_address' => $entry->ip_address,
            'user_agent' => $entry->user_agent,
            'status' => $entry->status,
            'location' => $entry->location,
            'failure_reason' => $entry->failure_reason,
            'logged_at' => $entry->logged_at,
        ];
    });

    // Stats
    $totalToday = \App\Models\LoginHistory::where('logged_at', '>=', now()->startOfDay())->count();
    $failedToday = \App\Models\LoginHistory::where('logged_at', '>=', now()->startOfDay())
        ->where('status', 'failed')->count();
    $uniqueIpsToday = \App\Models\LoginHistory::where('logged_at', '>=', now()->startOfDay())
        ->distinct('ip_address')->count('ip_address');

    // Suspicious users (5+ failed from different IPs in 24h)
    $suspiciousUsers = \App\Models\LoginHistory::where('status', 'failed')
        ->where('logged_at', '>=', now()->subHours(24))
        ->selectRaw('user_id, count(distinct ip_address) as ip_count')
        ->groupBy('user_id')
        ->havingRaw('count(distinct ip_address) >= 3')
        ->pluck('user_id')
        ->toArray();

    return Inertia::render('Admin/LoginHistory/Index', [
        'entries' => $entries,
        'stats' => [
            'totalToday' => $totalToday,
            'failedToday' => $failedToday,
            'uniqueIpsToday' => $uniqueIpsToday,
            'suspiciousCount' => count($suspiciousUsers),
        ],
        'filters' => $request->only(['user_id', 'status', 'ip', 'days']),
    ]);
})->name('admin.login-history');
