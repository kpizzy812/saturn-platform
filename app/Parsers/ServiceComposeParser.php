<?php

namespace App\Parsers;

use App\Enums\ProxyTypes;
use App\Jobs\ServerFilesFromServerJob;
use App\Models\LocalFileVolume;
use App\Models\LocalPersistentVolume;
use App\Models\Service;
use App\Models\ServiceApplication;
use App\Models\ServiceDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Symfony\Component\Yaml\Yaml;

/**
 * Parser for Service Docker Compose files.
 *
 * Handles parsing and transformation of docker-compose.yml files for Service resources,
 * including environment variable processing, volume management, network configuration,
 * and label generation for Traefik/Caddy proxies.
 */
class ServiceComposeParser
{
    private Service $resource;

    private string $uuid;

    private mixed $server;

    private Collection $allServices;

    private Collection $topLevel;

    private Collection $baseNetwork;

    private Collection $allMagicEnvironments;

    private string $originalCompose;

    private array $services;

    /**
     * Parse a Docker Compose file for a Service resource.
     *
     * @param  Service  $resource  The service resource
     * @return Collection The parsed compose configuration
     */
    public static function parse(Service $resource): Collection
    {
        $parser = new self($resource);

        return $parser->doParse();
    }

    private function __construct(Service $resource)
    {
        $this->resource = $resource;
        $this->uuid = data_get($resource, 'uuid');
        $this->server = data_get($resource, 'server');
        $this->allServices = get_service_templates();
        $this->allMagicEnvironments = collect([]);
    }

    /**
     * Short identifier for subdomain generation (first 8 chars of UUID).
     * Full UUID is still used for Docker networks, project names, etc.
     */
    private function subdomainSlug(): string
    {
        return substr($this->uuid, 0, 8);
    }

    private function doParse(): Collection
    {
        $compose = data_get($this->resource, 'docker_compose_raw');
        $this->originalCompose = $compose;

        if (! $compose) {
            return collect([]);
        }

        try {
            $yaml = Yaml::parse($compose);
        } catch (\Exception) {
            return collect([]);
        }

        $this->services = data_get($yaml, 'services', []);
        $this->initializeTopLevel($yaml);
        $this->initializeBaseNetwork();

        // Generate SERVICE_NAME variables
        $serviceNameEnvironments = generateDockerComposeServiceName($this->services);

        // First pass: presave services and collect magic environments
        $this->presaveServices();
        $this->processMagicEnvironments();

        // Get log drain enabled map
        $serviceAppsLogDrainEnabledMap = $this->resource->applications()->get()->keyBy('name')->map(function ($app) {
            return $app->isLogDrainEnabled();
        });

        // Second pass: parse services
        $parsedServices = $this->parseServices($serviceNameEnvironments, $serviceAppsLogDrainEnabledMap);
        $this->topLevel->put('services', $parsedServices);

        // Sort and clean top level
        $this->finalizeTopLevel();

        // Save the resource
        $this->saveResource();

        return $this->topLevel;
    }

    private function initializeTopLevel(array $yaml): void
    {
        $this->topLevel = collect([
            'volumes' => collect(data_get($yaml, 'volumes', [])),
            'networks' => collect(data_get($yaml, 'networks', [])),
            'configs' => collect(data_get($yaml, 'configs', [])),
            'secrets' => collect(data_get($yaml, 'secrets', [])),
        ]);

        // Filter out null volumes
        if ($this->topLevel->get('volumes')->count() > 0) {
            $temp = collect([]);
            foreach ($this->topLevel['volumes'] as $volumeName => $volume) {
                if (! is_null($volume)) {
                    $temp->put($volumeName, $volume);
                }
            }
            $this->topLevel['volumes'] = $temp;
        }
    }

    private function initializeBaseNetwork(): void
    {
        $this->baseNetwork = collect([$this->uuid]);
    }

    private function presaveServices(): void
    {
        foreach ($this->services as $serviceName => $service) {
            $this->validateServiceName($serviceName);

            $image = data_get_str($service, 'image');
            $savedService = $this->findOrCreateSavedService($serviceName, $image, $service);

            if ($savedService->image !== $image->value()) {
                $savedService->image = $image->value();
                $savedService->save();
            }
        }
    }

    private function validateServiceName(string $serviceName): void
    {
        try {
            validateShellSafePath($serviceName, 'service name');
        } catch (\Exception $e) {
            throw new \Exception(
                'Invalid Docker Compose service name: '.$e->getMessage().
                ' Service names must not contain shell metacharacters.'
            );
        }
    }

    private function findOrCreateSavedService(string $serviceName, mixed $image, array $service): ServiceApplication|ServiceDatabase
    {
        // Check for manually migrated services first
        $migratedApp = ServiceApplication::where('name', $serviceName)
            ->where('service_id', $this->resource->id)
            ->where('is_migrated', true)
            ->first();
        $migratedDb = ServiceDatabase::where('name', $serviceName)
            ->where('service_id', $this->resource->id)
            ->where('is_migrated', true)
            ->first();

        if ($migratedApp || $migratedDb) {
            return $migratedApp ?: $migratedDb;
        }

        $isDatabase = isDatabaseImage($image, $service);

        if ($isDatabase) {
            $applicationFound = ServiceApplication::where('name', $serviceName)
                ->where('service_id', $this->resource->id)
                ->first();

            if ($applicationFound) {
                return $applicationFound;
            }

            return ServiceDatabase::firstOrCreate([
                'name' => $serviceName,
                'service_id' => $this->resource->id,
            ]);
        }

        return ServiceApplication::firstOrCreate([
            'name' => $serviceName,
            'service_id' => $this->resource->id,
        ], [
            'is_gzip_enabled' => true,
        ]);
    }

