<?php

namespace App\Traits\Deployment;

use Illuminate\Support\Collection;
use Spatie\Url\Url;

/**
 * Trait for generating Saturn Platform environment variables.
 *
 * Required properties from parent class:
 * - $application, $pull_request_id, $preview, $commit, $branch
 * - $container_name, $saturn_variables
 *
 * Required methods from parent class:
 * - None (self-contained)
 */
trait HandlesSaturnEnvVariables
{
    /**
     * Set Saturn Platform variables for docker compose environment.
     * These are used as environment variables prefix in docker compose commands.
     */
    private function set_saturn_variables()
    {
        $this->saturn_variables = '';

        // Only include SOURCE_COMMIT in build context if enabled in settings
        if ($this->application->settings->include_source_commit_in_build) {
            $this->saturn_variables .= "SOURCE_COMMIT={$this->commit} ";
        }
        if ($this->pull_request_id === 0) {
            $fqdn = $this->application->fqdn;
        } else {
            $fqdn = $this->preview->fqdn;
        }
        if (isset($fqdn)) {
            $url = Url::fromString($fqdn);
            $fqdn = $url->getHost();
            $url = $url->withHost($fqdn)->withPort(null)->__toString();
            if ((int) $this->application->compose_parsing_version >= 3) {
                $this->saturn_variables .= "SATURN_URL={$url} ";
                $this->saturn_variables .= "SATURN_FQDN={$fqdn} ";
            } else {
                $this->saturn_variables .= "SATURN_URL={$fqdn} ";
                $this->saturn_variables .= "SATURN_FQDN={$url} ";
            }
        }
        if (isset($this->application->git_branch)) {
            $this->saturn_variables .= "SATURN_BRANCH={$this->application->git_branch} ";
        }
        $this->saturn_variables .= "SATURN_RESOURCE_UUID={$this->application->uuid} ";
    }

    /**
     * Generate Saturn Platform environment variables collection.
     *
     * @param  bool  $forBuildTime  Whether generating for build-time (excludes volatile vars like CONTAINER_NAME)
     * @return Collection Key-value collection of Saturn environment variables
     */
    private function generate_saturn_env_variables(bool $forBuildTime = false): Collection
    {
        $saturn_envs = collect([]);
        $local_branch = $this->branch;
        if ($this->pull_request_id !== 0) {
            $this->generatePreviewSaturnEnvs($saturn_envs, $local_branch, $forBuildTime);
        } else {
            $this->generateProductionSaturnEnvs($saturn_envs, $local_branch, $forBuildTime);
        }

        return $saturn_envs;
    }

    /**
     * Generate Saturn env variables for preview deployments.
     */
    private function generatePreviewSaturnEnvs(Collection $saturn_envs, string $local_branch, bool $forBuildTime): void
    {
        // Only add SOURCE_COMMIT for runtime OR when explicitly enabled for build-time
        // SOURCE_COMMIT changes with each commit and breaks Docker cache if included in build
        if (! $forBuildTime || $this->application->settings->include_source_commit_in_build) {
            if ($this->application->environment_variables_preview->where('key', 'SOURCE_COMMIT')->isEmpty()) {
                $saturn_envs->put('SOURCE_COMMIT', $this->commit);
            }
        }
        if ($this->application->environment_variables_preview->where('key', 'SATURN_FQDN')->isEmpty()) {
            if ((int) $this->application->compose_parsing_version >= 3) {
                $saturn_envs->put('SATURN_URL', $this->preview->fqdn);
            } else {
                $saturn_envs->put('SATURN_FQDN', $this->preview->fqdn);
            }
        }
        if ($this->application->environment_variables_preview->where('key', 'SATURN_URL')->isEmpty()) {
            $url = str($this->preview->fqdn)->replace('http://', '')->replace('https://', '');
            if ((int) $this->application->compose_parsing_version >= 3) {
                $saturn_envs->put('SATURN_FQDN', $url);
            } else {
                $saturn_envs->put('SATURN_URL', $url);
            }
        }
        if ($this->application->build_pack !== 'dockercompose' || $this->application->compose_parsing_version === '1' || $this->application->compose_parsing_version === '2') {
            if ($this->application->environment_variables_preview->where('key', 'SATURN_BRANCH')->isEmpty()) {
                $saturn_envs->put('SATURN_BRANCH', $local_branch);
            }
            if ($this->application->environment_variables_preview->where('key', 'SATURN_RESOURCE_UUID')->isEmpty()) {
                $saturn_envs->put('SATURN_RESOURCE_UUID', $this->application->uuid);
            }
            // Only add SATURN_CONTAINER_NAME for runtime (not build-time) - it changes every deployment and breaks Docker cache
            if (! $forBuildTime) {
                if ($this->application->environment_variables_preview->where('key', 'SATURN_CONTAINER_NAME')->isEmpty()) {
                    $saturn_envs->put('SATURN_CONTAINER_NAME', $this->container_name);
                }
            }
        }

        add_saturn_default_environment_variables($this->application, $saturn_envs, $this->application->environment_variables_preview);
    }

    /**
     * Generate Saturn env variables for production deployments.
     */
    private function generateProductionSaturnEnvs(Collection $saturn_envs, string $local_branch, bool $forBuildTime): void
    {
        // Only add SOURCE_COMMIT for runtime OR when explicitly enabled for build-time
        // SOURCE_COMMIT changes with each commit and breaks Docker cache if included in build
        if (! $forBuildTime || $this->application->settings->include_source_commit_in_build) {
            if ($this->application->environment_variables->where('key', 'SOURCE_COMMIT')->isEmpty()) {
                $saturn_envs->put('SOURCE_COMMIT', $this->commit);
            }
        }
        if ($this->application->environment_variables->where('key', 'SATURN_FQDN')->isEmpty()) {
            if ((int) $this->application->compose_parsing_version >= 3) {
                $saturn_envs->put('SATURN_URL', $this->application->fqdn);
            } else {
                $saturn_envs->put('SATURN_FQDN', $this->application->fqdn);
            }
        }
        if ($this->application->environment_variables->where('key', 'SATURN_URL')->isEmpty()) {
            $url = str($this->application->fqdn)->replace('http://', '')->replace('https://', '');
            if ((int) $this->application->compose_parsing_version >= 3) {
                $saturn_envs->put('SATURN_FQDN', $url);
            } else {
                $saturn_envs->put('SATURN_URL', $url);
            }
        }
        if ($this->application->build_pack !== 'dockercompose' || $this->application->compose_parsing_version === '1' || $this->application->compose_parsing_version === '2') {
            if ($this->application->environment_variables->where('key', 'SATURN_BRANCH')->isEmpty()) {
                $saturn_envs->put('SATURN_BRANCH', $local_branch);
            }
            if ($this->application->environment_variables->where('key', 'SATURN_RESOURCE_UUID')->isEmpty()) {
                $saturn_envs->put('SATURN_RESOURCE_UUID', $this->application->uuid);
            }
            // Only add SATURN_CONTAINER_NAME for runtime (not build-time) - it changes every deployment and breaks Docker cache
            if (! $forBuildTime) {
                if ($this->application->environment_variables->where('key', 'SATURN_CONTAINER_NAME')->isEmpty()) {
                    $saturn_envs->put('SATURN_CONTAINER_NAME', $this->container_name);
                }
            }
        }

        add_saturn_default_environment_variables($this->application, $saturn_envs, $this->application->environment_variables);
    }
}
