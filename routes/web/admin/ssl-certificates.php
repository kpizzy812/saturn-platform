<?php

/**
 * Admin SSL Certificates overview routes
 *
 * All SSL certificates, expiration tracking, status overview.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/ssl-certificates', function (Request $request) {
    $query = \App\Models\SslCertificate::with('server')
        ->orderByDesc('created_at');

    // Filter by expiration status
    if ($request->input('expiry') === 'expiring') {
        $query->whereNotNull('valid_until')
            ->where('valid_until', '<=', now()->addDays(30))
            ->where('valid_until', '>', now());
    } elseif ($request->input('expiry') === 'expired') {
        $query->whereNotNull('valid_until')
            ->where('valid_until', '<=', now());
    }

    // Filter by server
    if ($request->filled('server_id')) {
        $query->where('server_id', $request->integer('server_id'));
    }

    $certificates = $query->paginate(50)->through(function ($cert) {
        $daysUntilExpiry = $cert->valid_until ? now()->diffInDays($cert->valid_until, false) : null;

        return [
            'id' => $cert->id,
            'common_name' => $cert->common_name,
            'subject_alternative_names' => $cert->subject_alternative_names,
            'valid_until' => $cert->valid_until,
            'days_until_expiry' => $daysUntilExpiry !== null ? (int) $daysUntilExpiry : null,
            'is_ca_certificate' => $cert->is_ca_certificate,
            'resource_type' => $cert->resource_type ? class_basename($cert->resource_type) : null,
            'resource_id' => $cert->resource_id,
            'server_id' => $cert->server_id,
            'server_name' => $cert->server?->name ?? 'Unknown',
            'created_at' => $cert->created_at,
        ];
    });

    // Stats
    $totalCerts = \App\Models\SslCertificate::count();
    $expiringSoon = \App\Models\SslCertificate::whereNotNull('valid_until')
        ->where('valid_until', '<=', now()->addDays(30))
        ->where('valid_until', '>', now())
        ->count();
    $expired = \App\Models\SslCertificate::whereNotNull('valid_until')
        ->where('valid_until', '<=', now())
        ->count();

    // Server list for filter
    $servers = \App\Models\Server::select('id', 'name')->orderBy('name')->get();

    return Inertia::render('Admin/SslCertificates/Index', [
        'certificates' => $certificates,
        'stats' => [
            'total' => $totalCerts,
            'expiringSoon' => $expiringSoon,
            'expired' => $expired,
            'valid' => $totalCerts - $expiringSoon - $expired,
        ],
        'servers' => $servers,
        'filters' => $request->only(['expiry', 'server_id']),
    ]);
})->name('admin.ssl-certificates');