    private function processMagicEnvironments(): void
    {
        foreach ($this->services as $serviceName => $service) {
            $magicEnvironments = collect([]);
            $image = data_get_str($service, 'image');
            $environment = collect(data_get($service, 'environment', []));
            $buildArgs = collect(data_get($service, 'build.args', []));
            $environment = $environment->merge($buildArgs);
            $environment = convertToKeyValueCollection($environment);

            $savedService = $this->findOrCreateSavedService($serviceName, $image, $service);

            // Collect magic environments from values
            foreach ($environment as $key => $value) {
                $key = str($key);
                $value = str($value);
                $regex = '/\$(\{?([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\}?)/';
                preg_match_all($regex, $value, $valueMatches);

                if (count($valueMatches[1]) > 0) {
                    foreach ($valueMatches[1] as $match) {
                        $match = replaceVariables($match);
                        if ($match->startsWith('SERVICE_')) {
                            if (! $magicEnvironments->has($match->value())) {
                                $magicEnvironments->put($match->value(), '');
                            }
                        }
                    }
                }

                // Process SERVICE_FQDN_ and SERVICE_URL_ variables
                if ($key->startsWith('SERVICE_FQDN_') || $key->startsWith('SERVICE_URL_')) {
                    $this->processServiceFqdnUrlVariable($key, $value, $savedService, $serviceName);
                }
            }

            $this->allMagicEnvironments = $this->allMagicEnvironments->merge($magicEnvironments);

            // Generate environment variables for magic environments
            if ($magicEnvironments->count() > 0) {
                $this->generateMagicEnvironmentVariables($magicEnvironments, $savedService);
            }
        }
    }

    private function processServiceFqdnUrlVariable(
        Stringable $key,
        Stringable $value,
        ServiceApplication|ServiceDatabase $savedService,
        string $originalServiceName
    ): void {
        $parsed = parseServiceEnvironmentVariable($key->value());
        $port = $parsed['port'];
        $fqdnFor = $parsed['service_name'];

        // Extract service name preserving original case from template
        $strKey = str($key->value());
        if ($parsed['has_port']) {
            if ($strKey->startsWith('SERVICE_URL_')) {
                $serviceName = $strKey->after('SERVICE_URL_')->beforeLast('_')->value();
            } elseif ($strKey->startsWith('SERVICE_FQDN_')) {
                $serviceName = $strKey->after('SERVICE_FQDN_')->beforeLast('_')->value();
            } else {
                return;
            }
        } else {
            if ($strKey->startsWith('SERVICE_URL_')) {
                $serviceName = $strKey->after('SERVICE_URL_')->value();
            } elseif ($strKey->startsWith('SERVICE_FQDN_')) {
                $serviceName = $strKey->after('SERVICE_FQDN_')->value();
            } else {
                return;
            }
        }

        $isServiceApplication = $savedService instanceof ServiceApplication;

        if ($isServiceApplication && blank($savedService->fqdn)) {
            $fqdn = generateFqdn(server: $this->server, random: "$fqdnFor-{$this->subdomainSlug()}", parserVersion: (int) $this->resource->compose_parsing_version);
            $url = generateUrl($this->server, "$fqdnFor-{$this->subdomainSlug()}");
        } elseif ($isServiceApplication) {
            $fqdn = str($savedService->fqdn)->after('://')->before(':')->prepend(str($savedService->fqdn)->before('://')->append('://'))->value();
            $url = str($savedService->fqdn)->after('://')->before(':')->prepend(str($savedService->fqdn)->before('://')->append('://'))->value();
        } else {
            $fqdn = generateFqdn(server: $this->server, random: "$fqdnFor-{$this->subdomainSlug()}", parserVersion: (int) $this->resource->compose_parsing_version);
            $url = generateUrl($this->server, "$fqdnFor-{$this->subdomainSlug()}");
        }

        $fqdnValueForEnv = str($fqdn)->after('://')->value();

        if ($value->startsWith('/')) {
            $path = $value->value();
            if ($path !== '/') {
                if (! str($fqdn)->endsWith($path)) {
                    $fqdn = "$fqdn$path";
                }
                if (! str($url)->endsWith($path)) {
                    $url = "$url$path";
                }
                if (! str($fqdnValueForEnv)->endsWith($path)) {
                    $fqdnValueForEnv = "$fqdnValueForEnv$path";
                }
            }
        }

        $urlWithPort = $url;
        $fqdnValueForEnvWithPort = $fqdnValueForEnv;
        if ($fqdn && $port) {
            $fqdnValueForEnvWithPort = "$fqdnValueForEnv:$port";
        }
        if ($url && $port) {
            $urlWithPort = "$url:$port";
        }

        // Only save fqdn to ServiceApplication (without port - Traefik handles internal routing)
        if ($isServiceApplication && is_null($savedService->fqdn)) {
            $savedService->fqdn = $url;
            $savedService->save();
        }

        // Create both SERVICE_URL and SERVICE_FQDN pairs
        $this->resource->environment_variables()->updateOrCreate([
            'key' => "SERVICE_FQDN_{$serviceName}",
            'resourceable_type' => get_class($this->resource),
            'resourceable_id' => $this->resource->id,
        ], [
            'value' => $fqdnValueForEnv,
            'is_preview' => false,
        ]);

        $this->resource->environment_variables()->updateOrCreate([
            'key' => "SERVICE_URL_{$serviceName}",
            'resourceable_type' => get_class($this->resource),
            'resourceable_id' => $this->resource->id,
        ], [
            'value' => $url,
            'is_preview' => false,
        ]);

        if ($parsed['has_port'] && $port) {
            $this->resource->environment_variables()->updateOrCreate([
                'key' => "SERVICE_FQDN_{$serviceName}_{$port}",
                'resourceable_type' => get_class($this->resource),
                'resourceable_id' => $this->resource->id,
            ], [
                'value' => $fqdnValueForEnvWithPort,
                'is_preview' => false,
            ]);

            $this->resource->environment_variables()->updateOrCreate([
                'key' => "SERVICE_URL_{$serviceName}_{$port}",
                'resourceable_type' => get_class($this->resource),
                'resourceable_id' => $this->resource->id,
            ], [
                'value' => $urlWithPort,
                'is_preview' => false,
            ]);
        }
    }

