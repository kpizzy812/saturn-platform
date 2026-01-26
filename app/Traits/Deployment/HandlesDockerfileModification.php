<?php

namespace App\Traits\Deployment;

/**
 * Trait for modifying Dockerfiles during deployment.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $deployment_uuid
 * - $workdir, $env_args, $saved_outputs, $dockerfile_location
 * - $build_secrets, $dockerBuildkitSupported, $saturn_variables
 * - $pull_request_id
 *
 * Required methods from parent class:
 * - execute_remote_command(), generate_env_variables()
 * - generate_secrets_hash(), findFromInstructionLines()
 */
trait HandlesDockerfileModification
{
    /**
     * Modify Dockerfiles in a Docker Compose project to inject ARG declarations.
     *
     * This ensures build-time variables are available during the build process.
     *
     * @param  array|string  $composeFile  The parsed compose file
     */
    private function modify_dockerfiles_for_compose($composeFile)
    {
        if ($this->application->build_pack !== 'dockercompose') {
            return;
        }

        // Skip ARG injection if disabled by user - preserves Docker build cache
        if ($this->application->settings->inject_build_args_to_dockerfile === false) {
            $this->application_deployment_queue->addLogEntry('Skipping Docker Compose Dockerfile ARG injection (disabled in settings).', hidden: true);

            return;
        }

        // Generate env variables if not already done
        // This populates $this->env_args with both user-defined and SATURN_* variables
        if (! $this->env_args || $this->env_args->isEmpty()) {
            $this->generate_env_variables();
        }

        $variables = $this->env_args;

        if ($variables->isEmpty()) {
            $this->application_deployment_queue->addLogEntry('No build-time variables to add to Dockerfiles.');

            return;
        }

        $services = data_get($composeFile, 'services', []);

        foreach ($services as $serviceName => $service) {
            $this->processServiceDockerfile($serviceName, $service, $variables);
        }
    }

