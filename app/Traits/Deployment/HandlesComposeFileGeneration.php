<?php

namespace App\Traits\Deployment;

use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Trait for generating Docker Compose files during deployment.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $server, $destination
 * - $deployment_uuid, $pull_request_id, $preview, $container_name
 * - $workdir, $dockerfile_location, $production_image_name
 * - $docker_compose, $docker_compose_base64, $saved_outputs
 *
 * Required methods from parent class:
 * - execute_remote_command(), checkForCancellation(), create_workdir()
 * - generate_local_persistent_volumes(), generate_local_persistent_volumes_only_volume_names()
 * - generate_healthcheck_commands()
 */
trait HandlesComposeFileGeneration
{
    /**
     * Generate the Docker Compose file for deployment.
     */
    private function generate_compose_file()
    {
        $this->checkForCancellation();
        $this->create_workdir();
        $ports = $this->application->main_port();
        $persistent_storages = $this->generate_local_persistent_volumes();
        $persistent_file_volumes = $this->application->fileStorages()->get();
        $volume_names = $this->generate_local_persistent_volumes_only_volume_names();
        $labels = collect();
        if (data_get($this->application, 'custom_labels')) {
            $this->application->parseContainerLabels();
            $labels = collect(preg_split("/\r\n|\n|\r/", base64_decode($this->application->custom_labels)));
            $labels = $labels->filter(function ($value, $key) {
                return ! Str::startsWith($value, 'saturn.');
            });
            $this->application->custom_labels = base64_encode($labels->implode("\n"));
            $this->application->save();
        } else {
            if ($this->application->settings->is_container_label_readonly_enabled) {
                $labels = collect(generateLabelsApplication($this->application, $this->preview));
            }
        }
        if ($this->pull_request_id !== 0) {
            $labels = collect(generateLabelsApplication($this->application, $this->preview));
        }
        if ($this->application->settings->is_container_label_escape_enabled) {
            $labels = $labels->map(function ($value, $key) {
                return escapeDollarSign($value);
            });
        }
        $labels = $labels->merge(defaultLabels($this->application->id, $this->application->uuid, $this->application->project()->name, $this->application->name, $this->application->environment->name, $this->pull_request_id))->toArray();

        // Check for custom HEALTHCHECK
        if ($this->application->build_pack === 'dockerfile' || $this->application->dockerfile) {
            $this->execute_remote_command([
                executeInDocker($this->deployment_uuid, "cat {$this->workdir}{$this->dockerfile_location}"),
                'hidden' => true,
                'save' => 'dockerfile_from_repo',
                'ignore_errors' => true,
            ]);
            $this->application->parseHealthcheckFromDockerfile($this->saved_outputs->get('dockerfile_from_repo'));
        }
        $custom_network_aliases = [];
        if (! empty($this->application->custom_network_aliases_array)) {
            $custom_network_aliases = $this->application->custom_network_aliases_array;
        }
        $serviceConfig = [
            'image' => $this->production_image_name,
            'container_name' => $this->container_name,
            'restart' => RESTART_MODE,
            'networks' => [
                $this->destination->network => [
                    'aliases' => array_merge(
                        [$this->container_name],
                        $custom_network_aliases
                    ),
                ],
            ],
            'mem_limit' => $this->application->limits_memory,
            'memswap_limit' => $this->application->limits_memory_swap,
            'mem_swappiness' => $this->application->limits_memory_swappiness,
            'mem_reservation' => $this->application->limits_memory_reservation,
            'cpus' => (float) $this->application->limits_cpus,
            'cpu_shares' => $this->application->limits_cpu_shares,
        ];

        // Only expose ports for non-worker applications
        if (! empty($ports)) {
            $serviceConfig['expose'] = $ports;
        }

        $docker_compose = [
            'services' => [
                $this->container_name => $serviceConfig,
            ],
            'networks' => [
                $this->destination->network => [
                    'external' => true,
                    'name' => $this->destination->network,
                    'attachable' => true,
                ],
            ],
        ];
        // Always use .env file
        $docker_compose['services'][$this->container_name]['env_file'] = ['.env'];

        // Only add Saturn Platform healthcheck if no custom HEALTHCHECK found in Dockerfile
        // If custom_healthcheck_found is true, the Dockerfile's HEALTHCHECK will be used
        // If healthcheck is disabled, no healthcheck will be added
        if (! $this->application->custom_healthcheck_found && ! $this->application->isHealthcheckDisabled()) {
            $docker_compose['services'][$this->container_name]['healthcheck'] = [
                'test' => [
                    'CMD-SHELL',
                    $this->generate_healthcheck_commands(),
                ],
                'interval' => $this->application->health_check_interval.'s',
                'timeout' => $this->application->health_check_timeout.'s',
                'retries' => $this->application->health_check_retries,
                'start_period' => $this->application->health_check_start_period.'s',
            ];
        }

        if (! is_null($this->application->limits_cpuset)) {
            data_set($docker_compose, 'services.'.$this->container_name.'.cpuset', $this->application->limits_cpuset);
        }
        if ($this->server->isSwarm()) {
            data_forget($docker_compose, 'services.'.$this->container_name.'.container_name');
            data_forget($docker_compose, 'services.'.$this->container_name.'.expose');
            data_forget($docker_compose, 'services.'.$this->container_name.'.restart');

            data_forget($docker_compose, 'services.'.$this->container_name.'.mem_limit');
            data_forget($docker_compose, 'services.'.$this->container_name.'.memswap_limit');
            data_forget($docker_compose, 'services.'.$this->container_name.'.mem_swappiness');
            data_forget($docker_compose, 'services.'.$this->container_name.'.mem_reservation');
            data_forget($docker_compose, 'services.'.$this->container_name.'.cpus');
            data_forget($docker_compose, 'services.'.$this->container_name.'.cpuset');
            data_forget($docker_compose, 'services.'.$this->container_name.'.cpu_shares');

            $docker_compose['services'][$this->container_name]['deploy'] = [
                'mode' => 'replicated',
                'replicas' => data_get($this->application, 'swarm_replicas', 1),
                'update_config' => [
                    'order' => 'start-first',
                ],
                'rollback_config' => [
                    'order' => 'start-first',
                ],
                'labels' => $labels,
                'resources' => [
                    'limits' => [
                        'cpus' => $this->application->limits_cpus,
                        'memory' => $this->application->limits_memory,
                    ],
                    'reservations' => [
                        'cpus' => $this->application->limits_cpus,
                        'memory' => $this->application->limits_memory,
                    ],
                ],
            ];
            if (data_get($this->application, 'swarm_placement_constraints')) {
                $swarm_placement_constraints = Yaml::parse(base64_decode(data_get($this->application, 'swarm_placement_constraints')));
                $docker_compose['services'][$this->container_name]['deploy'] = array_merge(
                    $docker_compose['services'][$this->container_name]['deploy'],
                    $swarm_placement_constraints
                );
            }
            if (data_get($this->application, 'settings.is_swarm_only_worker_nodes')) {
                $docker_compose['services'][$this->container_name]['deploy']['placement']['constraints'][] = 'node.role == worker';
            }
            if ($this->pull_request_id !== 0) {
                $docker_compose['services'][$this->container_name]['deploy']['replicas'] = 1;
            }
        } else {
            $docker_compose['services'][$this->container_name]['labels'] = $labels;
        }
        if ($this->server->isLogDrainEnabled() && $this->application->isLogDrainEnabled()) {
            $docker_compose['services'][$this->container_name]['logging'] = generate_fluentd_configuration();
        }
        if ($this->application->settings->is_gpu_enabled) {
            $docker_compose['services'][$this->container_name]['deploy']['resources']['reservations']['devices'] = [
                [
                    'driver' => data_get($this->application, 'settings.gpu_driver', 'nvidia'),
                    'capabilities' => ['gpu'],
                    'options' => data_get($this->application, 'settings.gpu_options', []),
                ],
            ];
            if (data_get($this->application, 'settings.gpu_count')) {
                $count = data_get($this->application, 'settings.gpu_count');
                if ($count === 'all') {
                    $docker_compose['services'][$this->container_name]['deploy']['resources']['reservations']['devices'][0]['count'] = $count;
                } else {
                    $docker_compose['services'][$this->container_name]['deploy']['resources']['reservations']['devices'][0]['count'] = (int) $count;
                }
            } elseif (data_get($this->application, 'settings.gpu_device_ids')) {
                $docker_compose['services'][$this->container_name]['deploy']['resources']['reservations']['devices'][0]['ids'] = data_get($this->application, 'settings.gpu_device_ids');
            }
        }
        if ($this->application->isHealthcheckDisabled()) {
            data_forget($docker_compose, 'services.'.$this->container_name.'.healthcheck');
        }
        if (count($this->application->ports_mappings_array) > 0 && $this->pull_request_id === 0) {
            $docker_compose['services'][$this->container_name]['ports'] = $this->application->ports_mappings_array;
        }

        if (count($persistent_storages) > 0) {
            if (! data_get($docker_compose, 'services.'.$this->container_name.'.volumes')) {
                $docker_compose['services'][$this->container_name]['volumes'] = [];
            }
            $docker_compose['services'][$this->container_name]['volumes'] = array_merge($docker_compose['services'][$this->container_name]['volumes'], $persistent_storages);
        }
        if (count($persistent_file_volumes) > 0) {
            if (! data_get($docker_compose, 'services.'.$this->container_name.'.volumes')) {
                $docker_compose['services'][$this->container_name]['volumes'] = [];
            }
            $docker_compose['services'][$this->container_name]['volumes'] = array_merge($docker_compose['services'][$this->container_name]['volumes'], $persistent_file_volumes->map(function ($item) {
                return "$item->fs_path:$item->mount_path";
            })->toArray());
        }
        if (count($volume_names) > 0) {
            $docker_compose['volumes'] = $volume_names;
        }

        if ($this->pull_request_id === 0) {
            $custom_compose = convertDockerRunToCompose($this->application->custom_docker_run_options);
            if ((bool) $this->application->settings->is_consistent_container_name_enabled) {
                if (! $this->application->settings->custom_internal_name) {
                    $docker_compose['services'][$this->application->uuid] = $docker_compose['services'][$this->container_name];
                    if (count($custom_compose) > 0) {
                        $ipv4 = data_get($custom_compose, 'ip.0');
                        $ipv6 = data_get($custom_compose, 'ip6.0');
                        data_forget($custom_compose, 'ip');
                        data_forget($custom_compose, 'ip6');
                        if ($ipv4 || $ipv6) {
                            data_forget($docker_compose['services'][$this->application->uuid], 'networks');
                        }
                        if ($ipv4) {
                            $docker_compose['services'][$this->application->uuid]['networks'][$this->destination->network]['ipv4_address'] = $ipv4;
                        }
                        if ($ipv6) {
                            $docker_compose['services'][$this->application->uuid]['networks'][$this->destination->network]['ipv6_address'] = $ipv6;
                        }
                        $docker_compose['services'][$this->application->uuid] = array_merge_recursive($docker_compose['services'][$this->application->uuid], $custom_compose);
                    }
                }
            } else {
                if (count($custom_compose) > 0) {
                    $ipv4 = data_get($custom_compose, 'ip.0');
                    $ipv6 = data_get($custom_compose, 'ip6.0');
                    data_forget($custom_compose, 'ip');
                    data_forget($custom_compose, 'ip6');
                    if ($ipv4 || $ipv6) {
                        data_forget($docker_compose['services'][$this->container_name], 'networks');
                    }
                    if ($ipv4) {
                        $docker_compose['services'][$this->container_name]['networks'][$this->destination->network]['ipv4_address'] = $ipv4;
                    }
                    if ($ipv6) {
                        $docker_compose['services'][$this->container_name]['networks'][$this->destination->network]['ipv6_address'] = $ipv6;
                    }
                    $docker_compose['services'][$this->container_name] = array_merge_recursive($docker_compose['services'][$this->container_name], $custom_compose);
                }
            }
        }

        $this->docker_compose = Yaml::dump($docker_compose, 10);
        $this->docker_compose_base64 = base64_encode($this->docker_compose);
        $this->execute_remote_command([executeInDocker($this->deployment_uuid, "echo '{$this->docker_compose_base64}' | base64 -d | tee {$this->workdir}/docker-compose.yaml > /dev/null"), 'hidden' => true]);
    }
}
