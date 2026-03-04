<?php

namespace App\Http\Controllers\Api;

use App\Models\Application;
use App\Models\EnvironmentVariable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnvironmentVariableController extends ApiController
{
    /**
     * Parse .env content and preview or import variables for an application.
     *
     * Request body:
     *   - content (string): Raw .env file content.
     *   - save (bool): If true, persist the variables.
     *   - conflict_resolution (object): Map of key => 'import'|'skip' for conflict resolution.
     */
    public function bulkImport(Request $request, string $uuid): JsonResponse
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->first();

        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        Gate::authorize('update', $application);

        $content = $request->input('content', '');
        $save = $request->boolean('save', false);
        $conflictResolution = $request->input('conflict_resolution', []);

        $parsed = $this->parseEnvContent($content);

        $existingKeys = EnvironmentVariable::where('resourceable_type', Application::class)
            ->where('resourceable_id', $application->id)
            ->where('is_preview', false)
            ->pluck('key')
            ->toArray();

        $preview = array_map(function (array $item) use ($existingKeys, $conflictResolution): array {
            $isConflict = in_array($item['key'], $existingKeys, true);
            $defaultAction = $isConflict ? 'skip' : 'import';

            return [
                'key' => $item['key'],
                'value' => $item['value'],
                'status' => $isConflict ? 'conflict' : 'new',
                'action' => $conflictResolution[$item['key']] ?? $defaultAction,
            ];
        }, $parsed);

        if ($save) {
            foreach ($preview as $item) {
                if ($item['action'] === 'skip') {
                    continue;
                }

                if ($item['status'] === 'conflict') {
                    EnvironmentVariable::where('resourceable_type', Application::class)
                        ->where('resourceable_id', $application->id)
                        ->where('key', $item['key'])
                        ->where('is_preview', false)
                        ->update(['value' => $item['value']]);
                } else {
                    $application->environment_variables()->create([
                        'key' => $item['key'],
                        'value' => $item['value'],
                        'is_preview' => false,
                        'is_buildtime' => false,
                        'is_runtime' => true,
                        'resourceable_type' => Application::class,
                        'resourceable_id' => $application->id,
                    ]);
                }
            }

            $importedCount = count(array_filter($preview, fn (array $item): bool => $item['action'] === 'import'));

            return response()->json([
                'message' => "{$importedCount} variable(s) imported successfully.",
                'preview' => $preview,
            ]);
        }

        return response()->json(['preview' => $preview]);
    }

    /**
     * Return a diff of environment variables between two applications.
     *
     * Query parameters:
     *   - compare_uuid (string): UUID of the application to compare against.
     */
    public function diff(Request $request, string $uuid): JsonResponse
    {
        $application = Application::ownedByCurrentTeam()
            ->where('uuid', $uuid)
            ->first();

        if (! $application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        Gate::authorize('view', $application);

        $compareUuid = $request->query('compare_uuid');
        if (! $compareUuid) {
            return response()->json(['message' => 'compare_uuid is required.'], 400);
        }

        $compareApp = Application::ownedByCurrentTeam()
            ->where('uuid', $compareUuid)
            ->first();

        if (! $compareApp) {
            return response()->json(['message' => 'Comparison application not found.'], 404);
        }

        $sourceVars = EnvironmentVariable::where('resourceable_type', Application::class)
            ->where('resourceable_id', $application->id)
            ->where('is_preview', false)
            ->orderBy('key')
            ->get()
            ->keyBy('key');

        $targetVars = EnvironmentVariable::where('resourceable_type', Application::class)
            ->where('resourceable_id', $compareApp->id)
            ->where('is_preview', false)
            ->orderBy('key')
            ->get()
            ->keyBy('key');

        $allKeys = $sourceVars->keys()
            ->merge($targetVars->keys())
            ->unique()
            ->sort()
            ->values();

        $diff = $allKeys->map(function (string $key) use ($sourceVars, $targetVars): array {
            $source = $sourceVars->get($key);
            $target = $targetVars->get($key);

            if ($source && ! $target) {
                return ['key' => $key, 'status' => 'removed', 'source_value' => $source->value, 'target_value' => null];
            }

            if (! $source && $target) {
                return ['key' => $key, 'status' => 'added', 'source_value' => null, 'target_value' => $target->value];
            }

            if ($source->value !== $target->value) {
                return ['key' => $key, 'status' => 'changed', 'source_value' => $source->value, 'target_value' => $target->value];
            }

            return ['key' => $key, 'status' => 'unchanged', 'source_value' => $source->value, 'target_value' => $target->value];
        })->values();

        return response()->json([
            'source' => ['uuid' => $application->uuid, 'name' => $application->name],
            'target' => ['uuid' => $compareApp->uuid, 'name' => $compareApp->name],
            'diff' => $diff,
        ]);
    }

    /**
     * Parse raw .env file content into key-value pairs.
     * Validates keys against POSIX standard and skips duplicates.
     *
     * @return array<int, array{key: string, value: string}>
     */
    private function parseEnvContent(string $content): array
    {
        $result = [];
        $seen = [];

        foreach (explode("\n", $content) as $rawLine) {
            $line = trim($rawLine);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Remove surrounding single or double quotes
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // POSIX variable name: letter or underscore, then alphanumeric or underscore
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = ['key' => $key, 'value' => $value];
        }

        return $result;
    }
}