    /**
     * Process a single service's Dockerfile for ARG injection.
     */
    private function processServiceDockerfile(string $serviceName, array $service, $variables): void
    {
        if (! isset($service['build'])) {
            return;
        }

        $context = '.';
        $dockerfile = 'Dockerfile';

        if (is_string($service['build'])) {
            $context = $service['build'];
        } elseif (is_array($service['build'])) {
            $context = data_get($service['build'], 'context', '.');
            $dockerfile = data_get($service['build'], 'dockerfile', 'Dockerfile');
        }

        $dockerfilePath = rtrim($context, '/').'/'.ltrim($dockerfile, '/');
        if (str_starts_with($dockerfilePath, './')) {
            $dockerfilePath = substr($dockerfilePath, 2);
        }
        if (str_starts_with($dockerfilePath, '/')) {
            $dockerfilePath = substr($dockerfilePath, 1);
        }

        // Check if Dockerfile exists
        $this->execute_remote_command([
            executeInDocker($this->deployment_uuid, "test -f {$this->workdir}/{$dockerfilePath} && echo 'exists' || echo 'not found'"),
            'hidden' => true,
            'save' => 'dockerfile_check_'.$serviceName,
        ]);

        if (str($this->saved_outputs->get('dockerfile_check_'.$serviceName))->trim()->toString() !== 'exists') {
            $this->application_deployment_queue->addLogEntry("Dockerfile not found for service {$serviceName} at {$dockerfilePath}, skipping ARG injection.");

            return;
        }

        // Read Dockerfile content
        $this->execute_remote_command([
            executeInDocker($this->deployment_uuid, "cat {$this->workdir}/{$dockerfilePath}"),
            'hidden' => true,
            'save' => 'dockerfile_content_'.$serviceName,
        ]);

        $dockerfileContent = $this->saved_outputs->get('dockerfile_content_'.$serviceName);
        if (! $dockerfileContent) {
            return;
        }

        $dockerfile_lines = collect(str($dockerfileContent)->trim()->explode("\n"));

        // Find FROM indices
        $fromIndices = [];
        $dockerfile_lines->each(function ($line, $index) use (&$fromIndices) {
            if (str($line)->trim()->startsWith('FROM')) {
                $fromIndices[] = $index;
            }
        });

        if (empty($fromIndices)) {
            $this->application_deployment_queue->addLogEntry("No FROM instruction found in Dockerfile for service {$serviceName}, skipping.");

            return;
        }

        $isMultiStage = count($fromIndices) > 1;

        // Build ARG declarations
        $argsToAdd = collect([]);
        foreach ($variables as $key => $value) {
            $argsToAdd->push("ARG {$key}");
        }

        if ($argsToAdd->isEmpty()) {
            $this->application_deployment_queue->addLogEntry("Service {$serviceName}: No build-time variables to add.");

            return;
        }

        // Development logging
        if (isDev()) {
            $this->application_deployment_queue->addLogEntry('[DEBUG] ========================================');
            $this->application_deployment_queue->addLogEntry("[DEBUG] Docker Compose ARG Injection - Service: {$serviceName}");
            $this->application_deployment_queue->addLogEntry('[DEBUG] ========================================');
            $this->application_deployment_queue->addLogEntry('[DEBUG] ARGs to inject: '.$argsToAdd->count());
            foreach ($argsToAdd as $arg) {
                $argKey = str($arg)->after('ARG ')->toString();
                $this->application_deployment_queue->addLogEntry("[DEBUG]   - {$argKey}");
            }
        }

        // Inject ARGs into each stage
        $totalAdded = $this->injectArgsIntoDockerfile($dockerfile_lines, $fromIndices, $argsToAdd);

        if ($totalAdded > 0) {
            $dockerfile_base64 = base64_encode($dockerfile_lines->implode("\n"));
            $this->execute_remote_command([
                executeInDocker($this->deployment_uuid, "echo '{$dockerfile_base64}' | base64 -d | tee {$this->workdir}/{$dockerfilePath} > /dev/null"),
                'hidden' => true,
            ]);

            $stageInfo = $isMultiStage ? ' (multi-stage build, added to '.count($fromIndices).' stages)' : '';
            $this->application_deployment_queue->addLogEntry("Added {$totalAdded} ARG declarations to Dockerfile for service {$serviceName}{$stageInfo}.");
        } else {
            $this->application_deployment_queue->addLogEntry("Service {$serviceName}: All required ARG declarations already exist.");
        }

        // Handle build secrets
        if ($this->application->settings->use_build_secrets && $this->dockerBuildkitSupported && ! empty($this->build_secrets)) {
            $fullDockerfilePath = "{$this->workdir}/{$dockerfilePath}";
            $this->modify_dockerfile_for_secrets($fullDockerfilePath);
            $this->application_deployment_queue->addLogEntry("Modified Dockerfile for service {$serviceName} to use build secrets.");
        }
    }

    /**
     * Inject ARG declarations into Dockerfile after each FROM instruction.
     *
     * @return int Total number of ARGs added
     */
    private function injectArgsIntoDockerfile($dockerfile_lines, array $fromIndices, $argsToAdd): int
    {
        $totalAdded = 0;
        $offset = 0;

        foreach ($fromIndices as $stageIndex => $fromIndex) {
            $adjustedIndex = $fromIndex + $offset;

            $stageStart = $adjustedIndex + 1;
            $stageEnd = isset($fromIndices[$stageIndex + 1])
                ? $fromIndices[$stageIndex + 1] + $offset
                : $dockerfile_lines->count();

            // Find existing ARGs in this stage
            $existingStageArgs = collect([]);
            for ($i = $stageStart; $i < $stageEnd; $i++) {
                $line = $dockerfile_lines->get($i);
                if (! $line || ! str($line)->trim()->startsWith('ARG')) {
                    break;
                }
                $parts = explode(' ', trim($line), 2);
                if (count($parts) >= 2) {
                    $argPart = $parts[1];
                    $keyValue = explode('=', $argPart, 2);
                    $existingStageArgs->push($keyValue[0]);
                }
            }

            // Filter out existing ARGs
            $stageArgsToAdd = $argsToAdd->filter(function ($arg) use ($existingStageArgs) {
                $key = str($arg)->after('ARG ')->trim()->toString();

                return ! $existingStageArgs->contains($key);
            });

            if ($stageArgsToAdd->isNotEmpty()) {
                $dockerfile_lines->splice($adjustedIndex + 1, 0, $stageArgsToAdd->toArray());
                $totalAdded += $stageArgsToAdd->count();
                $offset += $stageArgsToAdd->count();
            }
        }

        return $totalAdded;
    }

