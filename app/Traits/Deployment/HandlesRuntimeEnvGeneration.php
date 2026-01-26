<?php

namespace App\Traits\Deployment;

use Spatie\Url\Url;
use Symfony\Component\Yaml\Yaml;

/**
 * Trait for generating runtime environment variables during deployment.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $pull_request_id, $preview
 * - $build_pack, $workdir, $deployment_uuid, $configuration_dir
 * - $use_build_server, $build_server, $original_server, $server
 *
 * Required methods from parent class:
 * - execute_remote_command(), generate_saturn_env_variables()
 */
trait HandlesRuntimeEnvGeneration
{
    /**
     * Generate runtime environment variables.
     *
     * @return \Illuminate\Support\Collection
     */
    private function generate_runtime_environment_variables()
    {
        $envs = collect([]);
        $sort = $this->application->settings->is_env_sorting_enabled;
        if ($sort) {
            $sorted_environment_variables = $this->application->environment_variables->sortBy('key');
            $sorted_environment_variables_preview = $this->application->environment_variables_preview->sortBy('key');
        } else {
            $sorted_environment_variables = $this->application->environment_variables->sortBy('id');
            $sorted_environment_variables_preview = $this->application->environment_variables_preview->sortBy('id');
        }
        if ($this->build_pack === 'dockercompose') {
            $sorted_environment_variables = $sorted_environment_variables->filter(function ($env) {
                return ! str($env->key)->startsWith('SERVICE_FQDN_') && ! str($env->key)->startsWith('SERVICE_URL_') && ! str($env->key)->startsWith('SERVICE_NAME_');
            });
            $sorted_environment_variables_preview = $sorted_environment_variables_preview->filter(function ($env) {
                return ! str($env->key)->startsWith('SERVICE_FQDN_') && ! str($env->key)->startsWith('SERVICE_URL_') && ! str($env->key)->startsWith('SERVICE_NAME_');
            });
        }
        $ports = $this->application->main_port();
        $saturn_envs = $this->generate_saturn_env_variables();
        $saturn_envs->each(function ($item, $key) use ($envs) {
            $envs->push($key.'='.$item);
        });
        if ($this->pull_request_id === 0) {
            $this->addRuntimeServiceVariables($envs);
            $this->addRuntimeUserVariables($envs, $sorted_environment_variables, $ports);
        } else {
            $this->addPreviewServiceVariables($envs);
            $this->addPreviewUserVariables($envs, $sorted_environment_variables_preview, $ports);
        }

        // Return the generated environment variables instead of storing them globally
        return $envs;
    }

    /**
     * Add SERVICE_* variables for regular deployments.
     */
    private function addRuntimeServiceVariables($envs): void
    {
        if ($this->build_pack !== 'dockercompose') {
            return;
        }

        $domains = collect(json_decode($this->application->docker_compose_domains)) ?? collect([]);

        // Generate SERVICE_FQDN & SERVICE_URL for dockercompose
        foreach ($domains as $forServiceName => $domain) {
            $parsedDomain = data_get($domain, 'domain');
            if (filled($parsedDomain)) {
                $parsedDomain = str($parsedDomain)->explode(',')->first();
                $saturnUrl = Url::fromString($parsedDomain);
                $saturnScheme = $saturnUrl->getScheme();
                $saturnFqdn = $saturnUrl->getHost();
                $saturnUrl = $saturnUrl->withScheme($saturnScheme)->withHost($saturnFqdn)->withPort(null);
                $envs->push('SERVICE_URL_'.str($forServiceName)->upper().'='.$saturnUrl->__toString());
                $envs->push('SERVICE_FQDN_'.str($forServiceName)->upper().'='.$saturnFqdn);
            }
        }

        // Generate SERVICE_NAME for dockercompose services from processed compose
        if ($this->application->settings->is_raw_compose_deployment_enabled) {
            $dockerCompose = Yaml::parse($this->application->docker_compose_raw);
        } else {
            $dockerCompose = Yaml::parse($this->application->docker_compose);
        }
        $services = data_get($dockerCompose, 'services', []);
        foreach ($services as $serviceName => $_) {
            $envs->push('SERVICE_NAME_'.str($serviceName)->upper().'='.$serviceName);
        }
    }

