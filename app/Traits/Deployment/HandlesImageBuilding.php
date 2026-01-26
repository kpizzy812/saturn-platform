<?php

namespace App\Traits\Deployment;

/**
 * Trait for handling Docker image building during deployment.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $deployment_uuid
 * - $dockerBuildkitSupported, $build_args, $build_secrets
 * - $disableBuildCache, $force_rebuild, $addHosts
 * - $workdir, $dockerfile_location, $buildTarget
 * - $build_image_name, $production_image_name
 * - $nixpacks_plan, $destination
 *
 * Required methods from parent class:
 * - execute_remote_command(), generate_saturn_env_variables()
 * - pull_latest_image(), modify_dockerfile_for_secrets()
 * - wrap_build_command_with_env_export()
 */
trait HandlesImageBuilding
{
    /**
     * Build the Docker image for deployment.
     */
    private function build_image(): void
    {
        // Add Saturn Platform related variables to the build args/secrets
        if ($this->dockerBuildkitSupported) {
            // Saturn Platform variables are already included in the secrets from generate_build_env_variables
            // build_secrets is already a string at this point
        } else {
            // Traditional build args approach - generate SATURN_ variables locally
            // Generate SATURN_ variables locally for build args
            $saturn_envs = $this->generate_saturn_env_variables(forBuildTime: true);
            $saturn_envs->each(function ($value, $key) {
                $this->build_args->push("--build-arg '{$key}'");
            });
            $this->build_args = $this->build_args instanceof \Illuminate\Support\Collection
                ? $this->build_args->implode(' ')
                : (string) $this->build_args;
        }

        $this->application_deployment_queue->addLogEntry('----------------------------------------');
        if ($this->disableBuildCache) {
            $this->application_deployment_queue->addLogEntry('Docker build cache is disabled. It will not be used during the build process.');
        }
        if ($this->application->build_pack === 'static') {
            $this->application_deployment_queue->addLogEntry('Static deployment. Copying static assets to the image.');
        } else {
            $this->application_deployment_queue->addLogEntry('Building docker image started.');
            $this->application_deployment_queue->addLogEntry('To check the current progress, click on Show Debug Logs.');
        }

        if ($this->application->settings->is_static) {
            $this->buildStaticImage();
        } else {
            $this->buildRegularImage();
        }

        $this->application_deployment_queue->addLogEntry('Building docker image completed.');
    }

    /**
     * Build static site image with nginx.
     */
    private function buildStaticImage(): void
    {
        if ($this->application->static_image) {
            $this->pull_latest_image($this->application->static_image);
            $this->application_deployment_queue->addLogEntry('Continuing with the building process.');
        }

        if ($this->application->build_pack === 'nixpacks') {
            $this->buildStaticWithNixpacks();
        } else {
            $this->buildStaticWithDockerfile();
        }

        $this->createFinalStaticImage();
    }

    /**
     * Build static site using nixpacks.
     */
    private function buildStaticWithNixpacks(): void
    {
        $this->nixpacks_plan = base64_encode($this->nixpacks_plan);
        $this->execute_remote_command([executeInDocker($this->deployment_uuid, "echo '{$this->nixpacks_plan}' | base64 -d | tee ".self::NIXPACKS_PLAN_PATH.' > /dev/null'), 'hidden' => true]);

        if ($this->force_rebuild) {
            $this->execute_remote_command([
                executeInDocker($this->deployment_uuid, 'nixpacks build -c '.self::NIXPACKS_PLAN_PATH." --no-cache --no-error-without-start -n {$this->build_image_name} {$this->workdir} -o {$this->workdir}"),
                'hidden' => true,
            ], [
                executeInDocker($this->deployment_uuid, "cat {$this->workdir}/.nixpacks/Dockerfile"),
                'hidden' => true,
            ]);
        } else {
            $this->execute_remote_command([
                executeInDocker($this->deployment_uuid, 'nixpacks build -c '.self::NIXPACKS_PLAN_PATH." --cache-key '{$this->application->uuid}' --no-error-without-start -n {$this->build_image_name} {$this->workdir} -o {$this->workdir}"),
                'hidden' => true,
            ], [
                executeInDocker($this->deployment_uuid, "cat {$this->workdir}/.nixpacks/Dockerfile"),
                'hidden' => true,
            ]);
        }

        // Patch Dockerfile for specific Node.js version if needed
        if (property_exists($this, 'requiredNodeVersion') && $this->requiredNodeVersion) {
            $this->patchDockerfileForNodeVersion("{$this->workdir}/.nixpacks/Dockerfile", $this->requiredNodeVersion);
        }

        $build_command = $this->createNixpacksBuildCommand("{$this->workdir}/.nixpacks/Dockerfile", $this->build_image_name);
        $this->executeBuildCommand($build_command);
        $this->execute_remote_command([executeInDocker($this->deployment_uuid, 'rm '.self::NIXPACKS_PLAN_PATH), 'hidden' => true]);
    }

