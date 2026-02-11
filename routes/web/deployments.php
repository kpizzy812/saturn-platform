<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Deployments Routes
|--------------------------------------------------------------------------
|
| Routes for viewing and managing application deployments.
|
*/

Route::get('/deployments', function () {
    $applicationIds = \App\Models\Application::ownedByCurrentTeam()->pluck('id');

    $deployments = \App\Models\ApplicationDeploymentQueue::whereIn('application_id', $applicationIds)
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get()
        ->map(fn ($d) => [
            'id' => $d->id,
            'uuid' => $d->deployment_uuid,
            'application_id' => $d->application_id,
            'status' => $d->status,
            'commit' => $d->commit,
            'commit_message' => $d->commit_message,
            'created_at' => $d->created_at?->toISOString(),
            'updated_at' => $d->updated_at?->toISOString(),
            'service_name' => $d->application_name,
            'trigger' => $d->is_webhook ? 'push' : ($d->rollback ? 'rollback' : 'manual'),
        ]);

    return Inertia::render('Deployments/Index', [
        'deployments' => $deployments,
    ]);
})->name('deployments.index');

Route::get('/deployments/{uuid}', function (string $uuid) {
    $deployment = \App\Models\ApplicationDeploymentQueue::with(['application', 'user'])
        ->where('deployment_uuid', $uuid)
        ->first();

    if (! $deployment) {
        return Inertia::render('Deployments/Show', ['deployment' => null]);
    }

    $logs = $deployment->logs ? json_decode($deployment->logs, true) : [];
    $buildLogs = [];
    $deployLogs = [];

    // Filter out hidden logs and format for display
    foreach ($logs as $log) {
        // Skip hidden logs (internal commands, sensitive data)
        if (! empty($log['hidden'])) {
            continue;
        }

        $timestamp = $log['timestamp'] ?? '';
        $output = $log['output'] ?? $log['message'] ?? '';

        // Format timestamp if present
        $formattedLine = $output;
        if ($timestamp) {
            try {
                $time = \Carbon\Carbon::parse($timestamp)->format('H:i:s');
                $formattedLine = "[$time] ".$output;
            } catch (\Exception $e) {
                $formattedLine = $output;
            }
        }

        // All logs go to buildLogs since we don't have separate deploy phase
        $buildLogs[] = trim($formattedLine);
    }

    // Calculate duration
    $duration = null;
    $startTime = $deployment->started_at ?? $deployment->created_at;
    if ($startTime && $deployment->updated_at && $deployment->status !== 'in_progress') {
        $diff = $startTime->diff($deployment->updated_at);
        if ($diff->i > 0) {
            $duration = $diff->i.'m '.$diff->s.'s';
        } else {
            $duration = $diff->s.'s';
        }
    }

    // Get application for environment variables and UUID
    $application = $deployment->application;
    $environment = [];

    if ($application) {
        // Get environment variables (mask sensitive values)
        $envVars = $application->environment_variables ?? collect();
        foreach ($envVars as $envVar) {
            $key = $envVar->key;
            $value = $envVar->is_shown_once ? '********' : ($envVar->value ?? '');
            $environment[$key] = $value;
        }
    }

    // Get user who triggered the deployment
    $author = null;
    if ($deployment->user) {
        $author = [
            'name' => $deployment->user->name,
            'email' => $deployment->user->email,
            'avatar' => $deployment->user->avatar ? '/storage/'.$deployment->user->avatar : null,
        ];
    }

    // Determine trigger type
    $trigger = 'manual';
    if ($deployment->is_webhook) {
        $trigger = 'push';
    } elseif ($deployment->is_api) {
        $trigger = 'api';
    } elseif ($deployment->rollback) {
        $trigger = 'rollback';
    }

    $data = [
        'id' => $deployment->id,
        'uuid' => $deployment->deployment_uuid,
        'application_id' => $deployment->application_id,
        'application_uuid' => $application?->uuid,
        'status' => $deployment->status,
        'commit' => $deployment->commit,
        'commit_message' => $deployment->commit_message ?? 'Deployment',
        'created_at' => $deployment->created_at?->toISOString(),
        'updated_at' => $deployment->updated_at?->toISOString(),
        'service_name' => $deployment->application_name ?? $application?->name,
        'trigger' => $trigger,
        'duration' => $duration,
        'build_logs' => $buildLogs,
        'deploy_logs' => $deployLogs,
        'environment' => $environment,
        'author' => $author,
    ];

    return Inertia::render('Deployments/Show', [
        'deployment' => $data,
    ]);
})->name('deployments.show');

