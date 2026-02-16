<?php

namespace App\Actions\Project;

use App\Models\Project;
use App\Models\SharedEnvironmentVariable;
use Visus\Cuid2\Cuid2;

class CloneProjectAction
{
    /**
     * Clone a project with optional components.
     */
    public function execute(
        Project $source,
        string $newName,
        bool $cloneSharedVars = false,
        bool $cloneTags = false,
        bool $cloneSettings = false,
    ): Project {
        $newProject = Project::create([
            'name' => $newName,
            'description' => $source->description,
            'team_id' => $source->team_id,
        ]);

        // Clone additional environments beyond the three defaults
        $defaultEnvNames = ['development', 'uat', 'production'];
        $source->load('environments');
        foreach ($source->environments as $env) {
            if (in_array($env->name, $defaultEnvNames)) {
                continue;
            }

            $newProject->environments()->create([
                'name' => $env->name,
                'type' => $env->type ?? 'development',
                'uuid' => (string) new Cuid2,
                'requires_approval' => $env->requires_approval ?? false,
            ]);
        }

        // Clone settings
        if ($cloneSettings && $source->settings) {
            $newProject->settings()->update([
                'default_server_id' => $source->settings->default_server_id,
            ]);
        }

        // Clone shared variables
        if ($cloneSharedVars) {
            $source->load('environment_variables');
            foreach ($source->environment_variables()->whereNull('environment_id')->get() as $var) {
                SharedEnvironmentVariable::create([
                    'key' => $var->key,
                    'value' => $var->value,
                    'is_shown_once' => $var->is_shown_once,
                    'type' => 'project',
                    'team_id' => $source->team_id,
                    'project_id' => $newProject->id,
                ]);
            }
        }

        // Clone tags
        if ($cloneTags) {
            $source->load('tags');
            $newProject->tags()->attach($source->tags->pluck('id'));
        }

        return $newProject;
    }
}