    private function generateMagicEnvironmentVariables(Collection $magicEnvironments, ServiceApplication|ServiceDatabase $savedService): void
    {
        foreach ($magicEnvironments as $key => $value) {
            $key = str($key);
            $value = replaceVariables($value);
            $command = parseCommandFromMagicEnvVariable($key);

            if ($command->value() === 'FQDN') {
                $this->generateFqdnVariable($key, $savedService);
            } elseif ($command->value() === 'URL') {
                $this->generateUrlVariable($key, $savedService);
            } else {
                $generatedValue = generateEnvValue($command, $this->resource);
                $this->resource->environment_variables()->firstOrCreate([
                    'key' => $key->value(),
                    'resourceable_type' => get_class($this->resource),
                    'resourceable_id' => $this->resource->id,
                ], [
                    'value' => $generatedValue,
                    'is_preview' => false,
                ]);
            }
        }
    }

    private function generateFqdnVariable(Stringable $key, ServiceApplication|ServiceDatabase $savedService): void
    {
        $fqdnFor = $key->after('SERVICE_FQDN_')->lower()->value();
        $fqdn = generateFqdn(server: $this->server, random: str($fqdnFor)->replace('_', '-')->value()."-{$this->subdomainSlug()}", parserVersion: (int) $this->resource->compose_parsing_version);
        $url = generateUrl(server: $this->server, random: str($fqdnFor)->replace('_', '-')->value()."-{$this->subdomainSlug()}");

        $envExists = $this->resource->environment_variables()->where('key', $key->value())->first();
        $portSuffixedExists = $this->resource->environment_variables()
            ->where('key', 'LIKE', $key->value().'_%')
            ->whereRaw('key ~ ?', ['^'.$key->value().'_[0-9]+$'])
            ->exists();
        $serviceExists = ServiceApplication::where('name', str($fqdnFor)->replace('_', '-')->value())
            ->where('service_id', $this->resource->id)
            ->first();

        $fqdnHasPort = $serviceExists && str($serviceExists->fqdn)->contains(':') && str($serviceExists->fqdn)->afterLast(':')->isMatch('/^\d+$/');
        $isCurrentService = $serviceExists && $serviceExists->id === $savedService->id;

        if (! $envExists && ! $portSuffixedExists && ! $fqdnHasPort && $isCurrentService && (data_get($serviceExists, 'name') === str($fqdnFor)->replace('_', '-')->value())) {
            $serviceExists->fqdn = $url;
            $serviceExists->save();
        }

        $this->resource->environment_variables()->firstOrCreate([
            'key' => $key->value(),
            'resourceable_type' => get_class($this->resource),
            'resourceable_id' => $this->resource->id,
        ], [
            'value' => $fqdn,
            'is_preview' => false,
        ]);
    }

    private function generateUrlVariable(Stringable $key, ServiceApplication|ServiceDatabase $savedService): void
    {
        $urlFor = $key->after('SERVICE_URL_')->lower()->value();
        $url = generateUrl(server: $this->server, random: str($urlFor)->replace('_', '-')->value()."-{$this->subdomainSlug()}");

        $envExists = $this->resource->environment_variables()->where('key', $key->value())->first();
        $portSuffixedExists = $this->resource->environment_variables()
            ->where('key', 'LIKE', $key->value().'_%')
            ->whereRaw('key ~ ?', ['^'.$key->value().'_[0-9]+$'])
            ->exists();
        $serviceExists = ServiceApplication::where('name', str($urlFor)->replace('_', '-')->value())
            ->where('service_id', $this->resource->id)
            ->first();

        $fqdnHasPort = $serviceExists && str($serviceExists->fqdn)->contains(':') && str($serviceExists->fqdn)->afterLast(':')->isMatch('/^\d+$/');
        $isCurrentService = $serviceExists && $serviceExists->id === $savedService->id;

        if (! $envExists && ! $portSuffixedExists && ! $fqdnHasPort && $isCurrentService && (data_get($serviceExists, 'name') === str($urlFor)->replace('_', '-')->value())) {
            $serviceExists->fqdn = $url;
            $serviceExists->save();
        }

        $this->resource->environment_variables()->firstOrCreate([
            'key' => $key->value(),
            'resourceable_type' => get_class($this->resource),
            'resourceable_id' => $this->resource->id,
        ], [
            'value' => $url,
            'is_preview' => false,
        ]);
    }

    private function parseServices(Collection $serviceNameEnvironments, Collection $serviceAppsLogDrainEnabledMap): Collection
    {
        $parsedServices = collect([]);

        foreach ($this->services as $serviceName => $service) {
            $payload = $this->parseService($serviceName, $service, $serviceNameEnvironments, $serviceAppsLogDrainEnabledMap);
            $parsedServices->put($serviceName, $payload);
        }

        return $parsedServices;
    }