    /**
     * Add build environment variables to Dockerfile as ARG declarations.
     * This is used for non-Docker Compose builds when BuildKit is not available.
     */
    private function add_build_env_variables_to_dockerfile()
    {
        if ($this->dockerBuildkitSupported) {
            // We dont need to add build secrets to dockerfile for buildkit, as we already added them with --secret flag in function generate_docker_env_flags_for_secrets
            return;
        }

        // Skip ARG injection if disabled by user - preserves Docker build cache
        if ($this->application->settings->inject_build_args_to_dockerfile === false) {
            $this->application_deployment_queue->addLogEntry('Skipping Dockerfile ARG injection (disabled in settings).', hidden: true);

            return;
        }

        $this->execute_remote_command([
            executeInDocker($this->deployment_uuid, "cat {$this->workdir}{$this->dockerfile_location}"),
            'hidden' => true,
            'save' => 'dockerfile',
            'ignore_errors' => true,
        ]);
        $dockerfile = collect(str($this->saved_outputs->get('dockerfile'))->trim()->explode("\n"));

        // Find all FROM instruction positions
        $fromLines = $this->findFromInstructionLines($dockerfile);

        // If no FROM instructions found, skip ARG insertion
        if (empty($fromLines)) {
            return;
        }

        // Collect all ARG statements to insert
        $argsToInsert = collect();

        if ($this->pull_request_id === 0) {
            // Only add environment variables that are available during build
            $envs = $this->application->environment_variables()
                ->where('key', 'not like', 'NIXPACKS_%')
                ->where('is_buildtime', true)
                ->get();
            foreach ($envs as $env) {
                if (data_get($env, 'is_multiline') === true) {
                    $argsToInsert->push("ARG {$env->key}");
                } else {
                    $argsToInsert->push("ARG {$env->key}={$env->real_value}");
                }
            }
            // Add Saturn Platform variables as ARGs
            if ($this->saturn_variables) {
                $saturn_vars = collect(explode(' ', trim($this->saturn_variables)))
                    ->filter()
                    ->map(function ($var) {
                        return "ARG {$var}";
                    });
                $argsToInsert = $argsToInsert->merge($saturn_vars);
            }
        } else {
            // Only add preview environment variables that are available during build
            $envs = $this->application->environment_variables_preview()
                ->where('key', 'not like', 'NIXPACKS_%')
                ->where('is_buildtime', true)
                ->get();
            foreach ($envs as $env) {
                if (data_get($env, 'is_multiline') === true) {
                    $argsToInsert->push("ARG {$env->key}");
                } else {
                    $argsToInsert->push("ARG {$env->key}={$env->real_value}");
                }
            }
            // Add Saturn Platform variables as ARGs
            if ($this->saturn_variables) {
                $saturn_vars = collect(explode(' ', trim($this->saturn_variables)))
                    ->filter()
                    ->map(function ($var) {
                        return "ARG {$var}";
                    });
                $argsToInsert = $argsToInsert->merge($saturn_vars);
            }
        }

        // Development logging to show what ARGs are being injected
        if (isDev()) {
            $this->application_deployment_queue->addLogEntry('[DEBUG] ========================================');
            $this->application_deployment_queue->addLogEntry('[DEBUG] Dockerfile ARG Injection');
            $this->application_deployment_queue->addLogEntry('[DEBUG] ========================================');
            $this->application_deployment_queue->addLogEntry('[DEBUG] ARGs to inject: '.$argsToInsert->count());
            foreach ($argsToInsert as $arg) {
                // Only show ARG key, not the value (for security)
                $argKey = str($arg)->after('ARG ')->before('=')->toString();
                $this->application_deployment_queue->addLogEntry("[DEBUG]   - {$argKey}");
            }
        }

        // Insert ARGs after each FROM instruction (in reverse order to maintain correct line numbers)
        if ($argsToInsert->isNotEmpty()) {
            foreach (array_reverse($fromLines) as $fromLineIndex) {
                // Insert all ARGs after this FROM instruction
                foreach ($argsToInsert->reverse() as $arg) {
                    $dockerfile->splice($fromLineIndex + 1, 0, [$arg]);
                }
            }
            $envs_mapped = $envs->mapWithKeys(function ($env) {
                return [$env->key => $env->real_value];
            });
            $secrets_hash = $this->generate_secrets_hash($envs_mapped);
            $argsToInsert->push("ARG SATURN_BUILD_SECRETS_HASH={$secrets_hash}");
        }

        $dockerfile_base64 = base64_encode($dockerfile->implode("\n"));
        $this->application_deployment_queue->addLogEntry('Final Dockerfile:', type: 'info', hidden: true);
        $this->execute_remote_command(
            [
                executeInDocker($this->deployment_uuid, "echo '{$dockerfile_base64}' | base64 -d | tee {$this->workdir}{$this->dockerfile_location} > /dev/null"),
                'hidden' => true,
            ],
            [
                executeInDocker($this->deployment_uuid, "cat {$this->workdir}{$this->dockerfile_location}"),
                'hidden' => true,
                'ignore_errors' => true,
            ]);
    }

