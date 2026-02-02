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

// Deployment AI Analysis web routes (for frontend session-based auth)
Route::get('/web-api/deployments/{uuid}/analysis', function (string $uuid) {
    $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();

    if (! $deployment) {
        return response()->json(['status' => 'not_found', 'message' => 'Deployment not found'], 404);
    }

    // Verify the deployment belongs to the current team
    $application = $deployment->application;
    if (! $application || $application->team()?->id !== currentTeam()->id) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    $analysis = $deployment->logAnalysis;

    if ($analysis === null) {
        return response()->json([
            'status' => 'not_found',
            'message' => 'No analysis available for this deployment',
        ], 404);
    }

    return response()->json([
        'status' => $analysis->status,
        'analysis' => [
            'id' => $analysis->id,
            'status' => $analysis->status,
            'severity' => $analysis->severity,
            'category' => $analysis->category,
            'root_cause' => $analysis->root_cause,
            'root_cause_details' => $analysis->root_cause_details,
            'solution' => $analysis->solution,
            'affected_files' => $analysis->affected_files,
            'prevention' => $analysis->prevention,
            'confidence' => $analysis->confidence,
            'ai_provider' => $analysis->ai_provider,
            'created_at' => $analysis->created_at?->toISOString(),
            'updated_at' => $analysis->updated_at?->toISOString(),
        ],
    ]);
})->name('web-api.deployments.analysis');

Route::post('/web-api/deployments/{uuid}/analyze', function (string $uuid) {
    if (! config('ai.enabled', true)) {
        return response()->json([
            'error' => 'AI analysis is disabled',
            'hint' => 'Enable AI analysis by setting AI_ANALYSIS_ENABLED=true',
        ], 503);
    }

    $analyzer = app(\App\Services\AI\DeploymentLogAnalyzer::class);
    if (! $analyzer->isAvailable()) {
        return response()->json([
            'error' => 'No AI provider available',
            'hint' => 'Configure at least one AI provider (ANTHROPIC_API_KEY, OPENAI_API_KEY, or Ollama)',
        ], 503);
    }

    $deployment = \App\Models\ApplicationDeploymentQueue::where('deployment_uuid', $uuid)->first();

    if (! $deployment) {
        return response()->json(['error' => 'Deployment not found'], 404);
    }

    // Verify the deployment belongs to the current team
    $application = $deployment->application;
    if (! $application || $application->team()?->id !== currentTeam()->id) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    // Check if already analyzing
    $existingAnalysis = $deployment->logAnalysis;
    if ($existingAnalysis?->isAnalyzing()) {
        return response()->json([
            'status' => 'analyzing',
            'message' => 'Analysis is already in progress',
        ]);
    }

    // Dispatch job
    \App\Jobs\AnalyzeDeploymentLogsJob::dispatch($deployment->id);

    return response()->json([
        'status' => 'analyzing',
        'message' => 'Analysis started',
    ]);
})->name('web-api.deployments.analyze');

Route::get('/web-api/ai/status', function () {
    $analyzer = app(\App\Services\AI\DeploymentLogAnalyzer::class);

    return response()->json([
        'enabled' => config('ai.enabled', true),
        'available' => $analyzer->isAvailable(),
        'provider' => $analyzer->getActiveProvider(),
    ]);
})->name('web-api.ai.status');
