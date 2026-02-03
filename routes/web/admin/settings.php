<?php

/**
 * Admin Settings routes
 *
 * Instance settings management.
 */

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/settings', function () {
    // Fetch instance settings (admin view)
    $settings = \App\Models\InstanceSettings::get();

    return Inertia::render('Admin/Settings/Index', [
        'settings' => $settings,
    ]);
})->name('admin.settings.index');

Route::post('/settings', function (\Illuminate\Http\Request $request) {
    $settings = \App\Models\InstanceSettings::get();

    $validated = $request->validate([
        'settings.instance_name' => 'nullable|string|max:255',
        'settings.fqdn' => 'nullable|string|max:255',
        'settings.allowed_ip_ranges' => 'nullable|string',
        'settings.is_auto_update_enabled' => 'nullable|boolean',
        'settings.auto_update_frequency' => 'nullable|string',
        'settings.is_ai_code_review_enabled' => 'nullable|boolean',
        'settings.is_ai_error_analysis_enabled' => 'nullable|boolean',
    ]);

    $data = $validated['settings'] ?? [];

    // Only update allowed fields
    $settings->update([
        'instance_name' => $data['instance_name'] ?? $settings->instance_name,
        'fqdn' => $data['fqdn'] ?? $settings->fqdn,
        'allowed_ip_ranges' => isset($data['allowed_ip_ranges'])
            ? array_filter(array_map('trim', explode(',', $data['allowed_ip_ranges'])))
            : $settings->allowed_ip_ranges,
        'is_auto_update_enabled' => $data['is_auto_update_enabled'] ?? $settings->is_auto_update_enabled,
        'auto_update_frequency' => $data['auto_update_frequency'] ?? $settings->auto_update_frequency,
        'is_ai_code_review_enabled' => $data['is_ai_code_review_enabled'] ?? $settings->is_ai_code_review_enabled,
        'is_ai_error_analysis_enabled' => $data['is_ai_error_analysis_enabled'] ?? $settings->is_ai_error_analysis_enabled,
    ]);

    return back()->with('success', 'Settings updated successfully.');
})->name('admin.settings.update');