    private function parseService(
        string $serviceName,
        array $service,
        Collection $serviceNameEnvironments,
        Collection $serviceAppsLogDrainEnabledMap
    ): Collection {
        $image = data_get_str($service, 'image');
        $restart = data_get_str($service, 'restart', RESTART_MODE);
        $logging = $this->getLoggingConfig($service, $serviceName, $serviceAppsLogDrainEnabledMap);
        $volumes = collect(data_get($service, 'volumes', []));
        $networks = collect(data_get($service, 'networks', []));
        $useNetworkMode = data_get($service, 'network_mode') !== null;
        $dependsOn = collect(data_get($service, 'depends_on', []));
        $labels = $this->normalizeLabels(data_get($service, 'labels', []));
        $environment = $this->getServiceEnvironment($service);
        $ports = collect(data_get($service, 'ports', []));
        $saturnEnvironments = collect([]);

        $savedService = $this->findOrCreateSavedService($serviceName, $image, $service);
        $isDatabase = $savedService instanceof ServiceDatabase;
        $containerName = "$serviceName-{$this->resource->uuid}";
        $predefinedPort = $this->getPredefinedPort($serviceName, $service);
        $fileStorages = $savedService->fileStorages();

        // Update image if changed and handle pocketbase
        if ($savedService->image !== $image->value()) {
            $savedService->image = $image->value();
            $savedService->save();
        }
        if (str($savedService->image)->contains('pocketbase') && $savedService->is_gzip_enabled) {
            $savedService->is_gzip_enabled = false;
            $savedService->save();
        }

        // Parse volumes
        $volumesParsed = $this->parseVolumes($volumes, $fileStorages, $savedService);

        // Handle networks
        if (! $useNetworkMode) {
            $networks = $this->processNetworks($networks);
        }

        // Collect and save ports
        $collectedPorts = $this->collectPorts($ports);
        $savedService->ports = $collectedPorts->implode(',');
        $savedService->save();

        // Process environment variables
        $environment = $this->processEnvironmentVariables($environment);

        // Add Saturn environment variables
        $this->addSaturnEnvironmentVariables($saturnEnvironments, $containerName);

        // Get FQDNs for this service
        $fqdns = $this->getServiceFqdns($savedService);

        // Generate default labels
        $defaultLabels = defaultLabels(
            id: $this->resource->id,
            name: $containerName,
            projectName: $this->resource->project()->name,
            resourceName: $this->resource->name,
            type: 'service',
            subType: $isDatabase ? 'database' : 'application',
            subId: $savedService->id,
            subName: $savedService->human_name ?? $savedService->name,
            environment: $this->resource->environment->name,
        );

        // Add SATURN_FQDN & SATURN_URL
        if (! $isDatabase && $fqdns->count() > 0) {
            $this->addFqdnEnvironmentVariables($saturnEnvironments, $fqdns);
        }

        add_saturn_default_environment_variables($this->resource, $saturnEnvironments, $this->resource->environment_variables);

        // Filter and map environment
        $environment = $this->finalizeEnvironment($environment);

        // Merge labels
        $serviceLabels = $this->processServiceLabels($labels, $defaultLabels, $fqdns, $isDatabase, $serviceName, $image, $predefinedPort);

        // Handle exclude from status
        if (data_get($service, 'restart') === 'no' || data_get($service, 'exclude_from_hc')) {
            $savedService->update(['exclude_from_status' => true]);
        }

        // Clean up volume data
        data_forget($service, 'volumes.*.content');
        data_forget($service, 'volumes.*.isDirectory');
        data_forget($service, 'volumes.*.is_directory');
        data_forget($service, 'exclude_from_hc');

        $volumesParsed = $this->cleanVolumeData($volumesParsed);

        // Build payload
        $payload = $this->buildServicePayload(
            $service,
            $containerName,
            $restart,
            $serviceLabels,
            $useNetworkMode,
            $networks,
            $ports,
            $volumesParsed,
            $environment,
            $saturnEnvironments,
            $serviceNameEnvironments,
            $logging,
            $dependsOn
        );

        return $payload;
    }

    private function getLoggingConfig(array $service, string $serviceName, Collection $serviceAppsLogDrainEnabledMap): ?array
    {
        $logging = data_get($service, 'logging');

        if ($this->server->isLogDrainEnabled() && $serviceAppsLogDrainEnabledMap->get($serviceName)) {
            $logging = generate_fluentd_configuration();
        }

        return $logging;
    }

    private function normalizeLabels(mixed $labels): Collection
    {
        $labels = collect($labels);

        if ($labels->count() > 0 && isAssociativeArray($labels)) {
            $newLabels = collect([]);
            $labels->each(function ($value, $key) use ($newLabels) {
                $newLabels->push("$key=$value");
            });

            return $newLabels;
        }

        return $labels;
    }

    private function getServiceEnvironment(array $service): Collection
    {
        $environment = collect(data_get($service, 'environment', []));
        $buildArgs = collect(data_get($service, 'build.args', []));
        $environment = $environment->merge($buildArgs);

        return convertToKeyValueCollection($environment);
    }

    private function getPredefinedPort(string $serviceName, array $service): ?string
    {
        $tempServiceName = $serviceName;

        if ($serviceName === 'registry') {
            $tempServiceName = 'docker-registry';
        }
        if (str(data_get($service, 'image'))->contains('glitchtip')) {
            $tempServiceName = 'glitchtip';
        }
        if ($serviceName === 'supabase-kong') {
            $tempServiceName = 'supabase';
        }

        $serviceDefinition = data_get($this->allServices, $tempServiceName);
        $predefinedPort = data_get($serviceDefinition, 'port');

        if ($serviceName === 'plausible') {
            $predefinedPort = '8000';
        }

        return $predefinedPort;
    }

