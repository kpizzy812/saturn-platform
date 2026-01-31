<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Domains Routes
|--------------------------------------------------------------------------
|
| Routes for domain management, SSL certificates, and redirect rules.
|
*/

Route::get('/domains', function () {
    $domains = collect();
    $id = 1;

    // Collect FQDNs from applications
    $applications = \App\Models\Application::ownedByCurrentTeam()->get();
    foreach ($applications as $app) {
        if (! $app->fqdn) {
            continue;
        }
        foreach (explode(',', $app->fqdn) as $index => $fqdn) {
            $fqdn = trim($fqdn);
            if (empty($fqdn)) {
                continue;
            }
            $domains->push([
                'id' => $id++,
                'uuid' => $app->uuid.'-'.$index,
                'domain' => preg_replace('#^https?://#', '', $fqdn),
                'fullUrl' => $fqdn,
                'sslStatus' => str_starts_with($fqdn, 'https://') ? 'active' : 'none',
                'resourceName' => $app->name,
                'resourceType' => 'application',
                'resourceUuid' => $app->uuid,
                'isPrimary' => $index === 0,
                'created_at' => $app->created_at?->toISOString(),
            ]);
        }
    }

    // Collect FQDNs from service applications
    $services = \App\Models\Service::ownedByCurrentTeam()->with('applications')->get();
    foreach ($services as $service) {
        foreach ($service->applications as $svcApp) {
            if (! $svcApp->fqdn) {
                continue;
            }
            foreach (explode(',', $svcApp->fqdn) as $index => $fqdn) {
                $fqdn = trim($fqdn);
                if (empty($fqdn)) {
                    continue;
                }
                $domains->push([
                    'id' => $id++,
                    'uuid' => $service->uuid.'-svc-'.$index,
                    'domain' => preg_replace('#^https?://#', '', $fqdn),
                    'fullUrl' => $fqdn,
                    'sslStatus' => str_starts_with($fqdn, 'https://') ? 'active' : 'none',
                    'resourceName' => $service->name.' ('.$svcApp->name.')',
                    'resourceType' => 'service',
                    'resourceUuid' => $service->uuid,
                    'isPrimary' => $index === 0,
                    'created_at' => $service->created_at?->toISOString(),
                ]);
            }
        }
    }

    return Inertia::render('Domains/Index', [
        'domains' => $domains->values(),
    ]);
})->name('domains.index');

Route::get('/domains/add', function () {
    $applications = \App\Models\Application::ownedByCurrentTeam()
        ->select('id', 'uuid', 'name')->get();
    $services = \App\Models\Service::ownedByCurrentTeam()
        ->select('id', 'uuid', 'name')->get();

    return Inertia::render('Domains/Add', [
        'applications' => $applications,
        'databases' => [],
        'services' => $services,
    ]);
})->name('domains.add');

Route::get('/domains/{uuid}', function (string $uuid) {
    // Find an application whose FQDN contains this domain info
    $app = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', 'like', explode('-', $uuid)[0].'%')
        ->first();

    $domain = null;
    $sslCertificate = null;

    if ($app && $app->fqdn) {
        $fqdns = explode(',', $app->fqdn);
        $fqdn = trim($fqdns[0] ?? '');
        $domainName = preg_replace('#^https?://#', '', $fqdn);
        $domain = [
            'id' => $app->id,
            'uuid' => $uuid,
            'domain' => $domainName,
            'fullUrl' => $fqdn,
            'sslStatus' => str_starts_with($fqdn, 'https://') ? 'active' : 'none',
            'resourceName' => $app->name,
            'resourceType' => 'application',
            'isPrimary' => true,
            'created_at' => $app->created_at?->toISOString(),
        ];

        // Try to find matching SSL certificate
        $serverIds = auth()->user()->currentTeam()->servers()->pluck('id');
        $sslCertificate = \App\Models\SslCertificate::whereIn('server_id', $serverIds)
            ->where('common_name', $domainName)
            ->first();
        if ($sslCertificate) {
            $sslCertificate = [
                'id' => $sslCertificate->id,
                'commonName' => $sslCertificate->common_name,
                'validUntil' => $sslCertificate->valid_until?->toISOString(),
                'isExpired' => $sslCertificate->valid_until?->isPast() ?? false,
            ];
        }
    }

    return Inertia::render('Domains/Show', [
        'domain' => $domain,
        'sslCertificate' => $sslCertificate,
    ]);
})->name('domains.show');

