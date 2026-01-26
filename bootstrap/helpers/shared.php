<?php

/**
 * Docker Compose parsing and network helpers.
 *
 * Contains the main Docker Compose parsing function and related network helpers.
 * These functions are kept together due to their complexity and interdependencies.
 */

use App\Enums\ProxyTypes;
use App\Jobs\ServerFilesFromServerJob;
use App\Models\Application;
use App\Models\ApplicationPreview;
use App\Models\EnvironmentVariable;
use App\Models\LocalFileVolume;
use App\Models\LocalPersistentVolume;
use App\Models\Service;
use App\Models\ServiceApplication;
use App\Models\ServiceDatabase;
use Illuminate\Support\Str;
use Spatie\Url\Url;
use Symfony\Component\Yaml\Yaml;
use Visus\Cuid2\Cuid2;

function getTopLevelNetworks(Service|Application $resource)
{
    if ($resource->getMorphClass() === \App\Models\Service::class) {
        if ($resource->docker_compose_raw) {
            try {
                $yaml = Yaml::parse($resource->docker_compose_raw);
            } catch (\Exception $e) {
                // If the docker-compose.yml file is not valid, we will return the network name as the key
                $topLevelNetworks = collect([
                    $resource->uuid => [
                        'name' => $resource->uuid,
                        'external' => true,
                    ],
                ]);

                return $topLevelNetworks->keys();
            }
            $services = data_get($yaml, 'services');
            $topLevelNetworks = collect(data_get($yaml, 'networks', []));
            $definedNetwork = collect([$resource->uuid]);
            $services = collect($services)->map(function ($service, $_) use ($topLevelNetworks, $definedNetwork) {
                $serviceNetworks = collect(data_get($service, 'networks', []));
                $networkMode = data_get($service, 'network_mode');

                $hasValidNetworkMode =
                    $networkMode === 'host' ||
                    (is_string($networkMode) && (str_starts_with($networkMode, 'service:') || str_starts_with($networkMode, 'container:')));

                // Only add 'networks' key if 'network_mode' is not 'host' or does not start with 'service:' or 'container:'
                if (! $hasValidNetworkMode) {
                    // Collect/create/update networks
                    if ($serviceNetworks->count() > 0) {
                        foreach ($serviceNetworks as $networkName => $networkDetails) {
                            if ($networkName === 'default') {
                                continue;
                            }
                            // ignore alias
                            if ($networkDetails['aliases'] ?? false) {
                                continue;
                            }
                            $networkExists = $topLevelNetworks->contains(function ($value, $key) use ($networkName) {
                                return $value == $networkName || $key == $networkName;
                            });
                            if (! $networkExists) {
                                if (is_string($networkDetails) || is_int($networkDetails)) {
                                    $topLevelNetworks->put($networkDetails, null);
                                }
                            }
                        }
                    }

                    $definedNetworkExists = $topLevelNetworks->contains(function ($value, $_) use ($definedNetwork) {
                        return $value == $definedNetwork;
                    });
                    if (! $definedNetworkExists) {
                        foreach ($definedNetwork as $network) {
                            $topLevelNetworks->put($network, [
                                'name' => $network,
                                'external' => true,
                            ]);
                        }
                    }
                }

                return $service;
            });

            return $topLevelNetworks->keys();
        }
    } elseif ($resource->getMorphClass() === \App\Models\Application::class) {
        try {
            $yaml = Yaml::parse($resource->docker_compose_raw);
        } catch (\Exception $e) {
            // If the docker-compose.yml file is not valid, we will return the network name as the key
            $topLevelNetworks = collect([
                $resource->uuid => [
                    'name' => $resource->uuid,
                    'external' => true,
                ],
            ]);

            return $topLevelNetworks->keys();
        }
        $topLevelNetworks = collect(data_get($yaml, 'networks', []));
        $services = data_get($yaml, 'services');
        $definedNetwork = collect([$resource->uuid]);
        $services = collect($services)->map(function ($service, $_) use ($topLevelNetworks, $definedNetwork) {
            $serviceNetworks = collect(data_get($service, 'networks', []));

            // Collect/create/update networks
            if ($serviceNetworks->count() > 0) {
                foreach ($serviceNetworks as $networkName => $networkDetails) {
                    if ($networkName === 'default') {
                        continue;
                    }
                    // ignore alias
                    if ($networkDetails['aliases'] ?? false) {
                        continue;
                    }
                    $networkExists = $topLevelNetworks->contains(function ($value, $key) use ($networkName) {
                        return $value == $networkName || $key == $networkName;
                    });
                    if (! $networkExists) {
                        if (is_string($networkDetails) || is_int($networkDetails)) {
                            $topLevelNetworks->put($networkDetails, null);
                        }
                    }
                }
            }
            $definedNetworkExists = $topLevelNetworks->contains(function ($value, $_) use ($definedNetwork) {
                return $value == $definedNetwork;
            });
            if (! $definedNetworkExists) {
                foreach ($definedNetwork as $network) {
                    $topLevelNetworks->put($network, [
                        'name' => $network,
                        'external' => true,
                    ]);
                }
            }

            return $service;
        });

        return $topLevelNetworks->keys();
    }
}

