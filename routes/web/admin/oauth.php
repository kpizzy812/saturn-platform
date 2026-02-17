<?php

/**
 * Admin OAuth/SSO Settings routes
 *
 * Manage OAuth provider configuration for social login.
 */

use App\Models\OauthSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/settings/oauth', function () {
    $providers = OauthSetting::all()->map(function ($provider) {
        $data = $provider->makeVisible(['client_id', 'redirect_uri', 'tenant', 'base_url'])->toArray();

        // Mask client_secret - only indicate presence
        if (! empty($provider->client_secret)) {
            $data['client_secret'] = '••••••••';
        }

        return $data;
    });

    return Inertia::render('Admin/Settings/OAuth', [
        'providers' => $providers,
    ]);
})->name('admin.settings.oauth');

Route::post('/settings/oauth/{id}', function (Request $request, int $id) {
    $provider = OauthSetting::findOrFail($id);

    $validated = $request->validate([
        'enabled' => 'nullable|boolean',
        'client_id' => 'nullable|string|max:500',
        'client_secret' => 'nullable|string|max:1000',
        'redirect_uri' => 'nullable|string|max:500',
        'tenant' => 'nullable|string|max:500',
        'base_url' => 'nullable|string|max:500',
    ]);

    $updateData = [];

    // Only update fields that were actually sent
    if (array_key_exists('enabled', $validated)) {
        $updateData['enabled'] = $validated['enabled'] ?? false;
    }

    if (array_key_exists('client_id', $validated)) {
        $updateData['client_id'] = $validated['client_id'];
    }

    // Only update secret if it's not the placeholder
    $placeholder = '••••••••';
    if (array_key_exists('client_secret', $validated) && $validated['client_secret'] !== $placeholder) {
        $updateData['client_secret'] = $validated['client_secret'];
    }

    if (array_key_exists('redirect_uri', $validated)) {
        $updateData['redirect_uri'] = $validated['redirect_uri'];
    }

    if (array_key_exists('tenant', $validated)) {
        $updateData['tenant'] = $validated['tenant'];
    }

    if (array_key_exists('base_url', $validated)) {
        $updateData['base_url'] = $validated['base_url'];
    }

    // Validate that required fields are present before enabling
    if (($updateData['enabled'] ?? $provider->enabled) === true) {
        $clientId = $updateData['client_id'] ?? $provider->client_id;
        $clientSecret = array_key_exists('client_secret', $updateData) ? $updateData['client_secret'] : $provider->client_secret;

        if (empty($clientId) || empty($clientSecret)) {
            return back()->with('error', "Cannot enable {$provider->provider}: Client ID and Client Secret are required.");
        }

        // Check provider-specific requirements
        $providerName = $provider->provider;
        if ($providerName === 'azure') {
            $tenant = $updateData['tenant'] ?? $provider->tenant;
            if (empty($tenant)) {
                return back()->with('error', 'Cannot enable Azure AD: Tenant is required.');
            }
        }

        if (in_array($providerName, ['authentik', 'clerk', 'zitadel'])) {
            $baseUrl = $updateData['base_url'] ?? $provider->base_url;
            if (empty($baseUrl)) {
                return back()->with('error', "Cannot enable {$providerName}: Base URL is required.");
            }
        }
    }

    $provider->update($updateData);

    $status = ($updateData['enabled'] ?? $provider->enabled) ? 'enabled' : 'updated';

    return back()->with('success', ucfirst($provider->provider)." provider {$status} successfully.");
})->name('admin.settings.oauth.update');
