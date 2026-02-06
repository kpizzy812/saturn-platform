<?php

/**
 * Admin SSH Keys routes
 *
 * SSH key management overview: listing all keys across teams,
 * viewing key details with usage info, and running key audits.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/ssh-keys', function (Request $request) {
    $query = \App\Models\PrivateKey::withCount(['servers', 'applications', 'githubApps', 'gitlabApps'])
        ->with('team:id,name');

    // Search filter
    if ($search = $request->get('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('fingerprint', 'like', "%{$search}%");
        });
    }

    // Team filter
    if ($teamId = $request->get('team')) {
        $query->where('team_id', (int) $teamId);
    }

    // Type filter: ssh (is_git_related=false), git (is_git_related=true)
    if ($type = $request->get('type')) {
        if ($type === 'ssh') {
            $query->where('is_git_related', false);
        } elseif ($type === 'git') {
            $query->where('is_git_related', true);
        }
    }

    // Usage filter: in_use or unused
    if ($usage = $request->get('usage')) {
        if ($usage === 'in_use') {
            $query->where(function ($q) {
                $q->has('servers')
                    ->orHas('applications')
                    ->orHas('githubApps')
                    ->orHas('gitlabApps');
            });
        } elseif ($usage === 'unused') {
            $query->doesntHave('servers')
                ->doesntHave('applications')
                ->doesntHave('githubApps')
                ->doesntHave('gitlabApps');
        }
    }

    $keys = $query->latest()
        ->paginate(50)
        ->through(function ($key) {
            return [
                'id' => $key->id,
                'uuid' => $key->uuid,
                'name' => $key->name,
                'description' => $key->description,
                'fingerprint' => $key->fingerprint,
                'is_git_related' => $key->is_git_related,
                'team_id' => $key->team_id,
                'team_name' => $key->team?->name ?? 'Unknown',
                'servers_count' => $key->servers_count,
                'applications_count' => $key->applications_count,
                'github_apps_count' => $key->github_apps_count,
                'gitlab_apps_count' => $key->gitlab_apps_count,
                'is_in_use' => ($key->servers_count + $key->applications_count + $key->github_apps_count + $key->gitlab_apps_count) > 0,
                'created_at' => $key->created_at,
            ];
        });

    $allTeams = \App\Models\Team::orderBy('name')->get(['id', 'name']);

    return Inertia::render('Admin/SshKeys/Index', [
        'keys' => $keys,
        'allTeams' => $allTeams,
        'filters' => [
            'search' => $request->get('search'),
            'team' => $request->get('team'),
            'type' => $request->get('type'),
            'usage' => $request->get('usage'),
        ],
    ]);
})->name('admin.ssh-keys.index');

Route::get('/ssh-keys/{id}', function (int $id) {
    $key = \App\Models\PrivateKey::with(['team:id,name'])
        ->withCount(['servers', 'applications', 'githubApps', 'gitlabApps'])
        ->findOrFail($id);

    // Load usage details
    $servers = $key->servers()->with('team:id,name')->get()->map(fn ($s) => [
        'id' => $s->id,
        'uuid' => $s->uuid,
        'name' => $s->name,
        'ip' => $s->ip,
        'team_name' => $s->team?->name ?? 'Unknown',
    ]);

    $applications = $key->applications()->get()->map(fn ($a) => [
        'id' => $a->id,
        'uuid' => $a->uuid,
        'name' => $a->name,
        'status' => $a->status ?? 'unknown',
    ]);

    $githubApps = $key->githubApps()->get()->map(fn ($g) => [
        'id' => $g->id,
        'name' => $g->name,
        'type' => 'github',
    ]);

    $gitlabApps = $key->gitlabApps()->get()->map(fn ($g) => [
        'id' => $g->id,
        'name' => $g->name,
        'type' => 'gitlab',
    ]);

    // Generate MD5 fingerprint for compatibility display
    $md5Fingerprint = null;
    try {
        $md5Fingerprint = \App\Models\PrivateKey::generateMd5Fingerprint($key->private_key);
    } catch (\Throwable $e) {
        // Key might be corrupt
    }

    return Inertia::render('Admin/SshKeys/Show', [
        'sshKey' => [
            'id' => $key->id,
            'uuid' => $key->uuid,
            'name' => $key->name,
            'description' => $key->description,
            'fingerprint' => $key->fingerprint,
            'md5_fingerprint' => $md5Fingerprint,
            'public_key' => $key->public_key,
            'is_git_related' => $key->is_git_related,
            'team_id' => $key->team_id,
            'team_name' => $key->team?->name ?? 'Unknown',
            'servers_count' => $key->servers_count,
            'applications_count' => $key->applications_count,
            'github_apps_count' => $key->github_apps_count,
            'gitlab_apps_count' => $key->gitlab_apps_count,
            'is_in_use' => ($key->servers_count + $key->applications_count + $key->github_apps_count + $key->gitlab_apps_count) > 0,
            'created_at' => $key->created_at,
            'updated_at' => $key->updated_at,
        ],
        'usage' => [
            'servers' => $servers,
            'applications' => $applications,
            'github_apps' => $githubApps,
            'gitlab_apps' => $gitlabApps,
        ],
    ]);
})->name('admin.ssh-keys.show');

Route::post('/ssh-keys/{id}/audit', function (int $id) {
    $key = \App\Models\PrivateKey::findOrFail($id);

    $results = [
        'key_valid' => false,
        'fingerprint_consistent' => false,
        'file_exists' => false,
        'messages' => [],
    ];

    // Validate key structure
    try {
        $results['key_valid'] = \App\Models\PrivateKey::validatePrivateKey($key->private_key);
        if (! $results['key_valid']) {
            $results['messages'][] = 'Private key structure is invalid.';
        }
    } catch (\Throwable $e) {
        $results['messages'][] = 'Failed to validate key: '.$e->getMessage();
    }

    // Check fingerprint consistency
    if ($results['key_valid']) {
        try {
            $computedFingerprint = \App\Models\PrivateKey::generateFingerprint($key->private_key);
            $results['fingerprint_consistent'] = $computedFingerprint === $key->fingerprint;
            if (! $results['fingerprint_consistent']) {
                $results['messages'][] = 'Stored fingerprint does not match computed fingerprint.';
            }
        } catch (\Throwable $e) {
            $results['messages'][] = 'Failed to verify fingerprint: '.$e->getMessage();
        }
    }

    // Check file exists in storage
    $filename = "ssh_key@{$key->uuid}";
    $disk = \Illuminate\Support\Facades\Storage::disk('ssh-keys');
    $results['file_exists'] = $disk->exists($filename);
    if (! $results['file_exists']) {
        $results['messages'][] = 'SSH key file not found in storage.';
    }

    if (empty($results['messages'])) {
        $results['messages'][] = 'All checks passed.';
    }

    $allPassed = $results['key_valid'] && $results['fingerprint_consistent'] && $results['file_exists'];

    return back()->with(
        $allPassed ? 'success' : 'error',
        $allPassed ? 'Key audit passed: all checks OK.' : 'Key audit found issues: '.implode(' ', $results['messages'])
    );
})->name('admin.ssh-keys.audit');