function parseDockerComposeFile(Service|Application $resource, bool $isNew = false, int $pull_request_id = 0, ?int $preview_id = null)
{
    if ($resource->getMorphClass() === \App\Models\Service::class) {
        if ($resource->docker_compose_raw) {
            try {
                $yaml = Yaml::parse($resource->docker_compose_raw);
            } catch (\Exception $e) {
                throw new \RuntimeException($e->getMessage());
            }
            $allServices = get_service_templates();
            $topLevelVolumes = collect(data_get($yaml, 'volumes', []));
            $topLevelNetworks = collect(data_get($yaml, 'networks', []));
            $topLevelConfigs = collect(data_get($yaml, 'configs', []));
            $topLevelSecrets = collect(data_get($yaml, 'secrets', []));
            $services = data_get($yaml, 'services');

            $generatedServiceFQDNS = collect([]);
            if (is_null($resource->destination)) {
                $destination = $resource->server->destinations()->first();
                if ($destination) {
                    $resource->destination()->associate($destination);
                    $resource->save();
                }
            }
            $definedNetwork = collect([$resource->uuid]);
            if ($topLevelVolumes->count() > 0) {
                $tempTopLevelVolumes = collect([]);
                foreach ($topLevelVolumes as $volumeName => $volume) {
                    if (is_null($volume)) {
                        continue;
                    }
                    $tempTopLevelVolumes->put($volumeName, $volume);
                }
                $topLevelVolumes = collect($tempTopLevelVolumes);
            }
            $services = collect($services)->map(function ($service, $serviceName) use ($topLevelVolumes, $topLevelNetworks, $definedNetwork, $isNew, $generatedServiceFQDNS, $resource, $allServices) {
                // Workarounds for beta users.
                if ($serviceName === 'registry') {
                    $tempServiceName = 'docker-registry';
                } else {
                    $tempServiceName = $serviceName;
                }
                if (str(data_get($service, 'image'))->contains('glitchtip')) {
                    $tempServiceName = 'glitchtip';
                }
                if ($serviceName === 'supabase-kong') {
                    $tempServiceName = 'supabase';
                }
                $serviceDefinition = data_get($allServices, $tempServiceName);
                $predefinedPort = data_get($serviceDefinition, 'port');
                if ($serviceName === 'plausible') {
                    $predefinedPort = '8000';
                }
                // End of workarounds for beta users.
                $serviceVolumes = collect(data_get($service, 'volumes', []));
                $servicePorts = collect(data_get($service, 'ports', []));
                $serviceNetworks = collect(data_get($service, 'networks', []));
                $serviceVariables = collect(data_get($service, 'environment', []));
                $serviceLabels = collect(data_get($service, 'labels', []));
                $networkMode = data_get($service, 'network_mode');

                $hasValidNetworkMode =
                    $networkMode === 'host' ||
                    (is_string($networkMode) && (str_starts_with($networkMode, 'service:') || str_starts_with($networkMode, 'container:')));

                if ($serviceLabels->count() > 0) {
                    $removedLabels = collect([]);
                    $serviceLabels = $serviceLabels->filter(function ($serviceLabel, $serviceLabelName) use ($removedLabels) {
                        // Handle array values from YAML (e.g., "traefik.enable: true" becomes an array)
                        if (is_array($serviceLabel)) {
                            $removedLabels->put($serviceLabelName, $serviceLabel);

                            return false;
                        }
                        if (! str($serviceLabel)->contains('=')) {
                            $removedLabels->put($serviceLabelName, $serviceLabel);

                            return false;
                        }

                        return $serviceLabel;
                    });
                    foreach ($removedLabels as $removedLabelName => $removedLabel) {
                        // Convert array values to strings
                        if (is_array($removedLabel)) {
                            $removedLabel = (string) collect($removedLabel)->first();
                        }
                        $serviceLabels->push("$removedLabelName=$removedLabel");
                    }
                }
                $containerName = "$serviceName-{$resource->uuid}";

                // Decide if the service is a database
                $image = data_get_str($service, 'image');

                // Check for manually migrated services first (respects user's conversion choice)
                $migratedApp = ServiceApplication::where('name', $serviceName)
                    ->where('service_id', $resource->id)
                    ->where('is_migrated', true)
                    ->first();
                $migratedDb = ServiceDatabase::where('name', $serviceName)
                    ->where('service_id', $resource->id)
                    ->where('is_migrated', true)
                    ->first();

                if ($migratedApp || $migratedDb) {
                    // Use the migrated service type, ignoring image detection
                    $isDatabase = (bool) $migratedDb;
                    $savedService = $migratedApp ?: $migratedDb;
                } else {
                    // Use image detection for non-migrated services
                    $isDatabase = isDatabaseImage($image, $service);

                    // Create new serviceApplication or serviceDatabase
                    if ($isDatabase) {
                        if ($isNew) {
                            $savedService = ServiceDatabase::create([
                                'name' => $serviceName,
                                'image' => $image,
                                'service_id' => $resource->id,
                            ]);
                        } else {
                            $savedService = ServiceDatabase::where([
                                'name' => $serviceName,
                                'service_id' => $resource->id,
                            ])->first();
                            if (is_null($savedService)) {
                                $savedService = ServiceDatabase::create([
                                    'name' => $serviceName,
                                    'image' => $image,
                                    'service_id' => $resource->id,
                                ]);
                            }
                        }
                    } else {
                        if ($isNew) {
                            $savedService = ServiceApplication::create([
                                'name' => $serviceName,
                                'image' => $image,
                                'service_id' => $resource->id,
                            ]);
                        } else {
                            $savedService = ServiceApplication::where([
                                'name' => $serviceName,
                                'service_id' => $resource->id,
                            ])->first();
                            if (is_null($savedService)) {
                                $savedService = ServiceApplication::create([
                                    'name' => $serviceName,
                                    'image' => $image,
                                    'service_id' => $resource->id,
                                ]);
                            }
                        }
                    }
                }

                data_set($service, 'is_database', $isDatabase);

                // Check if image changed
                if ($savedService->image !== $image) {
                    $savedService->image = $image;
                    $savedService->save();
                }
                // Collect/create/update networks
                if ($serviceNetworks->count() > 0) {
                    foreach ($serviceNetworks as $networkName => $networkDetails) {
                        if ($networkName === 'default') {
                            continue;
                        }
                        // ignore alias
                        if ($networkDetails['aliases'] ?? false) {
                            continue;
                        }
                        $networkExists = $topLevelNetworks->contains(function ($value, $key) use ($networkName) {
                            return $value == $networkName || $key == $networkName;
                        });
                        if (! $networkExists) {
                            if (is_string($networkDetails) || is_int($networkDetails)) {
                                $topLevelNetworks->put($networkDetails, null);
                            }
                        }
                    }
                }

                // Collect/create/update ports
                $collectedPorts = collect([]);
                if ($servicePorts->count() > 0) {
                    foreach ($servicePorts as $sport) {
                        if (is_string($sport) || is_numeric($sport)) {
                            $collectedPorts->push($sport);
                        }
                        if (is_array($sport)) {
                            $target = data_get($sport, 'target');
                            $published = data_get($sport, 'published');
                            $protocol = data_get($sport, 'protocol');
                            $collectedPorts->push("$target:$published/$protocol");
                        }
                    }
                }
                $savedService->ports = $collectedPorts->implode(',');
                $savedService->save();

                if (! $hasValidNetworkMode) {
                    // Add Saturn Platform specific networks
                    $definedNetworkExists = $topLevelNetworks->contains(function ($value, $_) use ($definedNetwork) {
                        return $value == $definedNetwork;
                    });
                    if (! $definedNetworkExists) {
                        foreach ($definedNetwork as $network) {
                            $topLevelNetworks->put($network, [
                                'name' => $network,
                                'external' => true,
                            ]);
                        }
                    }
                    $networks = collect();
                    foreach ($serviceNetworks as $key => $serviceNetwork) {
                        if (gettype($serviceNetwork) === 'string') {
                            // networks:
                            //  - appwrite
                            $networks->put($serviceNetwork, null);
                        } elseif (gettype($serviceNetwork) === 'array') {
                            // networks:
                            //   default:
                            //     ipv4_address: 192.168.203.254
                            // $networks->put($serviceNetwork, null);
                            $networks->put($key, $serviceNetwork);
                        }
                    }
                    foreach ($definedNetwork as $key => $network) {
                        $networks->put($network, null);
                    }
                    data_set($service, 'networks', $networks->toArray());
                }

                // Collect/create/update volumes
                if ($serviceVolumes->count() > 0) {
                    $serviceVolumes = $serviceVolumes->map(function ($volume) use ($savedService, $topLevelVolumes) {
                        $type = null;
                        $source = null;
                        $target = null;
                        $content = null;
                        $isDirectory = false;
                        if (is_string($volume)) {
                            $source = str($volume)->before(':');
                            $target = str($volume)->after(':')->beforeLast(':');
                            if ($source->startsWith('./') || $source->startsWith('/') || $source->startsWith('~')) {
                                $type = str('bind');
                                // By default, we cannot determine if the bind is a directory or not, so we set it to directory
                                $isDirectory = true;
                            } else {
                                $type = str('volume');
                            }
                        } elseif (is_array($volume)) {
                            $type = data_get_str($volume, 'type');
                            $source = data_get_str($volume, 'source');
                            $target = data_get_str($volume, 'target');
                            $content = data_get($volume, 'content');
                            $isDirectory = (bool) data_get($volume, 'isDirectory', null) || (bool) data_get($volume, 'is_directory', null);
                            $foundConfig = $savedService->fileStorages()->whereMountPath($target)->first();
                            if ($foundConfig) {
                                $contentNotNull = data_get($foundConfig, 'content');
                                if ($contentNotNull) {
                                    $content = $contentNotNull;
                                }
                                $isDirectory = (bool) data_get($volume, 'isDirectory', null) || (bool) data_get($volume, 'is_directory', null);
                            }
                            if (is_null($isDirectory) && is_null($content)) {
                                // if isDirectory is not set & content is also not set, we assume it is a directory
                                $isDirectory = true;
                            }
                        }
                        if ($type?->value() === 'bind') {
                            if ($source->value() === '/var/run/docker.sock') {
                                return $volume;
                            }
                            if ($source->value() === '/tmp' || $source->value() === '/tmp/') {
                                return $volume;
                            }

                            LocalFileVolume::updateOrCreate(
                                [
                                    'mount_path' => $target,
                                    'resource_id' => $savedService->id,
                                    'resource_type' => get_class($savedService),
                                ],
                                [
                                    'fs_path' => $source,
                                    'mount_path' => $target,
                                    'content' => $content,
                                    'is_directory' => $isDirectory,
                                    'resource_id' => $savedService->id,
                                    'resource_type' => get_class($savedService),
                                ]
                            );
                        } elseif ($type->value() === 'volume') {
                            if ($topLevelVolumes->has($source->value())) {
                                $v = $topLevelVolumes->get($source->value());
                                if (data_get($v, 'driver_opts.type') === 'cifs') {
                                    return $volume;
                                }
                            }
                            $slugWithoutUuid = Str::slug($source, '-');
                            $name = "{$savedService->service->uuid}_{$slugWithoutUuid}";
                            if (is_string($volume)) {
                                $source = str($volume)->before(':');
                                $target = str($volume)->after(':')->beforeLast(':');
                                $source = $name;
                                $volume = "$source:$target";
                            } elseif (is_array($volume)) {
                                data_set($volume, 'source', $name);
                            }
                            $topLevelVolumes->put($name, [
                                'name' => $name,
                            ]);
                            LocalPersistentVolume::updateOrCreate(
                                [
                                    'mount_path' => $target,
                                    'resource_id' => $savedService->id,
                                    'resource_type' => get_class($savedService),
                                ],
                                [
                                    'name' => $name,
                                    'mount_path' => $target,
                                    'resource_id' => $savedService->id,
                                    'resource_type' => get_class($savedService),
                                ]
                            );
                        }
                        dispatch(new ServerFilesFromServerJob($savedService));

                        return $volume;
                    });
                    data_set($service, 'volumes', $serviceVolumes->toArray());
                }

                // convert - SESSION_SECRET: 123 to - SESSION_SECRET=123
                $convertedServiceVariables = collect([]);
                foreach ($serviceVariables as $variableName => $variable) {
                    if (is_numeric($variableName)) {
                        if (is_array($variable)) {
                            $key = str(collect($variable)->keys()->first());
                            $value = str(collect($variable)->values()->first());
                            $variable = "$key=$value";
                            $convertedServiceVariables->put($variableName, $variable);
                        } elseif (is_string($variable)) {
                            $convertedServiceVariables->put($variableName, $variable);
                        }
                    } elseif (is_string($variableName)) {
                        $convertedServiceVariables->put($variableName, $variable);
                    }
                }
                $serviceVariables = $convertedServiceVariables;
                // Get variables from the service
                foreach ($serviceVariables as $variableName => $variable) {
                    if (is_numeric($variableName)) {
                        if (is_array($variable)) {
                            // - SESSION_SECRET: 123
                            // - SESSION_SECRET:
                            $key = str(collect($variable)->keys()->first());
                            $value = str(collect($variable)->values()->first());
                        } else {
                            $variable = str($variable);
                            if ($variable->contains('=')) {
                                // - SESSION_SECRET=123
                                // - SESSION_SECRET=
                                $key = $variable->before('=');
                                $value = $variable->after('=');
                            } else {
                                // - SESSION_SECRET
                                $key = $variable;
                                $value = null;
                            }
                        }
                    } else {
                        // SESSION_SECRET: 123
                        // SESSION_SECRET:
                        $key = str($variableName);
                        $value = str($variable);
                    }
                    if ($key->startsWith('SERVICE_FQDN')) {
                        if ($isNew || $savedService->fqdn === null) {
                            $name = $key->after('SERVICE_FQDN_')->beforeLast('_')->lower();
                            $fqdn = generateFqdn($resource->server, "{$name->value()}-{$resource->uuid}");
                            if (substr_count($key->value(), '_') === 3) {
                                // SERVICE_FQDN_UMAMI_1000
                                $port = $key->afterLast('_');
                            } else {
                                $last = $key->afterLast('_');
                                if (is_numeric($last->value())) {
                                    // SERVICE_FQDN_3001
                                    $port = $last;
                                } else {
                                    // SERVICE_FQDN_UMAMI
                                    $port = null;
                                }
                            }
                            if ($port) {
                                $fqdn = "$fqdn:$port";
                            }
                            if (substr_count($key->value(), '_') >= 2) {
                                if ($value) {
                                    $path = $value->value();
                                } else {
                                    $path = null;
                                }
                                if ($generatedServiceFQDNS->count() > 0) {
                                    $alreadyGenerated = $generatedServiceFQDNS->has($key->value());
                                    if ($alreadyGenerated) {
                                        $fqdn = $generatedServiceFQDNS->get($key->value());
                                    } else {
                                        $generatedServiceFQDNS->put($key->value(), $fqdn);
                                    }
                                } else {
                                    $generatedServiceFQDNS->put($key->value(), $fqdn);
                                }
                                $fqdn = "$fqdn$path";
                            }

                            if (! $isDatabase) {
                                if ($savedService->fqdn) {
                                    data_set($savedService, 'fqdn', $savedService->fqdn.','.$fqdn);
                                } else {
                                    data_set($savedService, 'fqdn', $fqdn);
                                }
                                $savedService->save();
                            }
                            EnvironmentVariable::create([
                                'key' => $key,
                                'value' => $fqdn,
                                'resourceable_type' => get_class($resource),
                                'resourceable_id' => $resource->id,
                                'is_preview' => false,
                            ]);
                        }
                        // Caddy needs exact port in some cases.
                        if ($predefinedPort && ! $key->endsWith("_{$predefinedPort}")) {
                            $fqdns_exploded = str($savedService->fqdn)->explode(',');
                            if ($fqdns_exploded->count() > 1) {
                                continue;
                            }
                            $env = EnvironmentVariable::where([
                                'key' => $key,
                                'resourceable_type' => get_class($resource),
                                'resourceable_id' => $resource->id,
                            ])->first();
                            if ($env) {
                                $env_url = Url::fromString($savedService->fqdn);
                                $env_port = $env_url->getPort();
                                if ($env_port !== $predefinedPort) {
                                    $env_url = $env_url->withPort($predefinedPort);
                                    $savedService->fqdn = $env_url->__toString();
                                    $savedService->save();
                                }
                            }
                        }

                        // data_forget($service, "environment.$variableName");
                        // $yaml = data_forget($yaml, "services.$serviceName.environment.$variableName");
                        // if (count(data_get($yaml, 'services.' . $serviceName . '.environment')) === 0) {
                        //     $yaml = data_forget($yaml, "services.$serviceName.environment");
                        // }
                        continue;
                    }
                    if ($value?->startsWith('$')) {
                        $foundEnv = EnvironmentVariable::where([
                            'key' => $key,
                            'resourceable_type' => get_class($resource),
                            'resourceable_id' => $resource->id,
                        ])->first();
                        $value = replaceVariables($value);
                        $key = $value;
                        if ($value->startsWith('SERVICE_')) {
                            $foundEnv = EnvironmentVariable::where([
                                'key' => $key,
                                'resourceable_type' => get_class($resource),
                                'resourceable_id' => $resource->id,
                            ])->first();
                            ['command' => $command, 'forService' => $forService, 'generatedValue' => $generatedValue, 'port' => $port] = parseEnvVariable($value);
                            if (! is_null($command)) {
                                if ($command?->value() === 'FQDN' || $command?->value() === 'URL') {
                                    if (Str::lower($forService) === $serviceName) {
                                        $fqdn = generateFqdn($resource->server, $containerName);
                                    } else {
                                        $fqdn = generateFqdn($resource->server, Str::lower($forService).'-'.$resource->uuid);
                                    }
                                    if ($port) {
                                        $fqdn = "$fqdn:$port";
                                    }
                                    if ($foundEnv) {
                                        $fqdn = data_get($foundEnv, 'value');
                                        // if ($savedService->fqdn) {
                                        //     $savedServiceFqdn = Url::fromString($savedService->fqdn);
                                        //     $parsedFqdn = Url::fromString($fqdn);
                                        //     $savedServicePath = $savedServiceFqdn->getPath();
                                        //     $parsedFqdnPath = $parsedFqdn->getPath();
                                        //     if ($savedServicePath != $parsedFqdnPath) {
                                        //         $fqdn = $parsedFqdn->withPath($savedServicePath)->__toString();
                                        //         $foundEnv->value = $fqdn;
                                        //         $foundEnv->save();
                                        //     }
                                        // }
                                    } else {
                                        if ($command->value() === 'URL') {
                                            $fqdn = str($fqdn)->after('://')->value();
                                        }
                                        EnvironmentVariable::create([
                                            'key' => $key,
                                            'value' => $fqdn,
                                            'resourceable_type' => get_class($resource),
                                            'resourceable_id' => $resource->id,
                                            'is_preview' => false,
                                        ]);
                                    }
                                    if (! $isDatabase) {
                                        if ($command->value() === 'FQDN' && is_null($savedService->fqdn) && ! $foundEnv) {
                                            $savedService->fqdn = $fqdn;
                                            $savedService->save();
                                        }
                                        // Caddy needs exact port in some cases.
                                        if ($predefinedPort && ! $key->endsWith("_{$predefinedPort}") && $command?->value() === 'FQDN' && $resource->server->proxyType() === 'CADDY') {
                                            $fqdns_exploded = str($savedService->fqdn)->explode(',');
                                            if ($fqdns_exploded->count() > 1) {
                                                continue;
                                            }
                                            $env = EnvironmentVariable::where([
                                                'key' => $key,
                                                'resourceable_type' => get_class($resource),
                                                'resourceable_id' => $resource->id,
                                            ])->first();
                                            if ($env) {
                                                $env_url = Url::fromString($env->value);
                                                $env_port = $env_url->getPort();
                                                if ($env_port !== $predefinedPort) {
                                                    $env_url = $env_url->withPort($predefinedPort);
                                                    $savedService->fqdn = $env_url->__toString();
                                                    $savedService->save();
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $generatedValue = generateEnvValue($command, $resource);
                                    if (! $foundEnv) {
                                        EnvironmentVariable::create([
                                            'key' => $key,
                                            'value' => $generatedValue,
                                            'resourceable_type' => get_class($resource),
                                            'resourceable_id' => $resource->id,
                                            'is_preview' => false,
                                        ]);
                                    }
                                }
                            }
                        } else {
                            if ($value->contains(':-')) {
                                $key = $value->before(':');
                                $defaultValue = $value->after(':-');
                            } elseif ($value->contains('-')) {
                                $key = $value->before('-');
                                $defaultValue = $value->after('-');
                            } elseif ($value->contains(':?')) {
                                $key = $value->before(':');
                                $defaultValue = $value->after(':?');
                            } elseif ($value->contains('?')) {
                                $key = $value->before('?');
                                $defaultValue = $value->after('?');
                            } else {
                                $key = $value;
                                $defaultValue = null;
                            }
                            $foundEnv = EnvironmentVariable::where([
                                'key' => $key,
                                'resourceable_type' => get_class($resource),
                                'resourceable_id' => $resource->id,
                            ])->first();
                            if ($foundEnv) {
                                $defaultValue = data_get($foundEnv, 'value');
                            }
                            EnvironmentVariable::updateOrCreate([
                                'key' => $key,
                                'resourceable_type' => get_class($resource),
                                'resourceable_id' => $resource->id,
                            ], [
                                'value' => $defaultValue,
                                'resourceable_type' => get_class($resource),
                                'resourceable_id' => $resource->id,
                                'is_preview' => false,
                            ]);
                        }
                    }
                }
                // Add labels to the service
                if ($savedService->serviceType()) {
                    $fqdns = generateServiceSpecificFqdns($savedService);
                } else {
                    $fqdns = collect(data_get($savedService, 'fqdns'))->filter();
                }
                $defaultLabels = defaultLabels(
                    id: $resource->id,
                    name: $containerName,
                    projectName: $resource->project()->name,
                    resourceName: $resource->name,
                    type: 'service',
                    subType: $isDatabase ? 'database' : 'application',
                    subId: $savedService->id,
                    subName: $savedService->name,
                    environment: $resource->environment->name,
                );
                $serviceLabels = $serviceLabels->merge($defaultLabels);
                if (! $isDatabase && $fqdns->count() > 0) {
                    if ($fqdns) {
                        $shouldGenerateLabelsExactly = $resource->server->settings->generate_exact_labels;
                        if ($shouldGenerateLabelsExactly) {
                            switch ($resource->server->proxyType()) {
                                case ProxyTypes::TRAEFIK->value:
                                    $serviceLabels = $serviceLabels->merge(fqdnLabelsForTraefik(
                                        uuid: $resource->uuid,
                                        domains: $fqdns,
                                        is_force_https_enabled: true,
                                        serviceLabels: $serviceLabels,
                                        is_gzip_enabled: $savedService->isGzipEnabled(),
                                        is_stripprefix_enabled: $savedService->isStripprefixEnabled(),
                                        service_name: $serviceName,
                                        image: data_get($service, 'image')
                                    ));
                                    break;
                                case ProxyTypes::CADDY->value:
                                    $serviceLabels = $serviceLabels->merge(fqdnLabelsForCaddy(
                                        network: $resource->destination->network,
                                        uuid: $resource->uuid,
                                        domains: $fqdns,
                                        is_force_https_enabled: true,
                                        serviceLabels: $serviceLabels,
                                        is_gzip_enabled: $savedService->isGzipEnabled(),
                                        is_stripprefix_enabled: $savedService->isStripprefixEnabled(),
                                        service_name: $serviceName,
                                        image: data_get($service, 'image')
                                    ));
                                    break;
                            }
                        } else {
                            $serviceLabels = $serviceLabels->merge(fqdnLabelsForTraefik(
                                uuid: $resource->uuid,
                                domains: $fqdns,
                                is_force_https_enabled: true,
                                serviceLabels: $serviceLabels,
                                is_gzip_enabled: $savedService->isGzipEnabled(),
                                is_stripprefix_enabled: $savedService->isStripprefixEnabled(),
                                service_name: $serviceName,
                                image: data_get($service, 'image')
                            ));
                            $serviceLabels = $serviceLabels->merge(fqdnLabelsForCaddy(
                                network: $resource->destination->network,
                                uuid: $resource->uuid,
                                domains: $fqdns,
                                is_force_https_enabled: true,
                                serviceLabels: $serviceLabels,
                                is_gzip_enabled: $savedService->isGzipEnabled(),
                                is_stripprefix_enabled: $savedService->isStripprefixEnabled(),
                                service_name: $serviceName,
                                image: data_get($service, 'image')
                            ));
                        }
                    }
                }
                if ($resource->server->isLogDrainEnabled() && $savedService->isLogDrainEnabled()) {
                    data_set($service, 'logging', generate_fluentd_configuration());
                }
                if ($serviceLabels->count() > 0) {
                    if ($resource->is_container_label_escape_enabled) {
                        $serviceLabels = $serviceLabels->map(function ($value, $key) {
                            return escapeDollarSign($value);
                        });
                    }
                }
                data_set($service, 'labels', $serviceLabels->toArray());
                data_forget($service, 'is_database');
                if (! data_get($service, 'restart')) {
                    data_set($service, 'restart', RESTART_MODE);
                }
                if (data_get($service, 'restart') === 'no' || data_get($service, 'exclude_from_hc')) {
                    $savedService->update(['exclude_from_status' => true]);
                }
                data_set($service, 'container_name', $containerName);
                data_forget($service, 'volumes.*.content');
                data_forget($service, 'volumes.*.isDirectory');
                data_forget($service, 'volumes.*.is_directory');
                data_forget($service, 'exclude_from_hc');
                data_set($service, 'environment', $serviceVariables->toArray());
                updateCompose($savedService);

                return $service;
            });

            $envs_from_saturn = $resource->environment_variables()->get();
            $services = collect($services)->map(function ($service, $serviceName) use ($resource, $envs_from_saturn) {
                $serviceVariables = collect(data_get($service, 'environment', []));
                $parsedServiceVariables = collect([]);
                foreach ($serviceVariables as $key => $value) {
                    if (is_numeric($key)) {
                        $value = str($value);
                        if ($value->contains('=')) {
                            $key = $value->before('=')->value();
                            $value = $value->after('=')->value();
                        } else {
                            $key = $value->value();
                            $value = null;
                        }
                        $parsedServiceVariables->put($key, $value);
                    } else {
                        $parsedServiceVariables->put($key, $value);
                    }
                }
                $parsedServiceVariables->put('SATURN_RESOURCE_UUID', "{$resource->uuid}");
                $parsedServiceVariables->put('SATURN_CONTAINER_NAME', "$serviceName-{$resource->uuid}");

                // TODO: move this in a shared function
                if (! $parsedServiceVariables->has('SATURN_APP_NAME')) {
                    $parsedServiceVariables->put('SATURN_APP_NAME', "\"{$resource->name}\"");
                }
                if (! $parsedServiceVariables->has('SATURN_SERVER_IP')) {
                    $parsedServiceVariables->put('SATURN_SERVER_IP', "\"{$resource->destination->server->ip}\"");
                }
                if (! $parsedServiceVariables->has('SATURN_ENVIRONMENT_NAME')) {
                    $parsedServiceVariables->put('SATURN_ENVIRONMENT_NAME', "\"{$resource->environment->name}\"");
                }
                if (! $parsedServiceVariables->has('SATURN_PROJECT_NAME')) {
                    $parsedServiceVariables->put('SATURN_PROJECT_NAME', "\"{$resource->project()->name}\"");
                }

                $parsedServiceVariables = $parsedServiceVariables->map(function ($value, $key) use ($envs_from_saturn) {
                    if (! str($value)->startsWith('$')) {
                        $found_env = $envs_from_saturn->where('key', $key)->first();
                        if ($found_env) {
                            return $found_env->value;
                        }
                    }

                    return $value;
                });

                data_set($service, 'environment', $parsedServiceVariables->toArray());

                return $service;
            });
            $finalServices = [
                'services' => $services->toArray(),
                'volumes' => $topLevelVolumes->toArray(),
                'networks' => $topLevelNetworks->toArray(),
                'configs' => $topLevelConfigs->toArray(),
                'secrets' => $topLevelSecrets->toArray(),
            ];
            $yaml = data_forget($yaml, 'services.*.volumes.*.content');
            $resource->docker_compose_raw = Yaml::dump($yaml, 10, 2);
            $resource->docker_compose = Yaml::dump($finalServices, 10, 2);

            $resource->save();
            $resource->saveComposeConfigs();

            return collect($finalServices);
        } else {
            return collect([]);
        }
    } elseif ($resource->getMorphClass() === \App\Models\Application::class) {
        try {
            $yaml = Yaml::parse($resource->docker_compose_raw);
        } catch (\Exception) {
            return;
        }
        $server = $resource->destination->server;
        $topLevelVolumes = collect(data_get($yaml, 'volumes', []));
        if ($pull_request_id !== 0) {
            $topLevelVolumes = collect([]);
        }

        if ($topLevelVolumes->count() > 0) {
            $tempTopLevelVolumes = collect([]);
            foreach ($topLevelVolumes as $volumeName => $volume) {
                if (is_null($volume)) {
                    continue;
                }
                $tempTopLevelVolumes->put($volumeName, $volume);
            }
            $topLevelVolumes = collect($tempTopLevelVolumes);
        }

        $topLevelNetworks = collect(data_get($yaml, 'networks', []));
        $topLevelConfigs = collect(data_get($yaml, 'configs', []));
        $topLevelSecrets = collect(data_get($yaml, 'secrets', []));
        $services = data_get($yaml, 'services');

        $generatedServiceFQDNS = collect([]);
        if (is_null($resource->destination)) {
            $destination = $server->destinations()->first();
            if ($destination) {
                $resource->destination()->associate($destination);
                $resource->save();
            }
        }
        $definedNetwork = collect([$resource->uuid]);
        if ($pull_request_id !== 0) {
            $definedNetwork = collect(["{$resource->uuid}-$pull_request_id"]);
        }
        $services = collect($services)->map(function ($service, $serviceName) use ($topLevelVolumes, $topLevelNetworks, $definedNetwork, $isNew, $generatedServiceFQDNS, $resource, $server, $pull_request_id, $preview_id) {
            $serviceVolumes = collect(data_get($service, 'volumes', []));
            $servicePorts = collect(data_get($service, 'ports', []));
            $serviceNetworks = collect(data_get($service, 'networks', []));
            $serviceVariables = collect(data_get($service, 'environment', []));
            $serviceDependencies = collect(data_get($service, 'depends_on', []));
            $serviceLabels = collect(data_get($service, 'labels', []));
            $serviceBuildVariables = collect(data_get($service, 'build.args', []));
            $serviceVariables = $serviceVariables->merge($serviceBuildVariables);
            if ($serviceLabels->count() > 0) {
                $removedLabels = collect([]);
                $serviceLabels = $serviceLabels->filter(function ($serviceLabel, $serviceLabelName) use ($removedLabels) {
                    // Handle array values from YAML (e.g., "traefik.enable: true" becomes an array)
                    if (is_array($serviceLabel)) {
                        $removedLabels->put($serviceLabelName, $serviceLabel);

                        return false;
                    }
                    if (! str($serviceLabel)->contains('=')) {
                        $removedLabels->put($serviceLabelName, $serviceLabel);

                        return false;
                    }

                    return $serviceLabel;
                });
                foreach ($removedLabels as $removedLabelName => $removedLabel) {
                    // Convert array values to strings
                    if (is_array($removedLabel)) {
                        $removedLabel = (string) collect($removedLabel)->first();
                    }
                    $serviceLabels->push("$removedLabelName=$removedLabel");
                }
            }

            $baseName = generateApplicationContainerName($resource, $pull_request_id);
            $containerName = "$serviceName-$baseName";
            if ($resource->compose_parsing_version === '1') {
                if (count($serviceVolumes) > 0) {
                    $serviceVolumes = $serviceVolumes->map(function ($volume) use ($resource, $topLevelVolumes, $pull_request_id) {
                        if (is_string($volume)) {
                            $volume = str($volume);
                            if ($volume->contains(':') && ! $volume->startsWith('/')) {
                                $name = $volume->before(':');
                                $mount = $volume->after(':');
                                if ($name->startsWith('.') || $name->startsWith('~')) {
                                    $dir = base_configuration_dir().'/applications/'.$resource->uuid;
                                    if ($name->startsWith('.')) {
                                        $name = $name->replaceFirst('.', $dir);
                                    }
                                    if ($name->startsWith('~')) {
                                        $name = $name->replaceFirst('~', $dir);
                                    }
                                    if ($pull_request_id !== 0) {
                                        $name = addPreviewDeploymentSuffix($name, $pull_request_id);
                                    }
                                    $volume = str("$name:$mount");
                                } else {
                                    if ($pull_request_id !== 0) {
                                        $name = addPreviewDeploymentSuffix($name, $pull_request_id);
                                        $volume = str("$name:$mount");
                                        if ($topLevelVolumes->has($name)) {
                                            $v = $topLevelVolumes->get($name);
                                            if (data_get($v, 'driver_opts.type') === 'cifs') {
                                                // Do nothing
                                            } else {
                                                if (is_null(data_get($v, 'name'))) {
                                                    data_set($v, 'name', $name);
                                                    data_set($topLevelVolumes, $name, $v);
                                                }
                                            }
                                        } else {
                                            $topLevelVolumes->put($name, [
                                                'name' => $name,
                                            ]);
                                        }
                                    } else {
                                        if ($topLevelVolumes->has($name->value())) {
                                            $v = $topLevelVolumes->get($name->value());
                                            if (data_get($v, 'driver_opts.type') === 'cifs') {
                                                // Do nothing
                                            } else {
                                                if (is_null(data_get($v, 'name'))) {
                                                    data_set($topLevelVolumes, $name->value(), $v);
                                                }
                                            }
                                        } else {
                                            $topLevelVolumes->put($name->value(), [
                                                'name' => $name->value(),
                                            ]);
                                        }
                                    }
                                }
                            } else {
                                if ($volume->startsWith('/')) {
                                    $name = $volume->before(':');
                                    $mount = $volume->after(':');
                                    if ($pull_request_id !== 0) {
                                        $name = addPreviewDeploymentSuffix($name, $pull_request_id);
                                    }
                                    $volume = str("$name:$mount");
                                }
                            }
                        } elseif (is_array($volume)) {
                            $source = data_get($volume, 'source');
                            $target = data_get($volume, 'target');
                            $read_only = data_get($volume, 'read_only');
                            if ($source && $target) {
                                if ((str($source)->startsWith('.') || str($source)->startsWith('~'))) {
                                    $dir = base_configuration_dir().'/applications/'.$resource->uuid;
                                    if (str($source, '.')) {
                                        $source = str($source)->replaceFirst('.', $dir);
                                    }
                                    if (str($source, '~')) {
                                        $source = str($source)->replaceFirst('~', $dir);
                                    }
                                    if ($pull_request_id !== 0) {
                                        $source = addPreviewDeploymentSuffix($source, $pull_request_id);
                                    }
                                    if ($read_only) {
                                        data_set($volume, 'source', $source.':'.$target.':ro');
                                    } else {
                                        data_set($volume, 'source', $source.':'.$target);
                                    }
                                } else {
                                    if ($pull_request_id !== 0) {
                                        $source = addPreviewDeploymentSuffix($source, $pull_request_id);
                                    }
                                    if ($read_only) {
                                        data_set($volume, 'source', $source.':'.$target.':ro');
                                    } else {
                                        data_set($volume, 'source', $source.':'.$target);
                                    }
                                    if (! str($source)->startsWith('/')) {
                                        if ($topLevelVolumes->has($source)) {
                                            $v = $topLevelVolumes->get($source);
                                            if (data_get($v, 'driver_opts.type') === 'cifs') {
                                                // Do nothing
                                            } else {
                                                if (is_null(data_get($v, 'name'))) {
                                                    data_set($v, 'name', $source);
                                                    data_set($topLevelVolumes, $source, $v);
                                                }
                                            }
                                        } else {
                                            $topLevelVolumes->put($source, [
                                                'name' => $source,
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                        if (is_array($volume)) {
                            return data_get($volume, 'source');
                        }

                        return $volume->value();
                    });
                    data_set($service, 'volumes', $serviceVolumes->toArray());
                }
            } elseif ($resource->compose_parsing_version === '2') {
                if (count($serviceVolumes) > 0) {
                    $serviceVolumes = $serviceVolumes->map(function ($volume) use ($resource, $topLevelVolumes, $pull_request_id) {
                        if (is_string($volume)) {
                            $volume = str($volume);
                            if ($volume->contains(':') && ! $volume->startsWith('/')) {
                                $name = $volume->before(':');
                                $mount = $volume->after(':');
                                if ($name->startsWith('.') || $name->startsWith('~')) {
                                    $dir = base_configuration_dir().'/applications/'.$resource->uuid;
                                    if ($name->startsWith('.')) {
                                        $name = $name->replaceFirst('.', $dir);
                                    }
                                    if ($name->startsWith('~')) {
                                        $name = $name->replaceFirst('~', $dir);
                                    }
                                    if ($pull_request_id !== 0) {
                                        $name = addPreviewDeploymentSuffix($name, $pull_request_id);
                                    }
                                    $volume = str("$name:$mount");
                                } else {
                                    if ($pull_request_id !== 0) {
                                        $uuid = $resource->uuid;
                                        $name = $uuid.'-'.addPreviewDeploymentSuffix($name, $pull_request_id);
                                        $volume = str("$name:$mount");
                                        if ($topLevelVolumes->has($name)) {
                                            $v = $topLevelVolumes->get($name);
                                            if (data_get($v, 'driver_opts.type') === 'cifs') {
                                                // Do nothing
                                            } else {
                                                if (is_null(data_get($v, 'name'))) {
                                                    data_set($v, 'name', $name);
                                                    data_set($topLevelVolumes, $name, $v);
                                                }
                                            }
                                        } else {
                                            $topLevelVolumes->put($name, [
                                                'name' => $name,
                                            ]);
                                        }
                                    } else {
                                        $uuid = $resource->uuid;
                                        $name = str($uuid."-$name");
                                        $volume = str("$name:$mount");
                                        if ($topLevelVolumes->has($name->value())) {
                                            $v = $topLevelVolumes->get($name->value());
                                            if (data_get($v, 'driver_opts.type') === 'cifs') {
                                                // Do nothing
                                            } else {
                                                if (is_null(data_get($v, 'name'))) {
                                                    data_set($topLevelVolumes, $name->value(), $v);
                                                }
                                            }
                                        } else {
                                            $topLevelVolumes->put($name->value(), [
                                                'name' => $name->value(),
                                            ]);
                                        }
                                    }
                                }
                            } else {
                                if ($volume->startsWith('/')) {
                                    $name = $volume->before(':');
                                    $mount = $volume->after(':');
                                    if ($pull_request_id !== 0) {
                                        $name = addPreviewDeploymentSuffix($name, $pull_request_id);
                                    }
                                    $volume = str("$name:$mount");
                                }
                            }
                        } elseif (is_array($volume)) {
                            $source = data_get($volume, 'source');
                            $target = data_get($volume, 'target');
                            $read_only = data_get($volume, 'read_only');
                            if ($source && $target) {
                                $uuid = $resource->uuid;
                                if ((str($source)->startsWith('.') || str($source)->startsWith('~') || str($source)->startsWith('/'))) {
                                    $dir = base_configuration_dir().'/applications/'.$resource->uuid;
                                    if (str($source, '.')) {
                                        $source = str($source)->replaceFirst('.', $dir);
                                    }
                                    if (str($source, '~')) {
                                        $source = str($source)->replaceFirst('~', $dir);
                                    }
                                    if ($read_only) {
                                        data_set($volume, 'source', $source.':'.$target.':ro');
                                    } else {
                                        data_set($volume, 'source', $source.':'.$target);
                                    }
                                } else {
                                    if ($pull_request_id === 0) {
                                        $source = $uuid."-$source";
                                    } else {
                                        $source = $uuid.'-'.addPreviewDeploymentSuffix($source, $pull_request_id);
                                    }
                                    if ($read_only) {
                                        data_set($volume, 'source', $source.':'.$target.':ro');
                                    } else {
                                        data_set($volume, 'source', $source.':'.$target);
                                    }
                                    if (! str($source)->startsWith('/')) {
                                        if ($topLevelVolumes->has($source)) {
                                            $v = $topLevelVolumes->get($source);
                                            if (data_get($v, 'driver_opts.type') === 'cifs') {
                                                // Do nothing
                                            } else {
                                                if (is_null(data_get($v, 'name'))) {
                                                    data_set($v, 'name', $source);
                                                    data_set($topLevelVolumes, $source, $v);
                                                }
                                            }
                                        } else {
                                            $topLevelVolumes->put($source, [
                                                'name' => $source,
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                        if (is_array($volume)) {
                            return data_get($volume, 'source');
                        }
                        dispatch(new ServerFilesFromServerJob($resource));

                        return $volume->value();
                    });
                    data_set($service, 'volumes', $serviceVolumes->toArray());
                }
            }

            if ($pull_request_id !== 0 && count($serviceDependencies) > 0) {
                $serviceDependencies = $serviceDependencies->map(function ($dependency) use ($pull_request_id) {
                    return addPreviewDeploymentSuffix($dependency, $pull_request_id);
                });
                data_set($service, 'depends_on', $serviceDependencies->toArray());
            }

            // Decide if the service is a database
            $image = data_get_str($service, 'image');
            $isDatabase = isDatabaseImage($image, $service);
            data_set($service, 'is_database', $isDatabase);

            // Collect/create/update networks
            if ($serviceNetworks->count() > 0) {
                foreach ($serviceNetworks as $networkName => $networkDetails) {
                    if ($networkName === 'default') {
                        continue;
                    }
                    // ignore alias
                    if ($networkDetails['aliases'] ?? false) {
                        continue;
                    }
                    $networkExists = $topLevelNetworks->contains(function ($value, $key) use ($networkName) {
                        return $value == $networkName || $key == $networkName;
                    });
                    if (! $networkExists) {
                        if (is_string($networkDetails) || is_int($networkDetails)) {
                            $topLevelNetworks->put($networkDetails, null);
                        }
                    }
                }
            }
            // Collect/create/update ports
            $collectedPorts = collect([]);
            if ($servicePorts->count() > 0) {
                foreach ($servicePorts as $sport) {
                    if (is_string($sport) || is_numeric($sport)) {
                        $collectedPorts->push($sport);
                    }
                    if (is_array($sport)) {
                        $target = data_get($sport, 'target');
                        $published = data_get($sport, 'published');
                        $protocol = data_get($sport, 'protocol');
                        $collectedPorts->push("$target:$published/$protocol");
                    }
                }
            }
            $definedNetworkExists = $topLevelNetworks->contains(function ($value, $_) use ($definedNetwork) {
                return $value == $definedNetwork;
            });
            if (! $definedNetworkExists) {
                foreach ($definedNetwork as $network) {
                    if ($pull_request_id !== 0) {
                        $topLevelNetworks->put($network, [
                            'name' => $network,
                            'external' => true,
                        ]);
                    } else {
                        $topLevelNetworks->put($network, [
                            'name' => $network,
                            'external' => true,
                        ]);
                    }
                }
            }
            $networks = collect();
            foreach ($serviceNetworks as $key => $serviceNetwork) {
                if (gettype($serviceNetwork) === 'string') {
                    // networks:
                    //  - appwrite
                    $networks->put($serviceNetwork, null);
                } elseif (gettype($serviceNetwork) === 'array') {
                    // networks:
                    //   default:
                    //     ipv4_address: 192.168.203.254
                    // $networks->put($serviceNetwork, null);
                    $networks->put($key, $serviceNetwork);
                }
            }
            foreach ($definedNetwork as $key => $network) {
                $networks->put($network, null);
            }
            if (data_get($resource, 'settings.connect_to_docker_network')) {
                $network = $resource->destination->network;
                $networks->put($network, null);
                $topLevelNetworks->put($network, [
                    'name' => $network,
                    'external' => true,
                ]);
            }
            data_set($service, 'networks', $networks->toArray());
            // Get variables from the service
            foreach ($serviceVariables as $variableName => $variable) {
                if (is_numeric($variableName)) {
                    if (is_array($variable)) {
                        // - SESSION_SECRET: 123
                        // - SESSION_SECRET:
                        $key = str(collect($variable)->keys()->first());
                        $value = str(collect($variable)->values()->first());
                    } else {
                        $variable = str($variable);
                        if ($variable->contains('=')) {
                            // - SESSION_SECRET=123
                            // - SESSION_SECRET=
                            $key = $variable->before('=');
                            $value = $variable->after('=');
                        } else {
                            // - SESSION_SECRET
                            $key = $variable;
                            $value = null;
                        }
                    }
                } else {
                    // SESSION_SECRET: 123
                    // SESSION_SECRET:
                    $key = str($variableName);
                    $value = str($variable);
                }
                if ($key->startsWith('SERVICE_FQDN')) {
                    if ($isNew) {
                        $name = $key->after('SERVICE_FQDN_')->beforeLast('_')->lower();
                        $fqdn = generateFqdn($server, "{$name->value()}-{$resource->uuid}");
                        if (substr_count($key->value(), '_') === 3) {
                            // SERVICE_FQDN_UMAMI_1000
                            $port = $key->afterLast('_');
                        } else {
                            // SERVICE_FQDN_UMAMI
                            $port = null;
                        }
                        if ($port) {
                            $fqdn = "$fqdn:$port";
                        }
                        if (substr_count($key->value(), '_') >= 2) {
                            if ($value) {
                                $path = $value->value();
                            } else {
                                $path = null;
                            }
                            if ($generatedServiceFQDNS->count() > 0) {
                                $alreadyGenerated = $generatedServiceFQDNS->has($key->value());
                                if ($alreadyGenerated) {
                                    $fqdn = $generatedServiceFQDNS->get($key->value());
                                } else {
                                    $generatedServiceFQDNS->put($key->value(), $fqdn);
                                }
                            } else {
                                $generatedServiceFQDNS->put($key->value(), $fqdn);
                            }
                            $fqdn = "$fqdn$path";
                        }
                    }

                    continue;
                }
                if ($value?->startsWith('$')) {
                    $foundEnv = EnvironmentVariable::where([
                        'key' => $key,
                        'resourceable_type' => get_class($resource),
                        'resourceable_id' => $resource->id,
                        'is_preview' => false,
                    ])->first();
                    $value = replaceVariables($value);
                    $key = $value;
                    if ($value->startsWith('SERVICE_')) {
                        $foundEnv = EnvironmentVariable::where([
                            'key' => $key,
                            'resourceable_type' => get_class($resource),
                            'resourceable_id' => $resource->id,
                        ])->first();
                        ['command' => $command, 'forService' => $forService, 'generatedValue' => $generatedValue, 'port' => $port] = parseEnvVariable($value);
                        if (! is_null($command)) {
                            if ($command?->value() === 'FQDN' || $command?->value() === 'URL') {
                                if (Str::lower($forService) === $serviceName) {
                                    $fqdn = generateFqdn($server, $containerName);
                                } else {
                                    $fqdn = generateFqdn($server, Str::lower($forService).'-'.$resource->uuid);
                                }
                                if ($port) {
                                    $fqdn = "$fqdn:$port";
                                }
                                if ($foundEnv) {
                                    $fqdn = data_get($foundEnv, 'value');
                                } else {
                                    if ($command?->value() === 'URL') {
                                        $fqdn = str($fqdn)->after('://')->value();
                                    }
                                    EnvironmentVariable::create([
                                        'key' => $key,
                                        'value' => $fqdn,
                                        'resourceable_type' => get_class($resource),
                                        'resourceable_id' => $resource->id,
                                        'is_preview' => false,
                                    ]);
                                }
                            } else {
                                $generatedValue = generateEnvValue($command);
                                if (! $foundEnv) {
                                    EnvironmentVariable::create([
                                        'key' => $key,
                                        'value' => $generatedValue,
                                        'resourceable_type' => get_class($resource),
                                        'resourceable_id' => $resource->id,
                                        'is_preview' => false,
                                    ]);
                                }
                            }
                        }
                    } else {
                        if ($value->contains(':-')) {
                            $key = $value->before(':');
                            $defaultValue = $value->after(':-');
                        } elseif ($value->contains('-')) {
                            $key = $value->before('-');
                            $defaultValue = $value->after('-');
                        } elseif ($value->contains(':?')) {
                            $key = $value->before(':');
                            $defaultValue = $value->after(':?');
                        } elseif ($value->contains('?')) {
                            $key = $value->before('?');
                            $defaultValue = $value->after('?');
                        } else {
                            $key = $value;
                            $defaultValue = null;
                        }
                        $foundEnv = EnvironmentVariable::where([
                            'key' => $key,
                            'resourceable_type' => get_class($resource),
                            'resourceable_id' => $resource->id,
                            'is_preview' => false,
                        ])->first();
                        if ($foundEnv) {
                            $defaultValue = data_get($foundEnv, 'value');
                        }
                        if ($foundEnv) {
                            $foundEnv->update([
                                'key' => $key,
                                'resourceable_type' => get_class($resource),
                                'resourceable_id' => $resource->id,
                                'value' => $defaultValue,
                            ]);
                        } else {
                            EnvironmentVariable::create([
                                'key' => $key,
                                'value' => $defaultValue,
                                'resourceable_type' => get_class($resource),
                                'resourceable_id' => $resource->id,
                                'is_preview' => false,
                            ]);
                        }
                    }
                }
            }
            // Add labels to the service
            if ($resource->serviceType()) {
                $fqdns = generateServiceSpecificFqdns($resource);
            } else {
                $domains = collect(json_decode($resource->docker_compose_domains)) ?? [];
                if ($domains) {
                    $fqdns = data_get($domains, "$serviceName.domain");
                    if ($fqdns) {
                        $fqdns = str($fqdns)->explode(',');
                        if ($pull_request_id !== 0) {
                            $preview = $resource->previews()->find($preview_id);
                            $docker_compose_domains = collect(json_decode(data_get($preview, 'docker_compose_domains')));
                            if ($docker_compose_domains->count() > 0) {
                                $found_fqdn = data_get($docker_compose_domains, "$serviceName.domain");
                                if ($found_fqdn) {
                                    $fqdns = collect($found_fqdn);
                                } else {
                                    $fqdns = collect([]);
                                }
                            } else {
                                $fqdns = $fqdns->map(function ($fqdn) use ($pull_request_id, $resource) {
                                    $preview = ApplicationPreview::findPreviewByApplicationAndPullId($resource->id, $pull_request_id);
                                    $url = Url::fromString($fqdn);
                                    $template = $resource->preview_url_template;
                                    $host = $url->getHost();
                                    $schema = $url->getScheme();
                                    $random = new Cuid2;
                                    $preview_fqdn = str_replace('{{random}}', $random, $template);
                                    $preview_fqdn = str_replace('{{domain}}', $host, $preview_fqdn);
                                    $preview_fqdn = str_replace('{{pr_id}}', $pull_request_id, $preview_fqdn);
                                    $preview_fqdn = "$schema://$preview_fqdn";
                                    $preview->fqdn = $preview_fqdn;
                                    $preview->save();

                                    return $preview_fqdn;
                                });
                            }
                        }
                        $shouldGenerateLabelsExactly = $server->settings->generate_exact_labels;
                        if ($shouldGenerateLabelsExactly) {
                            switch ($server->proxyType()) {
                                case ProxyTypes::TRAEFIK->value:
                                    $serviceLabels = $serviceLabels->merge(
                                        fqdnLabelsForTraefik(
                                            uuid: $resource->uuid,
                                            domains: $fqdns,
                                            serviceLabels: $serviceLabels,
                                            generate_unique_uuid: $resource->build_pack === 'dockercompose',
                                            image: data_get($service, 'image'),
                                            is_force_https_enabled: $resource->isForceHttpsEnabled(),
                                            is_gzip_enabled: $resource->isGzipEnabled(),
                                            is_stripprefix_enabled: $resource->isStripprefixEnabled(),
                                        )
                                    );
                                    break;
                                case ProxyTypes::CADDY->value:
                                    $serviceLabels = $serviceLabels->merge(
                                        fqdnLabelsForCaddy(
                                            network: $resource->destination->network,
                                            uuid: $resource->uuid,
                                            domains: $fqdns,
                                            serviceLabels: $serviceLabels,
                                            image: data_get($service, 'image'),
                                            is_force_https_enabled: $resource->isForceHttpsEnabled(),
                                            is_gzip_enabled: $resource->isGzipEnabled(),
                                            is_stripprefix_enabled: $resource->isStripprefixEnabled(),
                                        )
                                    );
                                    break;
                            }
                        } else {
                            $serviceLabels = $serviceLabels->merge(
                                fqdnLabelsForTraefik(
                                    uuid: $resource->uuid,
                                    domains: $fqdns,
                                    serviceLabels: $serviceLabels,
                                    generate_unique_uuid: $resource->build_pack === 'dockercompose',
                                    image: data_get($service, 'image'),
                                    is_force_https_enabled: $resource->isForceHttpsEnabled(),
                                    is_gzip_enabled: $resource->isGzipEnabled(),
                                    is_stripprefix_enabled: $resource->isStripprefixEnabled(),
                                )
                            );
                            $serviceLabels = $serviceLabels->merge(
                                fqdnLabelsForCaddy(
                                    network: $resource->destination->network,
                                    uuid: $resource->uuid,
                                    domains: $fqdns,
                                    serviceLabels: $serviceLabels,
                                    image: data_get($service, 'image'),
                                    is_force_https_enabled: $resource->isForceHttpsEnabled(),
                                    is_gzip_enabled: $resource->isGzipEnabled(),
                                    is_stripprefix_enabled: $resource->isStripprefixEnabled(),
                                )
                            );
                        }
                    }
                }
            }

            $defaultLabels = defaultLabels(
                id: $resource->id,
                name: $containerName,
                projectName: $resource->project()->name,
                resourceName: $resource->name,
                environment: $resource->environment->name,
                pull_request_id: $pull_request_id,
                type: 'application'
            );
            $serviceLabels = $serviceLabels->merge($defaultLabels);

            if ($server->isLogDrainEnabled()) {
                if ($resource instanceof Application && $resource->isLogDrainEnabled()) {
                    data_set($service, 'logging', generate_fluentd_configuration());
                }
            }
            if ($serviceLabels->count() > 0) {
                if ($resource->settings->is_container_label_escape_enabled) {
                    $serviceLabels = $serviceLabels->map(function ($value, $key) {
                        return escapeDollarSign($value);
                    });
                }
            }
            data_set($service, 'labels', $serviceLabels->toArray());
            data_forget($service, 'is_database');
            if (! data_get($service, 'restart')) {
                data_set($service, 'restart', RESTART_MODE);
            }
            data_set($service, 'container_name', $containerName);
            data_forget($service, 'volumes.*.content');
            data_forget($service, 'volumes.*.isDirectory');
            data_forget($service, 'volumes.*.is_directory');
            data_forget($service, 'exclude_from_hc');
            data_set($service, 'environment', $serviceVariables->toArray());

            return $service;
        });
        if ($pull_request_id !== 0) {
            $services->each(function ($service, $serviceName) use ($pull_request_id, $services) {
                $services[addPreviewDeploymentSuffix($serviceName, $pull_request_id)] = $service;
                data_forget($services, $serviceName);
            });
        }
        $finalServices = [
            'services' => $services->toArray(),
            'volumes' => $topLevelVolumes->toArray(),
            'networks' => $topLevelNetworks->toArray(),
            'configs' => $topLevelConfigs->toArray(),
            'secrets' => $topLevelSecrets->toArray(),
        ];
        $resource->docker_compose_raw = Yaml::dump($yaml, 10, 2);
        $resource->docker_compose = Yaml::dump($finalServices, 10, 2);
        data_forget($resource, 'environment_variables');
        data_forget($resource, 'environment_variables_preview');
        $resource->save();

        return collect($finalServices);
    }
}
