<?php

namespace App\Traits\Deployment;

use App\Exceptions\DeploymentException;
use App\Models\EnvironmentVariable;

/**
 * Trait for Nixpacks buildpack deployment operations.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $server, $deployment_uuid
 * - $use_build_server, $build_server, $original_server
 * - $customRepository, $force_rebuild, $pull_request_id
 * - $workdir, $docker_compose_location, $saved_outputs
 * - $nixpacks_type, $nixpacks_plan, $nixpacks_plan_json, $env_nixpacks_args, $env_args
 *
 * Required methods from parent class:
 * - execute_remote_command(), checkForCancellation()
 * - prepare_builder_image(), check_git_if_build_needed(), generate_image_names()
 * - check_image_locally_or_remotely(), should_skip_build()
 * - clone_repository(), cleanup_git(), generate_compose_file()
 * - save_buildtime_environment_variables(), generate_build_env_variables()
 * - build_image(), save_runtime_environment_variables()
 * - push_to_docker_registry(), rolling_update()
 * - generate_env_variables(), generate_saturn_env_variables()
 */
trait HandlesNixpacksBuildpack
{
    /**
     * Deploy using Nixpacks buildpack.
     */
    private function deploy_nixpacks_buildpack()
    {
        if ($this->use_build_server) {
            $this->server = $this->build_server;
        }
        $this->application_deployment_queue->addLogEntry("Starting deployment of {$this->customRepository}:{$this->application->git_branch} to {$this->server->name}.");
        $this->prepare_builder_image();
        $this->check_git_if_build_needed();
        $this->generate_image_names();
        if (! $this->force_rebuild) {
            $this->check_image_locally_or_remotely();
            if ($this->should_skip_build()) {
                return;
            }
        }
        $this->clone_repository();
        $this->cleanup_git();
        $this->generate_nixpacks_confs();
        $this->autoDetectPortFromNixpacks();
        $this->generate_compose_file();

        // Save build-time .env file BEFORE the build for Nixpacks
        $this->save_buildtime_environment_variables();

        $this->generate_build_env_variables();
        $this->build_image();

        // For Nixpacks, save runtime environment variables AFTER the build
        // This overwrites the build-time .env with ALL variables (build-time + runtime)
        $this->save_runtime_environment_variables();
        $this->push_to_docker_registry();
        $this->rolling_update();
    }

    /**
     * Auto-detect port from Nixpacks plan or environment variables.
     */
    private function autoDetectPortFromNixpacks(): void
    {
        // Skip if user has explicitly set a port (not empty and not default 80)
        $currentPort = $this->application->ports_exposes;
        if (! empty($currentPort) && $currentPort !== '80') {
            return;
        }

        // First, check PORT variable from environment
        $envPort = $this->application->detectPortFromEnvironment($this->pull_request_id !== 0);
        if ($envPort) {
            $this->application->update(['ports_exposes' => (string) $envPort]);
            $this->application_deployment_queue->addLogEntry("Auto-detected port from environment: {$envPort}");

            return;
        }

        // Next, try to get PORT from Nixpacks plan
        if ($this->nixpacks_plan_json) {
            $portFromPlan = data_get($this->nixpacks_plan_json, 'variables.PORT');
            if (is_numeric($portFromPlan)) {
                $this->application->update(['ports_exposes' => (string) $portFromPlan]);
                $this->application_deployment_queue->addLogEntry("Auto-detected port from Nixpacks plan: {$portFromPlan}");

                return;
            }
        }
    }