    private function parseVolumes(Collection $volumes, mixed $fileStorages, ServiceApplication|ServiceDatabase $savedService): Collection
    {
        $volumesParsed = collect([]);

        foreach ($volumes as $index => $volume) {
            $parsedVolume = $this->parseVolume($volume, $fileStorages, $savedService);
            if ($parsedVolume !== null) {
                $volumesParsed->put($index, $parsedVolume);
            }
        }

        return $volumesParsed;
    }

    private function parseVolume(mixed $volume, mixed $fileStorages, ServiceApplication|ServiceDatabase $savedService): mixed
    {
        $type = null;
        $source = null;
        $target = null;
        $content = null;
        $isDirectory = false;
        $parsed = null;

        if (is_string($volume)) {
            $parsed = parseDockerVolumeString($volume);
            $source = $parsed['source'];
            $target = $parsed['target'];
            $foundConfig = $fileStorages->whereMountPath($target)->first();

            if (sourceIsLocal($source)) {
                $type = str('bind');
                if ($foundConfig) {
                    $content = data_get($foundConfig, 'content');
                    $isDirectory = data_get($foundConfig, 'is_directory');
                } else {
                    $isDirectory = true;
                }
            } else {
                $type = str('volume');
            }
        } elseif (is_array($volume)) {
            return $this->parseArrayVolume($volume, $fileStorages, $savedService);
        }

        if ($type === null) {
            return $volume;
        }

        return $this->processVolumeByType($type, $source, $target, $content, $isDirectory, $parsed, $volume, $savedService);
    }

    private function parseArrayVolume(array $volume, mixed $fileStorages, ServiceApplication|ServiceDatabase $savedService): mixed
    {
        $type = data_get_str($volume, 'type');
        $source = data_get_str($volume, 'source');
        $target = data_get_str($volume, 'target');
        $content = data_get($volume, 'content');
        $isDirectory = (bool) data_get($volume, 'isDirectory', null) || (bool) data_get($volume, 'is_directory', null);

        // Validate for command injection
        $this->validateVolumeSource($source);
        $this->validateVolumeTarget($target);

        $foundConfig = $fileStorages->whereMountPath($target)->first();
        if ($foundConfig) {
            $content = data_get($foundConfig, 'content') ?: $content;
            $isDirectory = data_get($foundConfig, 'is_directory');
        } else {
            if (! $isDirectory && is_null($content)) {
                $isDirectory = true;
            }
        }

        return $this->processVolumeByType($type, $source, $target, $content, $isDirectory, null, $volume, $savedService);
    }

    private function validateVolumeSource(mixed $source): void
    {
        if ($source === null || empty($source->value())) {
            return;
        }

        $sourceValue = $source->value();
        $isSimpleEnvVar = preg_match('/^\$\{[a-zA-Z_][a-zA-Z0-9_]*\}$/', $sourceValue);
        $isEnvVarWithDefault = preg_match('/^\$\{[^}]+:-[^}]*\}$/', $sourceValue);
        $isEnvVarWithPath = preg_match('/^\$\{[a-zA-Z_][a-zA-Z0-9_]*\}[\/\w\.\-]*$/', $sourceValue);

        if (! $isSimpleEnvVar && ! $isEnvVarWithDefault && ! $isEnvVarWithPath) {
            try {
                validateShellSafePath($sourceValue, 'volume source');
            } catch (\Exception $e) {
                throw new \Exception(
                    'Invalid Docker volume definition (array syntax): '.$e->getMessage().
                    ' Please use safe path names without shell metacharacters.'
                );
            }
        }
    }

    private function validateVolumeTarget(mixed $target): void
    {
        if ($target === null || empty($target->value())) {
            return;
        }

        try {
            validateShellSafePath($target->value(), 'volume target');
        } catch (\Exception $e) {
            throw new \Exception(
                'Invalid Docker volume definition (array syntax): '.$e->getMessage().
                ' Please use safe path names without shell metacharacters.'
            );
        }
    }

    private function processVolumeByType(
        mixed $type,
        mixed $source,
        mixed $target,
        mixed $content,
        bool $isDirectory,
        ?array $parsed,
        mixed $volume,
        ServiceApplication|ServiceDatabase $savedService
    ): mixed {
        if ($type->value() === 'bind') {
            return $this->processBindVolume($source, $target, $content, $isDirectory, $parsed, $volume, $savedService);
        } elseif ($type->value() === 'volume') {
            return $this->processNamedVolume($source, $target, $parsed, $volume, $savedService);
        }

        return $volume;
    }

    private function processBindVolume(
        mixed $source,
        mixed $target,
        mixed $content,
        bool $isDirectory,
        ?array $parsed,
        mixed $volume,
        ServiceApplication|ServiceDatabase $savedService
    ): string {
        // Special cases for docker.sock and /tmp
        if ($source->value() === '/var/run/docker.sock' || $source->value() === '/tmp' || $source->value() === '/tmp/') {
            $volumeStr = $source->value().':'.$target->value();
            if ($parsed['mode']) {
                $volumeStr .= ':'.$parsed['mode']->value();
            }

            return $volumeStr;
        }

        if ((int) $this->resource->compose_parsing_version >= 4) {
            $mainDirectory = str(base_configuration_dir().'/services/'.$this->uuid);
        } else {
            $mainDirectory = str(base_configuration_dir().'/applications/'.$this->uuid);
        }

        $source = replaceLocalSource($source, $mainDirectory);

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

        if (isDev()) {
            if ((int) $this->resource->compose_parsing_version >= 4) {
                $source = $source->replace($mainDirectory, '/var/lib/docker/volumes/saturn_dev_saturn_data/_data/services/'.$this->uuid);
            } else {
                $source = $source->replace($mainDirectory, '/var/lib/docker/volumes/saturn_dev_saturn_data/_data/applications/'.$this->uuid);
            }
        }

        $volumeStr = "$source:$target";
        if ($parsed['mode']) {
            $volumeStr .= ':'.$parsed['mode']->value();
        }

        dispatch(new ServerFilesFromServerJob($savedService));

        return $volumeStr;
    }

