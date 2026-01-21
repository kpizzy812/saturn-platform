<?php

namespace App\Livewire\Project\Shared\EnvironmentVariable;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Add extends Component
{
    use AuthorizesRequests;

    public ?string $key = null;

    public ?string $value = null;

    public bool $is_multiline = false;

    public bool $is_literal = false;

    public bool $is_runtime = true;

    public bool $is_buildtime = true;

    public array $parameters = [];

    /**
     * Get available shared variables from team, project and environment.
     */
    public function availableSharedVariables(): array
    {
        $result = [
            'team' => [],
            'project' => [],
            'environment' => [],
        ];

        $user = Auth::user();
        if (! $user) {
            return $result;
        }

        $team = currentTeam();
        if (! $team) {
            return $result;
        }

        // Get team shared variables
        try {
            $this->authorize('view', $team);
            $result['team'] = $team->environment_variables ?? collect();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // User doesn't have permission to view team variables
        }

        // Get project shared variables if project_uuid is in parameters
        $projectUuid = $this->parameters['project_uuid'] ?? null;
        if ($projectUuid) {
            $project = $team->projects()->where('uuid', $projectUuid)->first();
            if ($project) {
                try {
                    $this->authorize('view', $project);
                    $result['project'] = $project->environment_variables ?? collect();
                } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                    // User doesn't have permission to view project variables
                }
            }
        }

        // Get environment shared variables if environment_uuid is in parameters
        $environmentUuid = $this->parameters['environment_uuid'] ?? null;
        if ($environmentUuid) {
            $environment = $team->environments()->where('uuid', $environmentUuid)->first();
            if ($environment) {
                try {
                    $this->authorize('view', $environment);
                    $result['environment'] = $environment->environment_variables ?? collect();
                } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                    // User doesn't have permission to view environment variables
                }
            }
        }

        return $result;
    }

    public function render()
    {
        return view('livewire.project.shared.environment-variable.add');
    }
}