Route::get('/deployments/{uuid}/logs', function (string $uuid) {
    $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();

    return Inertia::render('Deployments/BuildLogs', [
        'deployment' => $deployment ? [
            'uuid' => $deployment->deployment_uuid,
            'status' => $deployment->status,
            'application_name' => $deployment->application_name,
        ] : null,
    ]);
})->name('deployments.logs');

// JSON endpoint for deployment logs (for XHR requests)
Route::get('/deployments/{uuid}/logs/json', function (string $uuid) {
    $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();
    if (! $deployment) {
        return response()->json(['message' => 'Deployment not found.'], 404);
    }

    // Verify the deployment belongs to the current team
    $application = $deployment->application;
    if (! $application || $application->team()?->id !== currentTeam()->id) {
        return response()->json(['message' => 'Deployment not found.'], 404);
    }

    $logs = $deployment->logs;
    $parsedLogs = [];

    if ($logs) {
        $parsedLogs = json_decode($logs, true) ?: [];
    }

    return response()->json([
        'deployment_uuid' => $deployment->deployment_uuid,
        'status' => $deployment->status,
        'logs' => $parsedLogs,
    ]);
})->name('deployments.logs.json');

// JSON endpoint for application container logs (for XHR requests)
// Supports incremental fetching via ?since=<unix_timestamp> parameter
// Supports container filtering via ?container=<name> parameter
Route::get('/applications/{uuid}/logs/json', function (string $uuid, Request $request) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->first();

    if (! $application) {
        return response()->json(['message' => 'Application not found.'], 404);
    }

    $server = $application->destination->server;
    if (! $server) {
        return response()->json(['message' => 'Server not found.'], 404);
    }

    // Get query parameters
    $since = $request->query('since');
    $containerFilter = $request->query('container');

    // Get container logs via SSH
    try {
        $containers = getCurrentApplicationContainerStatus($server, $application->id, 0, true);

        if ($containers->isEmpty()) {
            return response()->json([
                'container_logs' => 'No containers found for this application.',
                'containers' => [],
                'timestamp' => now()->timestamp,
            ]);
        }

        // Select container: use filtered name or fall back to first
        $targetContainer = null;
        if ($containerFilter) {
            $targetContainer = $containers->first(fn ($c) => ($c['Names'] ?? '') === $containerFilter);
        }
        if (! $targetContainer) {
            $targetContainer = $containers->first();
        }

        $containerName = $targetContainer['Names'] ?? null;
        if ($containerName) {
            if ($since) {
                $logs = instant_remote_process(["docker logs --since {$since} --timestamps {$containerName} 2>&1"], $server);
            } else {
                $logs = instant_remote_process(["docker logs -n 200 --timestamps {$containerName} 2>&1"], $server);
            }

            return response()->json([
                'container_logs' => $logs,
                'containers' => $containers,
                'timestamp' => now()->timestamp,
            ]);
        }

        return response()->json([
            'container_logs' => 'Container not found.',
            'containers' => $containers,
            'timestamp' => now()->timestamp,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to fetch logs: '.$e->getMessage(),
        ], 500);
    }
})->name('applications.logs.json');