    /**
     * Build static site using Dockerfile.
     */
    private function buildStaticWithDockerfile(): void
    {
        $build_command = $this->createDockerfileBuildCommand(
            "{$this->workdir}{$this->dockerfile_location}",
            $this->build_image_name,
            useNetwork: $this->destination->network
        );
        $this->executeBuildCommand($build_command);
    }

    /**
     * Create final static image with nginx.
     */
    private function createFinalStaticImage(): void
    {
        $publishDir = trim($this->application->publish_directory, '/');
        $publishDir = $publishDir ? "/{$publishDir}" : '';
        $dockerfile = base64_encode("FROM {$this->application->static_image}
WORKDIR /usr/share/nginx/html/
LABEL saturn.deploymentId={$this->deployment_uuid}
COPY --from=$this->build_image_name /app{$publishDir} .
COPY ./nginx.conf /etc/nginx/conf.d/default.conf");

        if (str($this->application->custom_nginx_configuration)->isNotEmpty()) {
            $nginx_config = base64_encode($this->application->custom_nginx_configuration);
        } else {
            if ($this->application->settings->is_spa) {
                $nginx_config = base64_encode(defaultNginxConfiguration('spa'));
            } else {
                $nginx_config = base64_encode(defaultNginxConfiguration());
            }
        }

        $build_command = $this->wrap_build_command_with_env_export("docker build {$this->addHosts} --network host -f {$this->workdir}/Dockerfile {$this->build_args} --progress plain -t {$this->production_image_name} {$this->workdir}");
        $base64_build_command = base64_encode($build_command);

        $this->execute_remote_command(
            [
                executeInDocker($this->deployment_uuid, "echo '{$dockerfile}' | base64 -d | tee {$this->workdir}/Dockerfile > /dev/null"),
            ],
            [
                executeInDocker($this->deployment_uuid, "echo '{$nginx_config}' | base64 -d | tee {$this->workdir}/nginx.conf > /dev/null"),
            ],
            [
                executeInDocker($this->deployment_uuid, "echo '{$base64_build_command}' | base64 -d | tee ".self::BUILD_SCRIPT_PATH.' > /dev/null'),
                'hidden' => true,
            ],
            [
                executeInDocker($this->deployment_uuid, 'cat '.self::BUILD_SCRIPT_PATH),
                'hidden' => true,
            ],
            [
                executeInDocker($this->deployment_uuid, 'bash '.self::BUILD_SCRIPT_PATH),
                'hidden' => true,
            ]
        );
    }

    /**
     * Build regular (non-static) Docker image.
     */
    private function buildRegularImage(): void
    {
        if ($this->application->dockerfile) {
            $this->buildFromSimpleDockerfile();
        } elseif ($this->application->build_pack === 'nixpacks') {
            $this->buildFromNixpacks();
        } else {
            $this->buildFromDockerfileBuildpack();
        }
    }

    /**
     * Build from simple inline Dockerfile.
     */
    private function buildFromSimpleDockerfile(): void
    {
        $build_command = $this->createDockerfileBuildCommand(
            "{$this->workdir}{$this->dockerfile_location}",
            $this->production_image_name,
            pullImage: true
        );
        $this->executeBuildCommand($build_command);
    }

    /**
     * Build from Nixpacks.
     */
    private function buildFromNixpacks(): void
    {
        $this->nixpacks_plan = base64_encode($this->nixpacks_plan);
        $this->execute_remote_command([executeInDocker($this->deployment_uuid, "echo '{$this->nixpacks_plan}' | base64 -d | tee ".self::NIXPACKS_PLAN_PATH.' > /dev/null'), 'hidden' => true]);

        if ($this->force_rebuild) {
            $this->execute_remote_command([
                executeInDocker($this->deployment_uuid, 'nixpacks build -c '.self::NIXPACKS_PLAN_PATH." --no-cache --no-error-without-start -n {$this->production_image_name} {$this->workdir} -o {$this->workdir}"),
                'hidden' => true,
            ], [
                executeInDocker($this->deployment_uuid, "cat {$this->workdir}/.nixpacks/Dockerfile"),
                'hidden' => true,
            ]);
        } else {
            $this->execute_remote_command([
                executeInDocker($this->deployment_uuid, 'nixpacks build -c '.self::NIXPACKS_PLAN_PATH." --cache-key '{$this->application->uuid}' --no-error-without-start -n {$this->production_image_name} {$this->workdir} -o {$this->workdir}"),
                'hidden' => true,
            ], [
                executeInDocker($this->deployment_uuid, "cat {$this->workdir}/.nixpacks/Dockerfile"),
                'hidden' => true,
            ]);
        }

        // Patch Dockerfile for specific Node.js version if needed
        if (property_exists($this, 'requiredNodeVersion') && $this->requiredNodeVersion) {
            $this->patchDockerfileForNodeVersion("{$this->workdir}/.nixpacks/Dockerfile", $this->requiredNodeVersion);
        }

        $build_command = $this->createNixpacksBuildCommand("{$this->workdir}/.nixpacks/Dockerfile", $this->production_image_name);
        $this->executeBuildCommand($build_command);
        $this->execute_remote_command([executeInDocker($this->deployment_uuid, 'rm '.self::NIXPACKS_PLAN_PATH), 'hidden' => true]);
    }

    /**
     * Build from Dockerfile buildpack.
     */
    private function buildFromDockerfileBuildpack(): void
    {
        $build_command = $this->createDockerfileBuildCommand(
            "{$this->workdir}{$this->dockerfile_location}",
            $this->production_image_name
        );
        $this->executeBuildCommand($build_command);
    }

    /**
     * Create build command for Nixpacks-generated Dockerfile.
     */
    private function createNixpacksBuildCommand(string $dockerfilePath, string $imageName): string
    {
        if ($this->dockerBuildkitSupported && $this->application->settings->use_build_secrets) {
            $this->modify_dockerfile_for_secrets($dockerfilePath);
            $secrets_flags = $this->build_secrets ? " {$this->build_secrets}" : '';
            $cache_flag = $this->force_rebuild ? '--no-cache ' : '';

            return $this->wrap_build_command_with_env_export("DOCKER_BUILDKIT=1 docker build {$cache_flag}{$this->addHosts} --network host -f {$dockerfilePath}{$secrets_flags} --progress plain -t {$imageName} {$this->workdir}");
        } elseif ($this->dockerBuildkitSupported) {
            $this->modify_dockerfile_for_secrets($dockerfilePath);
            $secrets_flags = $this->build_secrets ? " {$this->build_secrets}" : '';
            $cache_flag = $this->force_rebuild ? '--no-cache ' : '';

            return $this->wrap_build_command_with_env_export("DOCKER_BUILDKIT=1 docker build {$cache_flag}{$this->addHosts} --network host -f {$dockerfilePath}{$secrets_flags} --progress plain -t {$imageName} {$this->build_args} {$this->workdir}");
        } else {
            $cache_flag = $this->force_rebuild ? '--no-cache ' : '';

            return $this->wrap_build_command_with_env_export("docker build {$cache_flag}{$this->addHosts} --network host -f {$dockerfilePath} --progress plain -t {$imageName} {$this->build_args} {$this->workdir}");
        }
    }

    /**
     * Create build command for regular Dockerfile.
     */
    private function createDockerfileBuildCommand(string $dockerfilePath, string $imageName, bool $pullImage = false, ?string $useNetwork = null): string
    {
        $network = $useNetwork ?? 'host';
        $networkFlag = $useNetwork ? "--network {$network}" : '--network host';
        $pullFlag = $pullImage ? '--pull ' : '';
        $cache_flag = $this->force_rebuild ? '--no-cache ' : '';

        if ($this->dockerBuildkitSupported && $this->application->settings->use_build_secrets) {
            $this->modify_dockerfile_for_secrets($dockerfilePath);
            $secrets_flags = $this->build_secrets ? " {$this->build_secrets}" : '';

            return $this->wrap_build_command_with_env_export("DOCKER_BUILDKIT=1 docker build {$cache_flag}{$pullFlag}{$this->buildTarget} {$this->addHosts} {$networkFlag} -f {$dockerfilePath}{$secrets_flags} --progress plain -t {$imageName} {$this->workdir}");
        } elseif ($this->dockerBuildkitSupported) {
            $this->modify_dockerfile_for_secrets($dockerfilePath);
            $secrets_flags = $this->build_secrets ? " {$this->build_secrets}" : '';

            return $this->wrap_build_command_with_env_export("DOCKER_BUILDKIT=1 docker build {$cache_flag}{$pullFlag}{$this->buildTarget} {$this->addHosts} {$networkFlag} -f {$dockerfilePath}{$secrets_flags} --progress plain -t {$imageName} {$this->build_args} {$this->workdir}");
        } else {
            return $this->wrap_build_command_with_env_export("docker build {$cache_flag}{$pullFlag}{$this->buildTarget} {$this->addHosts} {$networkFlag} -f {$dockerfilePath} {$this->build_args} --progress plain -t {$imageName} {$this->workdir}");
        }
    }

    /**
     * Execute a build command via script.
     */
    private function executeBuildCommand(string $build_command): void
    {
        $base64_build_command = base64_encode($build_command);
        $this->execute_remote_command(
            [
                executeInDocker($this->deployment_uuid, "echo '{$base64_build_command}' | base64 -d | tee ".self::BUILD_SCRIPT_PATH.' > /dev/null'),
                'hidden' => true,
            ],
            [
                executeInDocker($this->deployment_uuid, 'cat '.self::BUILD_SCRIPT_PATH),
                'hidden' => true,
            ],
            [
                executeInDocker($this->deployment_uuid, 'bash '.self::BUILD_SCRIPT_PATH),
                'hidden' => true,
            ]
        );
    }
}
