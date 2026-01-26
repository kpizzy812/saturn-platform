<?php

namespace App\Traits\Deployment;

use Spatie\Url\Url;
use Symfony\Component\Yaml\Yaml;

/**
 * Trait for generating build-time environment variables during deployment.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $pull_request_id, $preview
 * - $build_pack, $nixpacks_plan_json
 *
 * Required methods from parent class:
 * - generate_saturn_env_variables()
 */
trait HandlesBuildtimeEnvGeneration
{
    /**
     * Generate build-time environment variables.
     *
     * Priority order (lowest to highest):
     * 1. Nixpacks plan variables
     * 2. SATURN variables
     * 3. SERVICE_* variables for Docker Compose
     * 4. User-defined build-time variables (highest priority)
     *
     * @return \Illuminate\Support\Collection
     */
    private function generate_buildtime_environment_variables()
    {
        if (isDev()) {
            $this->application_deployment_queue->addLogEntry('[DEBUG] ========================================');
            $this->application_deployment_queue->addLogEntry('[DEBUG] Generating build-time environment variables');
            $this->application_deployment_queue->addLogEntry('[DEBUG] ========================================');
        }

        // Use associative array for automatic deduplication
        $envs_dict = [];

        // 1. Add nixpacks plan variables FIRST (lowest priority - can be overridden)
        if ($this->build_pack === 'nixpacks' &&
            isset($this->nixpacks_plan_json) &&
            $this->nixpacks_plan_json->isNotEmpty()) {

            $planVariables = data_get($this->nixpacks_plan_json, 'variables', []);

            if (! empty($planVariables)) {
                if (isDev()) {
                    $this->application_deployment_queue->addLogEntry('[DEBUG] Adding '.count($planVariables).' nixpacks plan variables to buildtime.env');
                }

                foreach ($planVariables as $key => $value) {
                    // Skip SATURN_* and SERVICE_* - they'll be added later with higher priority
                    if (str_starts_with($key, 'SATURN_') || str_starts_with($key, 'SERVICE_')) {
                        continue;
                    }

                    $escapedValue = escapeBashEnvValue($value);
                    $envs_dict[$key] = $escapedValue;

                    if (isDev()) {
                        $this->application_deployment_queue->addLogEntry("[DEBUG] Nixpacks var: {$key}={$escapedValue}");
                    }
                }
            }
        }

        // 2. Add SATURN variables (can override nixpacks, but shouldn't happen in practice)
        $saturn_envs = $this->generate_saturn_env_variables(forBuildTime: true);
        foreach ($saturn_envs as $key => $item) {
            $envs_dict[$key] = escapeBashEnvValue($item);
        }

        // 3. Add SERVICE_NAME, SERVICE_FQDN, SERVICE_URL variables for Docker Compose builds
        if ($this->build_pack === 'dockercompose') {
            $this->addDockerComposeServiceVariables($envs_dict);
        }

        // 4. Add user-defined build-time variables LAST (highest priority - can override everything)
        $this->addUserDefinedBuildtimeVariables($envs_dict);

        // Convert dictionary back to collection in KEY=VALUE format
        $envs = collect([]);
        foreach ($envs_dict as $key => $value) {
            $envs->push($key.'='.$value);
        }

        // Return the generated environment variables
        if (isDev()) {
            $this->application_deployment_queue->addLogEntry('[DEBUG] ========================================');
            $this->application_deployment_queue->addLogEntry("[DEBUG] Total build-time env variables: {$envs->count()}");
            $this->application_deployment_queue->addLogEntry('[DEBUG] ========================================');
        }

        return $envs;
    }

    /**
     * Add SERVICE_* variables for Docker Compose builds.
     */
    private function addDockerComposeServiceVariables(array &$envs_dict): void
    {
        if ($this->pull_request_id === 0) {
            // Generate SERVICE_NAME for dockercompose services from processed compose
            if ($this->application->settings->is_raw_compose_deployment_enabled) {
                $dockerCompose = Yaml::parse($this->application->docker_compose_raw);
            } else {
                $dockerCompose = Yaml::parse($this->application->docker_compose);
            }
            $services = data_get($dockerCompose, 'services', []);
            foreach ($services as $serviceName => $_) {
                $envs_dict['SERVICE_NAME_'.str($serviceName)->upper()] = escapeBashEnvValue($serviceName);
            }

            // Generate SERVICE_FQDN & SERVICE_URL for non-PR deployments
            $domains = collect(json_decode($this->application->docker_compose_domains)) ?? collect([]);
            foreach ($domains as $forServiceName => $domain) {
                $parsedDomain = data_get($domain, 'domain');
                if (filled($parsedDomain)) {
                    $parsedDomain = str($parsedDomain)->explode(',')->first();
                    $saturnUrl = Url::fromString($parsedDomain);
                    $saturnScheme = $saturnUrl->getScheme();
                    $saturnFqdn = $saturnUrl->getHost();
                    $saturnUrl = $saturnUrl->withScheme($saturnScheme)->withHost($saturnFqdn)->withPort(null);
                    $envs_dict['SERVICE_URL_'.str($forServiceName)->upper()] = escapeBashEnvValue($saturnUrl->__toString());
                    $envs_dict['SERVICE_FQDN_'.str($forServiceName)->upper()] = escapeBashEnvValue($saturnFqdn);
                }
            }
        } else {
            // Generate SERVICE_NAME for preview deployments
            $rawDockerCompose = Yaml::parse($this->application->docker_compose_raw);
            $rawServices = data_get($rawDockerCompose, 'services', []);
            foreach ($rawServices as $rawServiceName => $_) {
                $envs_dict['SERVICE_NAME_'.str($rawServiceName)->upper()] = escapeBashEnvValue(addPreviewDeploymentSuffix($rawServiceName, $this->pull_request_id));
            }

            // Generate SERVICE_FQDN & SERVICE_URL for preview deployments with PR-specific domains
            $domains = collect(json_decode(data_get($this->preview, 'docker_compose_domains'))) ?? collect([]);
            foreach ($domains as $forServiceName => $domain) {
                $parsedDomain = data_get($domain, 'domain');
                if (filled($parsedDomain)) {
                    $parsedDomain = str($parsedDomain)->explode(',')->first();
                    $saturnUrl = Url::fromString($parsedDomain);
                    $saturnScheme = $saturnUrl->getScheme();
                    $saturnFqdn = $saturnUrl->getHost();
                    $saturnUrl = $saturnUrl->withScheme($saturnScheme)->withHost($saturnFqdn)->withPort(null);
                    $envs_dict['SERVICE_URL_'.str($forServiceName)->upper()] = escapeBashEnvValue($saturnUrl->__toString());
                    $envs_dict['SERVICE_FQDN_'.str($forServiceName)->upper()] = escapeBashEnvValue($saturnFqdn);
                }
            }
        }
    }