Route::get('/domains/{uuid}/redirects', function (string $uuid) {
    // Find the application for this domain
    $app = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', 'like', explode('-', $uuid)[0].'%')
        ->first();

    $redirects = \App\Models\RedirectRule::ownedByCurrentTeam()
        ->when($app, fn ($q) => $q->where('application_id', $app->id))
        ->get()
        ->map(fn ($r) => [
            'id' => $r->id,
            'uuid' => $r->uuid,
            'source' => $r->source,
            'target' => $r->target,
            'type' => $r->type,
            'enabled' => $r->enabled,
            'hits' => $r->hits,
            'created_at' => $r->created_at?->toISOString(),
        ]);

    return Inertia::render('Domains/Redirects', [
        'redirects' => $redirects,
        'domainUuid' => $uuid,
    ]);
})->name('domains.redirects');

// Redirect rules CRUD
Route::post('/domains/{uuid}/redirects', function (Request $request, string $uuid) {
    $validated = $request->validate([
        'source' => 'required|string|max:500',
        'target' => 'required|string|max:500',
        'type' => 'required|string|in:301,302',
    ]);

    $app = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', 'like', explode('-', $uuid)[0].'%')
        ->first();

    \App\Models\RedirectRule::create([
        ...$validated,
        'team_id' => currentTeam()->id,
        'application_id' => $app?->id,
        'enabled' => true,
    ]);

    return redirect()->back()->with('success', 'Redirect rule created');
})->name('domains.redirects.store');

Route::put('/domains/{uuid}/redirects/{id}', function (Request $request, string $uuid, int $id) {
    $rule = \App\Models\RedirectRule::ownedByCurrentTeam()->where('id', $id)->firstOrFail();

    $validated = $request->validate([
        'source' => 'sometimes|string|max:500',
        'target' => 'sometimes|string|max:500',
        'type' => 'sometimes|string|in:301,302',
        'enabled' => 'sometimes|boolean',
    ]);

    $rule->update($validated);

    return redirect()->back()->with('success', 'Redirect rule updated');
})->name('domains.redirects.update');

Route::delete('/domains/{uuid}/redirects/{id}', function (string $uuid, int $id) {
    $rule = \App\Models\RedirectRule::ownedByCurrentTeam()->where('id', $id)->firstOrFail();
    $rule->delete();

    return redirect()->back()->with('success', 'Redirect rule deleted');
})->name('domains.redirects.destroy');

// SSL routes
Route::get('/ssl', function () {
    $team = auth()->user()->currentTeam();
    $serverIds = $team->servers()->pluck('id');

    $certificates = \App\Models\SslCertificate::whereIn('server_id', $serverIds)
        ->with('server:id,name')
        ->get()
        ->map(fn ($cert) => [
            'id' => $cert->id,
            'commonName' => $cert->common_name,
            'subjectAlternativeNames' => $cert->subject_alternative_names ?? [],
            'validUntil' => $cert->valid_until?->toISOString(),
            'isExpired' => $cert->valid_until?->isPast() ?? false,
            'isExpiringSoon' => $cert->valid_until?->diffInDays(now()) <= 30,
            'serverName' => $cert->server?->name,
            'isCaCertificate' => $cert->is_ca_certificate ?? false,
            'created_at' => $cert->created_at?->toISOString(),
        ]);

    return Inertia::render('SSL/Index', [
        'certificates' => $certificates,
    ]);
})->name('ssl.index');

Route::get('/ssl/upload', function () {
    // Collect all unique FQDNs from applications in the current team
    $team = auth()->user()->currentTeam();
    $domains = [];
    if ($team) {
        $projects = $team->projects()->with('environments.applications')->get();
        $id = 1;
        foreach ($projects as $project) {
            foreach ($project->environments as $env) {
                foreach ($env->applications as $app) {
                    if ($app->fqdn) {
                        // An app can have multiple FQDNs comma-separated
                        foreach (explode(',', $app->fqdn) as $fqdn) {
                            $domain = preg_replace('#^https?://#', '', trim($fqdn));
                            if ($domain && ! in_array($domain, array_column($domains, 'domain'))) {
                                $domains[] = ['id' => $id++, 'domain' => $domain];
                            }
                        }
                    }
                }
            }
        }
    }

    return Inertia::render('SSL/Upload', [
        'domains' => $domains,
    ]);
})->name('ssl.upload');