    /**
     * Fine-tune Elixir deployments with required environment variables.
     */
    private function elixir_finetunes()
    {
        if ($this->pull_request_id === 0) {
            $envType = 'environment_variables';
        } else {
            $envType = 'environment_variables_preview';
        }
        $mix_env = $this->application->{$envType}->where('key', 'MIX_ENV')->first();
        if (! $mix_env) {
            $this->application_deployment_queue->addLogEntry('MIX_ENV environment variable not found.', type: 'error');
            $this->application_deployment_queue->addLogEntry('Please add MIX_ENV environment variable and set it to be build time variable if you facing any issues with the deployment.', type: 'error');
        }
        $secret_key_base = $this->application->{$envType}->where('key', 'SECRET_KEY_BASE')->first();
        if (! $secret_key_base) {
            $this->application_deployment_queue->addLogEntry('SECRET_KEY_BASE environment variable not found.', type: 'error');
            $this->application_deployment_queue->addLogEntry('Please add SECRET_KEY_BASE environment variable and set it to be build time variable if you facing any issues with the deployment.', type: 'error');
        }
        $database_url = $this->application->{$envType}->where('key', 'DATABASE_URL')->first();
        if (! $database_url) {
            $this->application_deployment_queue->addLogEntry('DATABASE_URL environment variable not found.', type: 'error');
            $this->application_deployment_queue->addLogEntry('Please add DATABASE_URL environment variable and set it to be build time variable if you facing any issues with the deployment.', type: 'error');
        }
    }

    /**
     * Fine-tune Laravel deployments with PHP configuration.
     */
    private function laravel_finetunes()
    {
        if ($this->pull_request_id === 0) {
            $envType = 'environment_variables';
        } else {
            $envType = 'environment_variables_preview';
        }
        $nixpacks_php_fallback_path = $this->application->{$envType}->where('key', 'NIXPACKS_PHP_FALLBACK_PATH')->first();
        $nixpacks_php_root_dir = $this->application->{$envType}->where('key', 'NIXPACKS_PHP_ROOT_DIR')->first();

        if (! $nixpacks_php_fallback_path) {
            $nixpacks_php_fallback_path = new EnvironmentVariable;
            $nixpacks_php_fallback_path->key = 'NIXPACKS_PHP_FALLBACK_PATH';
            $nixpacks_php_fallback_path->value = '/index.php';
            $nixpacks_php_fallback_path->resourceable_id = $this->application->id;
            $nixpacks_php_fallback_path->resourceable_type = 'App\Models\Application';
            $nixpacks_php_fallback_path->save();
        }
        if (! $nixpacks_php_root_dir) {
            $nixpacks_php_root_dir = new EnvironmentVariable;
            $nixpacks_php_root_dir->key = 'NIXPACKS_PHP_ROOT_DIR';
            $nixpacks_php_root_dir->value = '/app/public';
            $nixpacks_php_root_dir->resourceable_id = $this->application->id;
            $nixpacks_php_root_dir->resourceable_type = 'App\Models\Application';
            $nixpacks_php_root_dir->save();
        }

        return [$nixpacks_php_fallback_path, $nixpacks_php_root_dir];
    }

