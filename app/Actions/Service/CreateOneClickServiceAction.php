<?php

namespace App\Actions\Service;

use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\Server;
use App\Models\Service;
use App\Models\StandaloneDocker;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateOneClickServiceAction
{
    use AsAction;

    /**
     * Create a one-click service from predefined templates.
     *
     * @param  string  $type  The one-click service type (e.g., 'wordpress-with-mysql', 'ghost', etc.)
     * @param  Server  $server  The target server
     * @param  Environment  $environment  The target environment
     * @param  StandaloneDocker  $destination  The docker destination
     * @param  bool  $instantDeploy  Whether to deploy immediately after creation
     * @return array{service: Service, domains: \Illuminate\Support\Collection}|array{error: string, valid_types?: \Illuminate\Support\Collection}
     */
    public function handle(
        string $type,
        Server $server,
        Environment $environment,
        StandaloneDocker $destination,
        bool $instantDeploy = false
    ): array {
        $services = get_service_templates();
        $serviceKeys = $services->keys();

        if (! $serviceKeys->contains($type)) {
            return [
                'error' => 'Service type not found.',
                'valid_types' => $serviceKeys,
            ];
        }

        $oneClickServiceName = $type;
        $oneClickService = data_get($services, "$oneClickServiceName.compose");
        $oneClickDotEnvs = data_get($services, "$oneClickServiceName.envs", null);

        if ($oneClickDotEnvs) {
            $oneClickDotEnvs = str(base64_decode($oneClickDotEnvs))->split('/\r\n|\r|\n/')->filter(function ($value) {
                return ! empty($value);
            });
        }

        if (! $oneClickService) {
            return [
                'error' => 'Service template not found.',
                'valid_types' => $serviceKeys,
            ];
        }

        $dockerComposeRaw = base64_decode($oneClickService);

        // Validate for command injection BEFORE creating service
        validateDockerComposeForInjection($dockerComposeRaw);

        $servicePayload = [
            'name' => "$oneClickServiceName-".str()->random(10),
            'docker_compose_raw' => $dockerComposeRaw,
            'environment_id' => $environment->id,
            'service_type' => $oneClickServiceName,
            'server_id' => $server->id,
            'destination_id' => $destination->id,
            'destination_type' => $destination->getMorphClass(),
        ];

        if (in_array($oneClickServiceName, NEEDS_TO_CONNECT_TO_PREDEFINED_NETWORK)) {
            data_set($servicePayload, 'connect_to_docker_network', true);
        }

        $service = Service::create($servicePayload);
        $service->name = "$oneClickServiceName-".$service->uuid;
        $service->save();

        // Create environment variables from template
        if ($oneClickDotEnvs?->count() > 0) {
            $oneClickDotEnvs->each(function ($value) use ($service) {
                $key = str()->before($value, '=');
                $value = str(str()->after($value, '='));
                $generatedValue = $value;

                if ($value->contains('SERVICE_')) {
                    $command = $value->after('SERVICE_')->beforeLast('_');
                    $generatedValue = generateEnvValue($command->value(), $service);
                }

                EnvironmentVariable::create([
                    'key' => $key,
                    'value' => $generatedValue,
                    'resourceable_id' => $service->id,
                    'resourceable_type' => $service->getMorphClass(),
                    'is_preview' => false,
                ]);
            });
        }

        $service->parse(isNew: true);

        // Apply service-specific application prerequisites
        applyServiceApplicationPrerequisites($service);

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
