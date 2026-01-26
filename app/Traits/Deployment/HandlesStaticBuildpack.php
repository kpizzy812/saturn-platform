<?php

namespace App\Traits\Deployment;

/**
 * Trait for static buildpack deployment operations.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $deployment_uuid
 * - $workdir, $production_image_name, $addHosts
 *
 * Required methods from parent class:
 * - execute_remote_command()
 *
 * Required constants from parent class:
 * - BUILD_SCRIPT_PATH
 */
trait HandlesStaticBuildpack
{
    /**
     * Pull the latest version of a Docker image from registry.
     */
    private function pull_latest_image($image)
    {
        $this->application_deployment_queue->addLogEntry("Pulling latest image ($image) from the registry.");
        $this->execute_remote_command(
            [
                executeInDocker($this->deployment_uuid, "docker pull {$image}"),
                'hidden' => true,
            ]
        );
    }

    /**
     * Build a static image using nginx to serve static files.
     */
    private function build_static_image()
    {
        $this->application_deployment_queue->addLogEntry('----------------------------------------');
        $this->application_deployment_queue->addLogEntry('Static deployment. Copying static assets to the image.');
        if ($this->application->static_image) {
            $this->pull_latest_image($this->application->static_image);
        }
        $dockerfile = base64_encode("FROM {$this->application->static_image}
        WORKDIR /usr/share/nginx/html/
        LABEL saturn.deploymentId={$this->deployment_uuid}
        COPY . .
        RUN rm -f /usr/share/nginx/html/nginx.conf
        RUN rm -f /usr/share/nginx/html/Dockerfile
        RUN rm -f /usr/share/nginx/html/docker-compose.yaml
        RUN rm -f /usr/share/nginx/html/.env
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
        $build_command = "docker build {$this->addHosts} --network host -f {$this->workdir}/Dockerfile --progress plain -t {$this->production_image_name} {$this->workdir}";
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
        $this->application_deployment_queue->addLogEntry('Building docker image completed.');
    }
}