    /**
     * Generate Nixpacks configuration and plan.
     */
    private function generate_nixpacks_confs()
    {
        // First, detect application type to know if we need Node.js version auto-detection
        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, "nixpacks detect {$this->workdir}"), 'save' => 'nixpacks_type', 'hidden' => true],
        );

        $this->nixpacks_type = $this->saved_outputs->get('nixpacks_type', '');

        // For Node.js apps, try to auto-detect version before generating plan
        // Auto-detection from .nvmrc/package.json has priority over default env var
        $autoDetectedNodeVersion = null;
        $this->requiredNodeVersion = null; // Store for later Dockerfile patching
        if ($this->nixpacks_type === 'node') {
            $autoDetectedNodeVersion = $this->autoDetectNodeVersion();
            if ($autoDetectedNodeVersion) {
                $this->application_deployment_queue->addLogEntry("Auto-detected Node.js version from project: {$autoDetectedNodeVersion}");

                // Check if this version needs custom handling
                $customVersion = $this->needsCustomNodeVersion($autoDetectedNodeVersion);
                if ($customVersion) {
                    $this->requiredNodeVersion = $customVersion;
                    $this->application_deployment_queue->addLogEntry("Note: Nixpacks provides an older Node.js version than required ({$customVersion}).");
                    $this->application_deployment_queue->addLogEntry("Saturn will automatically install Node.js {$customVersion} in the container.");
                    // Use major version for Nixpacks, we'll patch the Dockerfile later
                    preg_match('/^(\d+)/', $autoDetectedNodeVersion, $matches);
                    $autoDetectedNodeVersion = $matches[1] ?? $autoDetectedNodeVersion;
                }
            } else {
                // Check if user has explicitly set a version
                $explicitVersion = $this->getExplicitNodeVersion();
                if ($explicitVersion) {
                    $this->application_deployment_queue->addLogEntry("Using NIXPACKS_NODE_VERSION from environment: {$explicitVersion}");
                }
            }

            // Warn if no start script found in package.json
            $this->warnIfNoStartScript();
        }

        $nixpacks_command = $this->nixpacks_build_cmd($autoDetectedNodeVersion);
        $this->application_deployment_queue->addLogEntry("Generating nixpacks configuration with: $nixpacks_command");

        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, $nixpacks_command), 'save' => 'nixpacks_plan', 'hidden' => true],
        );
        if (str($this->nixpacks_type)->isEmpty()) {
            throw new DeploymentException('Nixpacks failed to detect the application type. Please check the documentation of Nixpacks: https://nixpacks.com/docs/providers');
        }

        if ($this->saved_outputs->get('nixpacks_plan')) {
            $this->nixpacks_plan = $this->saved_outputs->get('nixpacks_plan');
            if ($this->nixpacks_plan) {
                $this->application_deployment_queue->addLogEntry("Found application type: {$this->nixpacks_type}.");
                $this->application_deployment_queue->addLogEntry("If you need further customization, please check the documentation of Nixpacks: https://nixpacks.com/docs/providers/{$this->nixpacks_type}");
                $parsed = json_decode($this->nixpacks_plan, true);

                // Do any modifications here
                // We need to generate envs here because nixpacks need to know to generate a proper Dockerfile
                $this->generate_env_variables();
                $merged_envs = collect(data_get($parsed, 'variables', []))->merge($this->env_args);
                $aptPkgs = data_get($parsed, 'phases.setup.aptPkgs', []);
                if (count($aptPkgs) === 0) {
                    $aptPkgs = ['curl', 'wget'];
                    data_set($parsed, 'phases.setup.aptPkgs', ['curl', 'wget']);
                } else {
                    if (! in_array('curl', $aptPkgs)) {
                        $aptPkgs[] = 'curl';
                    }
                    if (! in_array('wget', $aptPkgs)) {
                        $aptPkgs[] = 'wget';
                    }
                    data_set($parsed, 'phases.setup.aptPkgs', $aptPkgs);
                }
                data_set($parsed, 'variables', $merged_envs->toArray());
                $is_laravel = data_get($parsed, 'variables.IS_LARAVEL', false);
                if ($is_laravel) {
                    $variables = $this->laravel_finetunes();
                    data_set($parsed, 'variables.NIXPACKS_PHP_FALLBACK_PATH', $variables[0]->value);
                    data_set($parsed, 'variables.NIXPACKS_PHP_ROOT_DIR', $variables[1]->value);
                }
                if ($this->nixpacks_type === 'elixir') {
                    $this->elixir_finetunes();
                }
                if ($this->nixpacks_type === 'node') {
                    // Check if NIXPACKS_NODE_VERSION is set (either explicitly or auto-detected)
                    $variables = data_get($parsed, 'variables', []);
                    if (! isset($variables['NIXPACKS_NODE_VERSION'])) {
                        $this->application_deployment_queue->addLogEntry('----------------------------------------');
                        $this->application_deployment_queue->addLogEntry('⚠️ NIXPACKS_NODE_VERSION not set and could not auto-detect from .nvmrc or package.json.');
                        $this->application_deployment_queue->addLogEntry('Nixpacks will use Node.js 18 by default, which is EOL.');
                        $this->application_deployment_queue->addLogEntry('You can specify version by: 1) Adding .nvmrc file, 2) Setting engines.node in package.json, or 3) Setting NIXPACKS_NODE_VERSION environment variable.');
                    }
                }
                $this->nixpacks_plan = json_encode($parsed, JSON_PRETTY_PRINT);
                $this->nixpacks_plan_json = collect($parsed);
                $this->application_deployment_queue->addLogEntry("Final Nixpacks plan: {$this->nixpacks_plan}", hidden: true);
                if ($this->nixpacks_type === 'rust') {
                    // temporary: disable healthcheck for rust because the start phase does not have curl/wget
                    $this->application->health_check_enabled = false;
                    $this->application->save();
                }
            }
        }
    }

    /**
     * Build the Nixpacks command with options.
     *
     * @param  string|null  $autoDetectedNodeVersion  Auto-detected Node.js version to inject
     */
    private function nixpacks_build_cmd(?string $autoDetectedNodeVersion = null)
    {
        $this->generate_nixpacks_env_variables();

        // Add auto-detected Node version LAST so it takes priority over any env var
        // This ensures .nvmrc/package.json versions override NIXPACKS_NODE_VERSION env var
        $extraEnv = '';
        if ($autoDetectedNodeVersion) {
            $extraEnv = " --env NIXPACKS_NODE_VERSION={$autoDetectedNodeVersion}";
        }

        $nixpacks_command = "nixpacks plan -f json {$this->env_nixpacks_args}{$extraEnv}";
        if ($this->application->build_command) {
            $nixpacks_command .= " --build-cmd \"{$this->application->build_command}\"";
        }
        if ($this->application->start_command) {
            $nixpacks_command .= " --start-cmd \"{$this->application->start_command}\"";
        }
        if ($this->application->install_command) {
            $nixpacks_command .= " --install-cmd \"{$this->application->install_command}\"";
        }
        $nixpacks_command .= " {$this->workdir}";

        return $nixpacks_command;
    }

    /**
     * Generate Nixpacks environment variable arguments.
     */
    private function generate_nixpacks_env_variables()
    {
        $this->env_nixpacks_args = collect([]);
        if ($this->pull_request_id === 0) {
            foreach ($this->application->nixpacks_environment_variables as $env) {
                if (! is_null($env->real_value) && $env->real_value !== '') {
                    $this->env_nixpacks_args->push("--env {$env->key}={$env->real_value}");
                }
            }
        } else {
            foreach ($this->application->nixpacks_environment_variables_preview as $env) {
                if (! is_null($env->real_value) && $env->real_value !== '') {
                    $this->env_nixpacks_args->push("--env {$env->key}={$env->real_value}");
                }
            }
        }

        // Add SATURN_* environment variables to Nixpacks build context
        $saturn_envs = $this->generate_saturn_env_variables(forBuildTime: true);
        $saturn_envs->each(function ($value, $key) {
            // Only add environment variables with non-null and non-empty values
            if (! is_null($value) && $value !== '') {
                $this->env_nixpacks_args->push("--env {$key}={$value}");
            }
        });

        $this->env_nixpacks_args = $this->env_nixpacks_args->implode(' ');
    }

    /**
     * Get explicitly set NIXPACKS_NODE_VERSION from environment variables.
     *
     * @return string|null The version if set, null otherwise
     */
    private function getExplicitNodeVersion(): ?string
    {
        if ($this->pull_request_id === 0) {
            $envVars = $this->application->nixpacks_environment_variables;
        } else {
            $envVars = $this->application->nixpacks_environment_variables_preview;
        }

        foreach ($envVars as $env) {
            if ($env->key === 'NIXPACKS_NODE_VERSION' && ! is_null($env->real_value) && $env->real_value !== '') {
                return $env->real_value;
            }
        }

        return null;
    }

    /**
     * Auto-detect Node.js version from .nvmrc or package.json engines field.
     *
     * @return string|null The detected Node.js version (major only) or null if not found
     */
    private function autoDetectNodeVersion(): ?string
    {
        // First try .nvmrc
        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, "cat {$this->workdir}/.nvmrc 2>/dev/null || echo ''"), 'save' => 'nvmrc_content', 'hidden' => true, 'ignore_errors' => true],
        );

        $nvmrcContent = trim($this->saved_outputs->get('nvmrc_content', ''));
        if (! empty($nvmrcContent)) {
            $version = $this->parseNodeVersion($nvmrcContent);
            if ($version) {
                $this->application_deployment_queue->addLogEntry("Found Node.js version in .nvmrc: {$nvmrcContent}");

                return $version;
            }
        }

        // Try package.json engines.node
        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, "cat {$this->workdir}/package.json 2>/dev/null || echo '{}'"), 'save' => 'package_json', 'hidden' => true, 'ignore_errors' => true],
        );

        $packageJson = $this->saved_outputs->get('package_json', '{}');
        $parsed = json_decode($packageJson, true);

        if ($parsed && isset($parsed['engines']['node'])) {
            $enginesNode = $parsed['engines']['node'];
            $version = $this->parseNodeVersion($enginesNode);
            if ($version) {
                $this->application_deployment_queue->addLogEntry("Found Node.js version in package.json engines: {$enginesNode}");

                return $version;
            }
        }

        return null;
    }

    /**
     * Parse Node.js version string and extract major version number.
     *
     * Handles formats like: "20", "20.x", ">=18", "^20.0.0", "20.11.0", "lts/*", etc.
     *
     * @param  string  $versionString  The version string to parse
     * @return string|null The major version number or null if unparseable
     */
    /**
     * Known Nixpacks Node.js versions (from Nix packages, may lag behind official releases).
     * Updated: January 2026
     */
    private const NIXPACKS_NODE_VERSIONS = [
        '18' => '18.20.4',
        '20' => '20.18.1',
        '22' => '22.11.0',
        '23' => '23.3.0',
    ];

    /**
     * Check if the required Node.js version needs a custom Dockerfile.
     * Returns the exact version needed if Nixpacks can't provide it, null otherwise.
     */
    private function needsCustomNodeVersion(string $requiredVersion): ?string
    {
        // If only major version specified, Nixpacks can handle it
        if (preg_match('/^\d+$/', $requiredVersion)) {
            return null;
        }

        // Extract major version
        preg_match('/^(\d+)/', $requiredVersion, $matches);
        $majorVersion = $matches[1] ?? null;

        if (! $majorVersion || ! isset(self::NIXPACKS_NODE_VERSIONS[$majorVersion])) {
            return null;
        }

        $nixpacksVersion = self::NIXPACKS_NODE_VERSIONS[$majorVersion];

        // Compare versions - if required is higher than what Nixpacks provides, need custom
        if (version_compare($requiredVersion, $nixpacksVersion, '>')) {
            return $requiredVersion;
        }

        return null;
    }

    /**
     * Patch the generated Nixpacks Dockerfile to use a specific Node.js version.
     * This adds a multi-stage build that installs the exact Node version needed.
     */
    private function patchDockerfileForNodeVersion(string $dockerfilePath, string $nodeVersion): void
    {
        $this->application_deployment_queue->addLogEntry('----------------------------------------');
        $this->application_deployment_queue->addLogEntry("⚠️ Required Node.js {$nodeVersion} is newer than Nixpacks provides.");
        $this->application_deployment_queue->addLogEntry("Patching Dockerfile to install Node.js {$nodeVersion} via official binaries...");

        // Read the current Dockerfile
        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, "cat {$dockerfilePath}"), 'save' => 'original_dockerfile', 'hidden' => true],
        );

        $originalDockerfile = $this->saved_outputs->get('original_dockerfile', '');

        // Create a patched Dockerfile that installs the correct Node version
        // We need to override the Nix-installed node by replacing it in PATH
        // Nix puts node in ~/.nix-profile/bin which has priority, so we replace the symlinks
        $nodeInstallCommand = <<<BASH
