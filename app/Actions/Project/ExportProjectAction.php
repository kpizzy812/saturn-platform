<?php

namespace App\Actions\Project;

use App\Models\Project;

class ExportProjectAction
{
    /**
     * Export project configuration as a structured array.
     *
     * @param  bool  $includeSecrets  Whether to include unmasked secret values (admin only)
     */
    public function execute(Project $project, bool $includeSecrets = false): array
    {
        $project->load(['environments', 'settings', 'tags', 'environment_variables']);

        return [
            'export_version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'project' => [
                'name' => $project->name,
                'description' => $project->description,
                'uuid' => $project->uuid,
            ],
            'settings' => [
                'default_server_id' => $project->settings?->default_server_id,
            ],
            'environments' => $project->environments->map(fn ($env) => [
                'name' => $env->name,
                'type' => $env->type ?? 'development',
                'requires_approval' => $env->requires_approval ?? false,
            ])->toArray(),
            'shared_variables' => $project->environment_variables
                ->whereNull('environment_id')
                ->map(fn ($var) => [
                    'key' => $var->key,
                    'value' => $includeSecrets ? $var->value : '***MASKED***',
                    'is_shown_once' => $var->is_shown_once,
                ])->values()->toArray(),
            'tags' => $project->tags->pluck('name')->toArray(),
        ];
    }
}