    /**
     * Add user-defined runtime variables for regular deployments.
     */
    private function addRuntimeUserVariables($envs, $sorted_environment_variables, $ports): void
    {
        // Filter runtime variables (only include variables that are available at runtime)
        $runtime_environment_variables = $sorted_environment_variables->filter(function ($env) {
            return $env->is_runtime;
        });

        // Sort runtime environment variables: those referencing SERVICE_ variables come after others
        $runtime_environment_variables = $runtime_environment_variables->sortBy(function ($env) {
            if (str($env->value)->startsWith('$SERVICE_') || str($env->value)->contains('${SERVICE_')) {
                return 2;
            }

            return 1;
        });

        foreach ($runtime_environment_variables as $env) {
            $envs->push($env->key.'='.$env->real_value);
        }

        // Check for PORT environment variable mismatch with ports_exposes
        if ($this->build_pack !== 'dockercompose') {
            $detectedPort = $this->application->detectPortFromEnvironment(false);
            if ($detectedPort && ! empty($ports) && ! in_array($detectedPort, $ports)) {
                $this->application_deployment_queue->addLogEntry(
                    "Warning: PORT environment variable ({$detectedPort}) does not match configured ports_exposes: ".implode(',', $ports).'. It could case "bad gateway" or "no server" errors. Check the "General" page to fix it.',
                    'stderr'
                );
            }
        }

        // Add PORT if not exists, use the first port as default
        if ($this->build_pack !== 'dockercompose') {
            if ($this->application->environment_variables->where('key', 'PORT')->isEmpty()) {
                $envs->push("PORT={$ports[0]}");
            }
        }
        // Add HOST if not exists
        if ($this->application->environment_variables->where('key', 'HOST')->isEmpty()) {
            $envs->push('HOST=0.0.0.0');
        }
    }

    /**
     * Add SERVICE_* variables for preview deployments.
     */
    private function addPreviewServiceVariables($envs): void
    {
        if ($this->build_pack !== 'dockercompose') {
            return;
        }

        $domains = collect(json_decode(data_get($this->preview, 'docker_compose_domains'))) ?? collect([]);

        // Generate SERVICE_FQDN & SERVICE_URL for dockercompose
        foreach ($domains as $forServiceName => $domain) {
            $parsedDomain = data_get($domain, 'domain');
            if (filled($parsedDomain)) {
                $parsedDomain = str($parsedDomain)->explode(',')->first();
                $saturnUrl = Url::fromString($parsedDomain);
                $saturnScheme = $saturnUrl->getScheme();
                $saturnFqdn = $saturnUrl->getHost();
                $saturnUrl = $saturnUrl->withScheme($saturnScheme)->withHost($saturnFqdn)->withPort(null);
                $envs->push('SERVICE_URL_'.str($forServiceName)->upper().'='.$saturnUrl->__toString());
                $envs->push('SERVICE_FQDN_'.str($forServiceName)->upper().'='.$saturnFqdn);
            }
        }

        // Generate SERVICE_NAME for dockercompose services
        $rawDockerCompose = Yaml::parse($this->application->docker_compose_raw);
        $rawServices = data_get($rawDockerCompose, 'services', []);
        foreach ($rawServices as $rawServiceName => $_) {
            $envs->push('SERVICE_NAME_'.str($rawServiceName)->upper().'='.addPreviewDeploymentSuffix($rawServiceName, $this->pull_request_id));
        }
    }