# Saturn: Installing exact Node.js version {$nodeVersion}
RUN curl -fsSL https://nodejs.org/dist/v{$nodeVersion}/node-v{$nodeVersion}-linux-x64.tar.xz -o /tmp/node.tar.xz && \\
    tar -xJf /tmp/node.tar.xz -C /tmp && \\
    cp -f /tmp/node-v{$nodeVersion}-linux-x64/bin/node /root/.nix-profile/bin/node && \\
    cp -rf /tmp/node-v{$nodeVersion}-linux-x64/lib/node_modules/npm /root/.nix-profile/lib/node_modules/ && \\
    ln -sf /root/.nix-profile/lib/node_modules/npm/bin/npm-cli.js /root/.nix-profile/bin/npm && \\
    ln -sf /root/.nix-profile/lib/node_modules/npm/bin/npx-cli.js /root/.nix-profile/bin/npx && \\
    rm -rf /tmp/node.tar.xz /tmp/node-v{$nodeVersion}-linux-x64 && \\
    node --version && npm --version
BASH;

        // Find the line after nix-env installation and add our Node override
        $patchedDockerfile = preg_replace(
            '/(RUN nix-env.*?nix-collect-garbage.*?\n)/s',
            "$1\n{$nodeInstallCommand}\n",
            $originalDockerfile
        );

        // If patch didn't work (pattern not found), try alternative approach
        if ($patchedDockerfile === $originalDockerfile) {
            // Add after WORKDIR /app/
            $patchedDockerfile = preg_replace(
                '/(WORKDIR \/app\/\n)/s',
                "$1\n{$nodeInstallCommand}\n",
                $originalDockerfile
            );
        }

        // Write patched Dockerfile
        $patchedDockerfileBase64 = base64_encode($patchedDockerfile);
        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, "echo '{$patchedDockerfileBase64}' | base64 -d > {$dockerfilePath}"), 'hidden' => true],
        );

        $this->application_deployment_queue->addLogEntry("✅ Dockerfile patched successfully. Using Node.js {$nodeVersion}");
    }

    private function parseNodeVersion(string $versionString): ?string
    {
        $versionString = trim($versionString);

        // Remove 'v' prefix if present
        $versionString = ltrim($versionString, 'v');

        // Handle special LTS cases - default to latest LTS (22 as of 2025)
        if (str_contains(strtolower($versionString), 'lts')) {
            return '22';
        }

        // Try to extract full semver version (e.g., 22.12.0)
        if (preg_match('/^(\d+\.\d+\.\d+)/', $versionString, $matches)) {
            return $matches[1];
        }

        // Try to extract major.minor version (e.g., 22.12)
        if (preg_match('/^(\d+\.\d+)/', $versionString, $matches)) {
            return $matches[1];
        }

        // Extract major version only (e.g., 22)
        if (preg_match('/^(\d+)/', $versionString, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Warn user if no start script is found in package.json.
     * This prevents "successful" deployments that actually fail at runtime.
     */
    private function warnIfNoStartScript(): void
    {
        $packageJson = $this->saved_outputs->get('package_json', '{}');
        $parsed = json_decode($packageJson, true);

        if (! $parsed || ! is_array($parsed)) {
            return;
        }

        $scripts = $parsed['scripts'] ?? [];
        $hasStartScript = isset($scripts['start']) || isset($scripts['start:prod']);

        if (! $hasStartScript) {
            $this->application_deployment_queue->addLogEntry('----------------------------------------');
            $this->application_deployment_queue->addLogEntry('⚠️ WARNING: No "start" script found in package.json!', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('The container may fail to start after deployment.', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('Please add a "start" script to your package.json, for example:', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('  "scripts": { "start": "node dist/main.js" }', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('Or set a custom Start Command in the application settings.', type: 'stderr');
            $this->application_deployment_queue->addLogEntry('----------------------------------------');
        }
    }
}