    private function processNamedVolume(
        mixed $source,
        mixed $target,
        ?array $parsed,
        mixed $volume,
        ServiceApplication|ServiceDatabase $savedService
    ): mixed {
        // Check for special volume types
        if ($this->topLevel->get('volumes')->has($source->value())) {
            $temp = $this->topLevel->get('volumes')->get($source->value());
            if (data_get($temp, 'driver_opts.type') === 'cifs' || data_get($temp, 'driver_opts.type') === 'nfs') {
                return $volume;
            }
        }

        $slugWithoutUuid = Str::slug($source, '-');
        $name = "{$this->uuid}_{$slugWithoutUuid}";

        if (is_string($volume)) {
            $parsed = parseDockerVolumeString($volume);
            $target = $parsed['target'];
            $volumeStr = "$name:$target";
            if ($parsed['mode']) {
                $volumeStr .= ':'.$parsed['mode']->value();
            }
            $volume = $volumeStr;
        } elseif (is_array($volume)) {
            data_set($volume, 'source', $name);
        }

        $this->topLevel->get('volumes')->put($name, ['name' => $name]);

        LocalPersistentVolume::updateOrCreate(
            [
                'name' => $name,
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

        dispatch(new ServerFilesFromServerJob($savedService));

        return $volume;
    }

    private function processNetworks(Collection $networks): Collection
    {
        // Add top-level networks to service
        if ($this->topLevel->get('networks')?->count() > 0) {
            foreach ($this->topLevel->get('networks') as $networkName => $network) {
                if ($networkName === 'default') {
                    continue;
                }
                if ($network['aliases'] ?? false) {
                    continue;
                }
                $networkExists = $networks->contains(function ($value, $key) use ($networkName) {
                    return $value == $networkName || $key == $networkName;
                });
                if (! $networkExists) {
                    $networks->put($networkName, null);
                }
            }
        }

        // Add base network
        $baseNetworkExists = $networks->contains(function ($value, $_) {
            return $value == $this->baseNetwork;
        });

        if (! $baseNetworkExists) {
            foreach ($this->baseNetwork as $network) {
                $this->topLevel->get('networks')->put($network, [
                    'name' => $network,
                    'external' => true,
                ]);
            }
        }

        return $networks;
    }

    private function collectPorts(Collection $ports): Collection
    {
        $collectedPorts = collect([]);

        foreach ($ports as $sport) {
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

        return $collectedPorts;
    }

    private function processEnvironmentVariables(Collection $environment): Collection
    {
        $normalEnvironments = $environment->diffKeys($this->allMagicEnvironments);
        $normalEnvironments = $normalEnvironments->filter(function ($value, $key) {
            return ! str($value)->startsWith('SERVICE_');
        });

        foreach ($normalEnvironments as $key => $value) {
            $this->processEnvironmentVariable($key, $value, $environment);
        }

        return $environment;
    }

    private function processEnvironmentVariable(string $key, mixed $value, Collection $environment): void
    {
        $key = str($key);
        $value = str($value);
        $originalValue = $value;
        $parsedValue = replaceVariables($value);

        if ($parsedValue->startsWith('SERVICE_')) {
            $this->resource->environment_variables()->firstOrCreate([
                'key' => $key,
                'resourceable_type' => get_class($this->resource),
                'resourceable_id' => $this->resource->id,
            ], [
                'value' => $value,
                'is_preview' => false,
            ]);

            return;
        }

        if (! $value->startsWith('$')) {
            return;
        }

        if ($key->value() === $parsedValue->value()) {
            $this->resource->environment_variables()->firstOrCreate([
                'key' => $key,
                'resourceable_type' => get_class($this->resource),
                'resourceable_id' => $this->resource->id,
            ], [
                'value' => null,
                'is_preview' => false,
            ]);
        } else {
            $this->processVariableWithDefault($key, $value, $originalValue, $environment);
        }
    }

    private function processVariableWithDefault(
        Stringable $key,
        Stringable $value,
        Stringable $originalValue,
        Collection $environment
    ): void {
        if (! $value->startsWith('$')) {
            return;
        }

        $isRequired = false;

        if ($value->contains(':-')) {
            $value = replaceVariables($value);
            $key = $value->before(':');
            $value = $value->after(':-');
        } elseif ($value->contains('-')) {
            $value = replaceVariables($value);
            $key = $value->before('-');
            $value = $value->after('-');
        } elseif ($value->contains(':?')) {
            $value = replaceVariables($value);
            $key = $value->before(':');
            $value = $value->after(':?');
            $isRequired = true;
        } elseif ($value->contains('?')) {
            $value = replaceVariables($value);
            $key = $value->before('?');
            $value = $value->after('?');
            $isRequired = true;
        }

        if ($originalValue->value() === $value->value()) {
            $parsedKeyValue = replaceVariables($value);
            $this->resource->environment_variables()->firstOrCreate([
                'key' => $parsedKeyValue,
                'resourceable_type' => get_class($this->resource),
                'resourceable_id' => $this->resource->id,
            ], [
                'is_preview' => false,
                'is_required' => $isRequired,
            ]);
            $environment[$parsedKeyValue->value()] = $value;

            return;
        }

        $this->resource->environment_variables()->firstOrCreate([
            'key' => $key,
            'resourceable_type' => get_class($this->resource),
            'resourceable_id' => $this->resource->id,
        ], [
            'value' => $value,
            'is_preview' => false,
            'is_required' => $isRequired,
        ]);
    }

    private function addSaturnEnvironmentVariables(Collection $saturnEnvironments, string $containerName): void
    {
        if ($this->resource->environment_variables->where('key', 'SATURN_RESOURCE_UUID')->isEmpty()) {
            $saturnEnvironments->put('SATURN_RESOURCE_UUID', "{$this->resource->uuid}");
        }

        if ($this->resource->environment_variables->where('key', 'SATURN_CONTAINER_NAME')->isEmpty()) {
            $saturnEnvironments->put('SATURN_CONTAINER_NAME', "{$containerName}");
        }
    }

    private function getServiceFqdns(ServiceApplication|ServiceDatabase $savedService): Collection
    {
        if ($savedService->serviceType()) {
            return generateServiceSpecificFqdns($savedService);
        }

        return collect(data_get($savedService, 'fqdns'))->filter();
    }

    private function addFqdnEnvironmentVariables(Collection $saturnEnvironments, Collection $fqdns): void
    {
        $fqdnsWithoutPort = $fqdns->map(function ($fqdn) {
            return str($fqdn)->replace('http://', '')->replace('https://', '')->before(':');
        });
        $saturnEnvironments->put('SATURN_FQDN', $fqdnsWithoutPort->implode(','));

        $urls = $fqdns->map(function ($fqdn): Stringable {
            return str($fqdn)->after('://')->before(':')->prepend(str($fqdn)->before('://')->append('://'));
        });
        $saturnEnvironments->put('SATURN_URL', $urls->implode(','));
    }

    private function finalizeEnvironment(Collection $environment): Collection
    {
        if ($environment->count() === 0) {
            return $environment;
        }

        return $environment->filter(function ($value, $key) {
            return ! str($key)->startsWith('SERVICE_FQDN_');
        })->map(function ($value, $key) {
            if ($value === null) {
                return $value;
            } elseif ($value === '') {
                $dbEnv = $this->resource->environment_variables()->where('key', $key)->first();
                if ($dbEnv && str($dbEnv->value)->isNotEmpty()) {
                    return $dbEnv->value;
                }
            }

            return $value;
        });
    }

    private function processServiceLabels(
        Collection $labels,
        Collection $defaultLabels,
        mixed $fqdns,
        bool $isDatabase,
        string $serviceName,
        mixed $image,
        ?string $predefinedPort
    ): Collection {
        $serviceLabels = $labels->merge($defaultLabels);

        if ($serviceLabels->count() > 0) {
            $isContainerLabelEscapeEnabled = data_get($this->resource, 'is_container_label_escape_enabled');
            if ($isContainerLabelEscapeEnabled) {
                $serviceLabels = $serviceLabels->map(function ($value, $key) {
                    return escapeDollarSign($value);
                });
            }
        }

        if (! $isDatabase && $fqdns->count() > 0) {
            $serviceLabels = $this->generateProxyLabels($serviceLabels, $fqdns, $serviceName, $image, $predefinedPort);
        }

        return $serviceLabels;
    }

    private function generateProxyLabels(
        Collection $serviceLabels,
        Collection $fqdns,
        string $serviceName,
        mixed $image,
        ?string $predefinedPort
    ): Collection {
        $shouldGenerateLabelsExactly = $this->resource->server->settings->generate_exact_labels;
        $uuid = $this->resource->uuid;
        $network = data_get($this->resource, 'destination.network');

        if ($shouldGenerateLabelsExactly) {
            switch ($this->server->proxyType()) {
                case ProxyTypes::TRAEFIK->value:
                    $serviceLabels = $serviceLabels->merge(fqdnLabelsForTraefik(
                        uuid: $uuid,
                        domains: $fqdns,
                        is_force_https_enabled: true,
                        serviceLabels: $serviceLabels,
                        is_gzip_enabled: $this->getOriginalResource($serviceName)->isGzipEnabled(),
                        is_stripprefix_enabled: $this->getOriginalResource($serviceName)->isStripprefixEnabled(),
                        service_name: $serviceName,
                        image: $image
                    ));
                    break;
                case ProxyTypes::CADDY->value:
                    $serviceLabels = $serviceLabels->merge(fqdnLabelsForCaddy(
                        network: $network,
                        uuid: $uuid,
                        domains: $fqdns,
                        is_force_https_enabled: true,
                        serviceLabels: $serviceLabels,
                        is_gzip_enabled: $this->getOriginalResource($serviceName)->isGzipEnabled(),
                        is_stripprefix_enabled: $this->getOriginalResource($serviceName)->isStripprefixEnabled(),
                        service_name: $serviceName,
                        image: $image,
                        predefinedPort: $predefinedPort
                    ));
                    break;
            }
        } else {
            $serviceLabels = $serviceLabels->merge(fqdnLabelsForTraefik(
                uuid: $uuid,
                domains: $fqdns,
                is_force_https_enabled: true,
                serviceLabels: $serviceLabels,
                is_gzip_enabled: $this->getOriginalResource($serviceName)->isGzipEnabled(),
                is_stripprefix_enabled: $this->getOriginalResource($serviceName)->isStripprefixEnabled(),
                service_name: $serviceName,
                image: $image
            ));
            $serviceLabels = $serviceLabels->merge(fqdnLabelsForCaddy(
                network: $network,
                uuid: $uuid,
                domains: $fqdns,
                is_force_https_enabled: true,
                serviceLabels: $serviceLabels,
                is_gzip_enabled: $this->getOriginalResource($serviceName)->isGzipEnabled(),
                is_stripprefix_enabled: $this->getOriginalResource($serviceName)->isStripprefixEnabled(),
                service_name: $serviceName,
                image: $image,
                predefinedPort: $predefinedPort
            ));
        }

        return $serviceLabels;
    }

    private function getOriginalResource(string $serviceName): ServiceApplication|ServiceDatabase
    {
        $service = $this->services[$serviceName] ?? [];
        $image = data_get_str($service, 'image');

        return $this->findOrCreateSavedService($serviceName, $image, $service);
    }

    private function cleanVolumeData(Collection $volumesParsed): Collection
    {
        return $volumesParsed->map(function ($volume) {
            data_forget($volume, 'content');
            data_forget($volume, 'is_directory');
            data_forget($volume, 'isDirectory');

            return $volume;
        });
    }

    private function buildServicePayload(
        array $service,
        string $containerName,
        mixed $restart,
        Collection $serviceLabels,
        bool $useNetworkMode,
        Collection $networks,
        Collection $ports,
        Collection $volumesParsed,
        Collection $environment,
        Collection $saturnEnvironments,
        Collection $serviceNameEnvironments,
        ?array $logging,
        Collection $dependsOn
    ): Collection {
        $payload = collect($service)->merge([
            'container_name' => $containerName,
            'restart' => $restart->value(),
            'labels' => $serviceLabels,
        ]);

        if (! $useNetworkMode) {
            $networksTemp = collect();
            foreach ($networks as $key => $network) {
                if (gettype($network) === 'string') {
                    $networksTemp->put($network, null);
                } elseif (gettype($network) === 'array') {
                    $networksTemp->put($key, $network);
                }
            }
            foreach ($this->baseNetwork as $network) {
                $networksTemp->put($network, null);
            }
            $payload['networks'] = $networksTemp;
        }

        if ($ports->count() > 0) {
            $payload['ports'] = $ports;
        }

        if ($volumesParsed->count() > 0) {
            $payload['volumes'] = $volumesParsed;
        }

        if ($environment->count() > 0 || $saturnEnvironments->count() > 0) {
            $payload['environment'] = $environment->merge($saturnEnvironments)->merge($serviceNameEnvironments);
        }

        if ($logging) {
            $payload['logging'] = $logging;
        }

        if ($dependsOn->count() > 0) {
            $payload['depends_on'] = $dependsOn;
        }

        // Auto-inject .env file
        $existingEnvFiles = data_get($service, 'env_file');
        $envFiles = collect(is_null($existingEnvFiles) ? [] : (is_array($existingEnvFiles) ? $existingEnvFiles : [$existingEnvFiles]))
            ->push('.env')
            ->unique()
            ->values();
        $payload['env_file'] = $envFiles;

        // Apply resource limits from Service model if configured
        if ($this->resource->hasResourceLimits()) {
            $limits = $this->resource->getLimits();

            if ($limits['limits_memory'] !== '0') {
                $payload['mem_limit'] = $limits['limits_memory'];
            }
            if ($limits['limits_memory_swap'] !== '0') {
                $payload['memswap_limit'] = $limits['limits_memory_swap'];
            }
            if ($limits['limits_memory_swappiness'] !== 60) {
                $payload['mem_swappiness'] = $limits['limits_memory_swappiness'];
            }
            if ($limits['limits_memory_reservation'] !== '0') {
                $payload['mem_reservation'] = $limits['limits_memory_reservation'];
            }
            if ($limits['limits_cpus'] !== '0') {
                $payload['cpus'] = (float) $limits['limits_cpus'];
            }
            if (! is_null($limits['limits_cpuset'])) {
                $payload['cpuset'] = $limits['limits_cpuset'];
            }
            if ($limits['limits_cpu_shares'] !== 1024) {
                $payload['cpu_shares'] = $limits['limits_cpu_shares'];
            }
        }

        return $payload;
    }

    private function finalizeTopLevel(): void
    {
        $customOrder = ['services', 'volumes', 'networks', 'configs', 'secrets'];

        $this->topLevel = $this->topLevel->sortBy(function ($value, $key) use ($customOrder) {
            return array_search($key, $customOrder);
        });

        // Remove empty top-level sections
        $this->topLevel = $this->topLevel->filter(function ($value, $key) {
            if ($key === 'services') {
                return true;
            }

            return $value instanceof Collection ? $value->isNotEmpty() : ! empty($value);
        });
    }

    private function saveResource(): void
    {
        $cleanedCompose = Yaml::dump(convertToArray($this->topLevel), 10, 2);
        $this->resource->docker_compose = $cleanedCompose;

        // Update docker_compose_raw to remove content: from volumes only
        try {
            $originalYaml = Yaml::parse($this->originalCompose);
            if (isset($originalYaml['services'])) {
                foreach ($originalYaml['services'] as $serviceName => &$service) {
                    if (isset($service['volumes'])) {
                        foreach ($service['volumes'] as $key => &$volume) {
                            if (is_array($volume)) {
                                unset($volume['content']);
                                unset($volume['isDirectory']);
                                unset($volume['is_directory']);
                            }
                        }
                    }
                }
            }
            $this->resource->docker_compose_raw = Yaml::dump($originalYaml, 10, 2);
        } catch (\Exception $e) {
            Log::warning('Failed to update docker_compose_raw in serviceParser: '.$e->getMessage());
        }

        data_forget($this->resource, 'environment_variables');
        data_forget($this->resource, 'environment_variables_preview');
        $this->resource->save();
    }
}