    /**
     * Modify Dockerfile to mount build secrets for RUN commands.
     * This is used when BuildKit is available and build secrets are enabled.
     */
    private function modify_dockerfile_for_secrets($dockerfile_path)
    {
        // Only process if build secrets are enabled and we have secrets to mount
        if (! $this->application->settings->use_build_secrets || empty($this->build_secrets)) {
            return;
        }

        // Read the Dockerfile
        $this->execute_remote_command([
            executeInDocker($this->deployment_uuid, "cat {$dockerfile_path}"),
            'hidden' => true,
            'save' => 'dockerfile_content',
        ]);

        $dockerfile = str($this->saved_outputs->get('dockerfile_content'))->trim()->explode("\n");

        // Add BuildKit syntax directive if not present
        if (! str_starts_with($dockerfile->first(), '# syntax=')) {
            $dockerfile->prepend('# syntax=docker/dockerfile:1');
        }

        // Generate env variables if not already done
        // This populates $this->env_args with both user-defined and SATURN_* variables
        if (! $this->env_args || $this->env_args->isEmpty()) {
            $this->generate_env_variables();
        }

        $variables = $this->env_args;
        if ($variables->isEmpty()) {
            return;
        }

        // Generate mount strings for all secrets
        $mountStrings = $variables->map(fn ($value, $key) => "--mount=type=secret,id={$key},env={$key}")->implode(' ');

        // Add mount for the secrets hash to ensure cache invalidation
        $mountStrings .= ' --mount=type=secret,id=SATURN_BUILD_SECRETS_HASH,env=SATURN_BUILD_SECRETS_HASH';

        $modified = false;
        $dockerfile = $dockerfile->map(function ($line) use ($mountStrings, &$modified) {
            $trimmed = ltrim($line);

            // Skip lines that already have secret mounts or are not RUN commands
            if (str_contains($line, '--mount=type=secret') || ! str_starts_with($trimmed, 'RUN')) {
                return $line;
            }

            // Add mount strings to RUN command
            $originalCommand = trim(substr($trimmed, 3));
            $modified = true;

            return "RUN {$mountStrings} {$originalCommand}";
        });

        if ($modified) {
            // Write the modified Dockerfile back
            $dockerfile_base64 = base64_encode($dockerfile->implode("\n"));
            $this->execute_remote_command([
                executeInDocker($this->deployment_uuid, "echo '{$dockerfile_base64}' | base64 -d | tee {$dockerfile_path} > /dev/null"),
                'hidden' => true,
            ]);
        }
    }
}
