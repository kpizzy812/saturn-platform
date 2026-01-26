<?php

namespace App\Traits\Deployment;

use Illuminate\Support\Collection;

/**
 * Trait for Docker build secrets management during deployment.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $pull_request_id
 * - $env_args, $build_secrets, $secrets_hash_key
 *
 * Required methods from parent class:
 * - generate_env_variables()
 */
trait HandlesBuildSecrets
{
    /**
     * Generate Docker -e flags for passing secrets to build container.
     */
    private function generate_docker_env_flags_for_secrets()
    {
        // Only generate env flags if build secrets are enabled
        if (! $this->application->settings->use_build_secrets) {
            return '';
        }

        // Generate env variables if not already done
        // This populates $this->env_args with both user-defined and SATURN_* variables
        if (! $this->env_args || $this->env_args->isEmpty()) {
            $this->generate_env_variables();
        }

        $variables = $this->env_args;

        if ($variables->isEmpty()) {
            return '';
        }

        $secrets_hash = $this->generate_secrets_hash($variables);

        // Get database env vars to check for multiline flag
        $env_vars = $this->pull_request_id === 0
            ? $this->application->environment_variables()->where('is_buildtime', true)->get()
            : $this->application->environment_variables_preview()->where('is_buildtime', true)->get();

        // Map to simple array format for the helper function
        $vars_array = $variables->map(function ($value, $key) use ($env_vars) {
            $env = $env_vars->firstWhere('key', $key);

            return [
                'key' => $key,
                'value' => $value,
                'is_multiline' => $env ? $env->is_multiline : false,
            ];
        });

        $env_flags = generateDockerEnvFlags($vars_array);
        $env_flags .= " -e SATURN_BUILD_SECRETS_HASH={$secrets_hash}";

        return $env_flags;
    }

    /**
     * Generate --secret flags for Docker BuildKit builds.
     */
    private function generate_build_secrets(Collection $variables)
    {
        if ($variables->isEmpty()) {
            $this->build_secrets = '';

            return;
        }

        $this->build_secrets = $variables
            ->map(function ($value, $key) {
                return "--secret id={$key},env={$key}";
            })
            ->implode(' ');

        $this->build_secrets .= ' --secret id=SATURN_BUILD_SECRETS_HASH,env=SATURN_BUILD_SECRETS_HASH';
    }

    /**
     * Generate deterministic hash of secrets for Docker build cache invalidation.
     */
    private function generate_secrets_hash($variables)
    {
        if (! $this->secrets_hash_key) {
            // Use APP_KEY as deterministic hash key to preserve Docker build cache
            // Random keys would change every deployment, breaking cache even when secrets haven't changed
            $this->secrets_hash_key = config('app.key');
        }

        if ($variables instanceof Collection) {
            $secrets_string = $variables
                ->mapWithKeys(function ($value, $key) {
                    return [$key => $value];
                })
                ->sortKeys()
                ->map(function ($value, $key) {
                    return "{$key}={$value}";
                })
                ->implode('|');
        } else {
            $secrets_string = $variables
                ->map(function ($env) {
                    return "{$env->key}={$env->real_value}";
                })
                ->sort()
                ->implode('|');
        }

        return hash_hmac('sha256', $secrets_string, $this->secrets_hash_key);
    }

    /**
     * Add build secrets configuration to Docker Compose file.
     */
    private function add_build_secrets_to_compose($composeFile)
    {
        // Generate env variables if not already done
        // This populates $this->env_args with both user-defined and SATURN_* variables
        if (! $this->env_args || $this->env_args->isEmpty()) {
            $this->generate_env_variables();
        }

        $variables = $this->env_args;

        if ($variables->isEmpty()) {
            return $composeFile;
        }

        $secrets = [];
        foreach ($variables as $key => $value) {
            $secrets[$key] = [
                'environment' => $key,
            ];
        }

        $services = data_get($composeFile, 'services', []);
        foreach ($services as $serviceName => &$service) {
            if (isset($service['build'])) {
                if (is_string($service['build'])) {
                    $service['build'] = [
                        'context' => $service['build'],
                    ];
                }
                if (! isset($service['build']['secrets'])) {
                    $service['build']['secrets'] = [];
                }
                foreach ($variables as $key => $value) {
                    if (! in_array($key, $service['build']['secrets'])) {
                        $service['build']['secrets'][] = $key;
                    }
                }
            }
        }

        $composeFile['services'] = $services;
        $existingSecrets = data_get($composeFile, 'secrets', []);
        if ($existingSecrets instanceof \Illuminate\Support\Collection) {
            $existingSecrets = $existingSecrets->toArray();
        }
        $composeFile['secrets'] = array_replace($existingSecrets, $secrets);

        $this->application_deployment_queue->addLogEntry('Added build secrets configuration to docker-compose file (using environment variables).');

        return $composeFile;
    }
}