    /**
     * Add user-defined runtime variables for preview deployments.
     */
    private function addPreviewUserVariables($envs, $sorted_environment_variables_preview, $ports): void
    {
        // Filter runtime variables for preview (only include variables that are available at runtime)
        $runtime_environment_variables_preview = $sorted_environment_variables_preview->filter(function ($env) {
            return $env->is_runtime;
        });

        // Sort runtime environment variables: those referencing SERVICE_ variables come after others
        $runtime_environment_variables_preview = $runtime_environment_variables_preview->sortBy(function ($env) {
            if (str($env->value)->startsWith('$SERVICE_') || str($env->value)->contains('${SERVICE_')) {
                return 2;
            }

            return 1;
        });

        foreach ($runtime_environment_variables_preview as $env) {
            $envs->push($env->key.'='.$env->real_value);
        }
        // Add PORT if not exists, use the first port as default
        if ($this->build_pack !== 'dockercompose') {
            if ($this->application->environment_variables_preview->where('key', 'PORT')->isEmpty()) {
                $envs->push("PORT={$ports[0]}");
            }
        }
        // Add HOST if not exists
        if ($this->application->environment_variables_preview->where('key', 'HOST')->isEmpty()) {
            $envs->push('HOST=0.0.0.0');
        }
    }

    /**
     * Save runtime environment variables to .env file.
     */
    private function save_runtime_environment_variables()
    {
        // This method saves the .env file with ALL runtime variables
        // For builds, it should be called AFTER the build to include runtime-only variables

        // Generate runtime environment variables locally
        $environment_variables = $this->generate_runtime_environment_variables();

        // Handle empty environment variables
        if ($environment_variables->isEmpty()) {
            $this->handleEmptyRuntimeEnv();

            return;
        }

        // Write the environment variables to file
        $envs_base64 = base64_encode($environment_variables->implode("\n"));

        // Write .env file to workdir (for container runtime)
        $this->application_deployment_queue->addLogEntry('Creating .env file with runtime variables for container.', hidden: true);
        $this->execute_remote_command(
            [
                executeInDocker($this->deployment_uuid, "echo '$envs_base64' | base64 -d | tee $this->workdir/.env > /dev/null"),
            ],
            [
                executeInDocker($this->deployment_uuid, "cat $this->workdir/.env"),
                'hidden' => true,

            ]
        );

        // Write .env file to configuration directory
        if ($this->use_build_server) {
            $this->server = $this->original_server;
            $this->execute_remote_command(
                [
                    "echo '$envs_base64' | base64 -d | tee $this->configuration_dir/.env > /dev/null",
                ]
            );
            $this->server = $this->build_server;
        } else {
            $this->execute_remote_command(
                [
                    "echo '$envs_base64' | base64 -d | tee $this->configuration_dir/.env > /dev/null",
                ]
            );
        }
    }

    /**
     * Handle empty runtime environment variables case.
     */
    private function handleEmptyRuntimeEnv(): void
    {
        // For Docker Compose and Docker Image, we need to create an empty .env file
        // because we always reference it in the compose file
        if ($this->build_pack === 'dockercompose' || $this->build_pack === 'dockerimage') {
            $this->application_deployment_queue->addLogEntry('Creating empty .env file (no environment variables defined).');

            // Create empty .env file
            $this->execute_remote_command(
                [
                    executeInDocker($this->deployment_uuid, "touch $this->workdir/.env"),
                ]
            );

            // Also create in configuration directory
            if ($this->use_build_server) {
                $this->server = $this->original_server;
                $this->execute_remote_command(
                    [
                        "touch $this->configuration_dir/.env",
                    ]
                );
                $this->server = $this->build_server;
            } else {
                $this->execute_remote_command(
                    [
                        "touch $this->configuration_dir/.env",
                    ]
                );
            }
        } else {
            // For non-Docker Compose deployments, clean up any existing .env files
            if ($this->use_build_server) {
                $this->server = $this->original_server;
                $this->execute_remote_command(
                    [
                        'command' => "rm -f $this->configuration_dir/.env",
                        'hidden' => true,
                        'ignore_errors' => true,
                    ]
                );
                $this->server = $this->build_server;
                $this->execute_remote_command(
                    [
                        'command' => "rm -f $this->configuration_dir/.env",
                        'hidden' => true,
                        'ignore_errors' => true,
                    ]
                );
            } else {
                $this->execute_remote_command(
                    [
                        'command' => "rm -f $this->configuration_dir/.env",
                        'hidden' => true,
                        'ignore_errors' => true,
                    ]
                );
            }
        }
    }
}