// Application environment variables bulk update
Route::patch('/applications/{uuid}/envs/bulk', function (string $uuid, Request $request) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->first();

    if (! $application) {
        return back()->with('error', 'Application not found.');
    }

    $variables = $request->input('variables', []);

    // Delete all existing non-preview environment variables
    $application->environment_variables()
        ->where('is_preview', false)
        ->delete();

    // Create new environment variables
    foreach ($variables as $item) {
        if (empty($item['key'])) {
            continue;
        }

        $application->environment_variables()->create([
            'key' => $item['key'],
            'value' => $item['value'] ?? '',
            'is_preview' => false,
            'is_buildtime' => $item['is_build_time'] ?? false,
            'is_runtime' => true,
        ]);
    }

    return back()->with('success', 'Environment variables saved successfully.');
})->name('applications.envs.bulk');

// Scan .env.example from application repository
Route::post('/web-api/applications/{uuid}/scan-env-example', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->first();

    if (! $application) {
        return response()->json(['message' => 'Application not found.'], 404);
    }

    try {
        $result = (new \App\Actions\Application\ScanEnvExample)->handle($application);

        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Failed to scan: '.$e->getMessage()], 500);
    }
})->name('web-api.applications.scan-env-example');

// Application deployments JSON (for Show page DeploymentsTab)
Route::get('/applications/{uuid}/deployments/json', function (string $uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->first();

    if (! $application) {
        return response()->json(['message' => 'Application not found.'], 404);
    }

    $deployments = \App\Models\ApplicationDeploymentQueue::where('application_id', $application->id)
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get()
        ->map(fn ($d) => [
            'id' => $d->id,
            'uuid' => $d->deployment_uuid,
            'application_id' => $d->application_id,
            'status' => $d->status,
            'commit' => $d->commit,
            'commit_message' => $d->commit_message,
            'created_at' => $d->created_at?->toISOString(),
            'updated_at' => $d->updated_at?->toISOString(),
            'service_name' => $d->application_name,
            'trigger' => $d->is_webhook ? 'push' : ($d->rollback ? 'rollback' : 'manual'),
        ]);

    return response()->json($deployments);
})->name('applications.deployments.json');

// Application environment variables CRUD (web, session-auth)
Route::post('/applications/{uuid}/envs/json', function (string $uuid, Request $request) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $request->validate(['key' => 'required|string']);

    $env = $application->environment_variables()->create([
        'key' => $request->input('key'),
        'value' => $request->input('value', ''),
        'is_preview' => false,
        'is_buildtime' => $request->input('is_build_time', false),
        'is_runtime' => true,
    ]);

    return response()->json(['uuid' => $env->uuid, 'id' => $env->id, 'key' => $env->key, 'value' => $env->value]);
})->name('applications.envs.json.create');

Route::patch('/applications/{uuid}/envs/json', function (string $uuid, Request $request) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $request->validate(['key' => 'required|string', 'value' => 'nullable|string']);

    $env = $application->environment_variables()
        ->where('key', $request->input('key'))
        ->first();

    if (! $env) {
        return response()->json(['message' => 'Variable not found.'], 404);
    }

    $updateData = [
        'key' => $request->input('key'),
        'value' => $request->input('value', ''),
    ];

    if ($request->has('is_build_time')) {
        $updateData['is_buildtime'] = $request->boolean('is_build_time');
    }

    $env->update($updateData);

    return response()->json(['uuid' => $env->uuid, 'key' => $env->key, 'value' => $env->value, 'is_buildtime' => $env->is_buildtime]);
})->name('applications.envs.json.update');

Route::delete('/applications/{uuid}/envs/{env_uuid}/json', function (string $uuid, string $env_uuid) {
    $application = \App\Models\Application::ownedByCurrentTeam()
        ->where('uuid', $uuid)
        ->firstOrFail();

    $env = $application->environment_variables()
        ->where('uuid', $env_uuid)
        ->firstOrFail();

    $env->delete();

    return response()->json(['message' => 'Variable deleted.']);
})->name('applications.envs.json.delete');

// AI Analysis routes are defined in routes/web/web-api.php