    /**
     * Add user-defined build-time variables (highest priority).
     */
    private function addUserDefinedBuildtimeVariables(array &$envs_dict): void
    {
        if ($this->pull_request_id === 0) {
            $sorted_environment_variables = $this->application->environment_variables()
                ->where('is_buildtime', true)  // ONLY build-time variables
                ->orderBy($this->application->settings->is_env_sorting_enabled ? 'key' : 'id')
                ->get();

            // For Docker Compose, filter out SERVICE_FQDN and SERVICE_URL as we generate these
            if ($this->build_pack === 'dockercompose') {
                $sorted_environment_variables = $sorted_environment_variables->filter(function ($env) {
                    return ! str($env->key)->startsWith('SERVICE_FQDN_') && ! str($env->key)->startsWith('SERVICE_URL_');
                });
            }
        } else {
            $sorted_environment_variables = $this->application->environment_variables_preview()
                ->where('is_buildtime', true)  // ONLY build-time variables
                ->orderBy($this->application->settings->is_env_sorting_enabled ? 'key' : 'id')
                ->get();

            // For Docker Compose, filter out SERVICE_FQDN and SERVICE_URL as we generate these with PR-specific values
            if ($this->build_pack === 'dockercompose') {
                $sorted_environment_variables = $sorted_environment_variables->filter(function ($env) {
                    return ! str($env->key)->startsWith('SERVICE_FQDN_') && ! str($env->key)->startsWith('SERVICE_URL_');
                });
            }
        }

        foreach ($sorted_environment_variables as $env) {
            $this->processEnvVariable($env, $envs_dict);
        }
    }

    /**
     * Process a single environment variable and add to envs_dict.
     */
    private function processEnvVariable($env, array &$envs_dict): void
    {
        // For literal/multiline vars, real_value includes quotes that we need to remove
        if ($env->is_literal || $env->is_multiline) {
            // Strip outer quotes from real_value and apply proper bash escaping
            $value = trim($env->real_value, "'");
            $escapedValue = escapeBashEnvValue($value);

            if (isDev() && isset($envs_dict[$env->key])) {
                $this->application_deployment_queue->addLogEntry("[DEBUG] User override: {$env->key} (was: {$envs_dict[$env->key]}, now: {$escapedValue})");
            }

            $envs_dict[$env->key] = $escapedValue;

            if (isDev()) {
                $this->application_deployment_queue->addLogEntry("[DEBUG] Build-time env: {$env->key}");
                $this->application_deployment_queue->addLogEntry('[DEBUG]   Type: literal/multiline');
                $this->application_deployment_queue->addLogEntry("[DEBUG]   raw real_value: {$env->real_value}");
                $this->application_deployment_queue->addLogEntry("[DEBUG]   stripped value: {$value}");
                $this->application_deployment_queue->addLogEntry("[DEBUG]   final escaped: {$escapedValue}");
            }
        } else {
            // For normal vars, use double quotes to allow $VAR expansion
            $escapedValue = escapeBashDoubleQuoted($env->real_value);

            if (isDev() && isset($envs_dict[$env->key])) {
                $this->application_deployment_queue->addLogEntry("[DEBUG] User override: {$env->key} (was: {$envs_dict[$env->key]}, now: {$escapedValue})");
            }

            $envs_dict[$env->key] = $escapedValue;

            if (isDev()) {
                $this->application_deployment_queue->addLogEntry("[DEBUG] Build-time env: {$env->key}");
                $this->application_deployment_queue->addLogEntry('[DEBUG]   Type: normal (allows expansion)');
                $this->application_deployment_queue->addLogEntry("[DEBUG]   real_value: {$env->real_value}");
                $this->application_deployment_queue->addLogEntry("[DEBUG]   final escaped: {$escapedValue}");
            }
        }
    }
}
