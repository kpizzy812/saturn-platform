<?php

namespace App\Actions\Service;

use App\Models\Service;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Yaml\Yaml;

class UpdateServiceAction
{
    use AsAction;

    /**
     * Update a service with the provided data.
     *
     * @param  Service  $service  The service to update
     * @param  array  $data  The update data (already validated)
     * @param  bool  $instantDeploy  Whether to deploy immediately after update
     * @return array{service: Service, domains: \Illuminate\Support\Collection}
     */
    public function handle(
        Service $service,
        array $data,
        bool $instantDeploy = false
    ): array {
        // Handle docker_compose_raw if provided
        if (isset($data['docker_compose_raw'])) {
            $dockerComposeRaw = Yaml::dump(
                Yaml::parse($data['docker_compose_raw']),
                10,
                2,
                Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
            );

            // Validate for command injection
            validateDockerComposeForInjection($dockerComposeRaw);

            $service->docker_compose_raw = $dockerComposeRaw;
        }

        // Update basic fields
        if (isset($data['name'])) {
            $service->name = $data['name'];
        }
        if (isset($data['description'])) {
            $service->description = $data['description'];
        }
        if (isset($data['connect_to_docker_network'])) {
            $service->connect_to_docker_network = $data['connect_to_docker_network'];
        }

        // Update resource limits
        $limitFields = [
            'limits_memory',
            'limits_memory_swap',
            'limits_memory_swappiness',
            'limits_memory_reservation',
            'limits_cpus',
            'limits_cpuset',
            'limits_cpu_shares',
        ];

        foreach ($limitFields as $field) {
            if (isset($data[$field])) {
                $service->{$field} = $data[$field];
            }
        }

        $service->save();
        $service->parse();

        if ($instantDeploy) {
            StartService::dispatch($service);
        }

        $domains = $this->extractDomains($service);

        return [
            'service' => $service,
            'domains' => $domains,
        ];
    }

    /**
     * Extract and format domains from service applications.
     */
    private function extractDomains(Service $service): \Illuminate\Support\Collection
    {
        $domains = $service->applications()->pluck('fqdn')->sort();

        return $domains->map(function ($domain) {
            if (count(explode(':', $domain)) > 2) {
                return str($domain)->beforeLast(':')->value();
            }

            return $domain;
        })->values();
    }
}
