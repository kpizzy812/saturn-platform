<?php

namespace App\Actions\Service;

use App\Models\Environment;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandaloneDocker;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Yaml\Yaml;

class CreateCustomServiceAction
{
    use AsAction;

    /**
     * Create a custom service from raw docker-compose content.
     *
     * @param  string  $dockerComposeRaw  The raw docker-compose YAML content (already decoded from base64)
     * @param  Server  $server  The target server
     * @param  Environment  $environment  The target environment
     * @param  StandaloneDocker  $destination  The docker destination
     * @param  string|null  $name  Optional service name
     * @param  string|null  $description  Optional service description
     * @param  bool  $connectToDockerNetwork  Connect to predefined docker network
     * @param  bool  $instantDeploy  Whether to deploy immediately after creation
     * @return array{service: Service, domains: \Illuminate\Support\Collection}
     */
    public function handle(
        string $dockerComposeRaw,
        Server $server,
        Environment $environment,
        StandaloneDocker $destination,
        ?string $name = null,
        ?string $description = null,
        bool $connectToDockerNetwork = false,
        bool $instantDeploy = false
    ): array {
        // Normalize YAML formatting
        $dockerComposeRaw = Yaml::dump(Yaml::parse($dockerComposeRaw), 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        // Validate for command injection BEFORE saving to database
        validateDockerComposeForInjection($dockerComposeRaw);

        $service = new Service;
        $service->name = $name ?? 'service-'.str()->random(10);
        $service->description = $description;
        $service->docker_compose_raw = $dockerComposeRaw;
        $service->environment_id = $environment->id;
        $service->server_id = $server->id;
        $service->destination_id = $destination->id;
        $service->destination_type = $destination->getMorphClass();
        $service->connect_to_docker_network = $connectToDockerNetwork;
        $service->save();

        $service->parse(isNew: true);

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
        $domains = $service->applications()->get()->pluck('fqdn')->sort();

        return $domains->map(function ($domain) {
            if (count(explode(':', $domain)) > 2) {
                return str($domain)->beforeLast(':')->value();
            }

            return $domain;
        })->values();
    }
}
