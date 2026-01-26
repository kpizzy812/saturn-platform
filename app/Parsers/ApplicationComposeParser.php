<?php

namespace App\Parsers;

use App\Enums\ProxyTypes;
use App\Jobs\ServerFilesFromServerJob;
use App\Models\Application;
use App\Models\ApplicationPreview;
use App\Models\LocalFileVolume;
use App\Models\LocalPersistentVolume;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Url\Url;
use Symfony\Component\Yaml\Yaml;
use Visus\Cuid2\Cuid2;

/**
 * Parser for Application Docker Compose files.
 *
 * Handles parsing and transformation of docker-compose.yml files for Application resources,
 * including environment variable processing, volume management, network configuration,
 * and label generation for Traefik/Caddy proxies.
 */
class ApplicationComposeParser
{
    private Application $resource;

    private int $pullRequestId;

    private bool $isPullRequest;

    private ?int $previewId;

    private ?string $commit;

    private string $uuid;

    private mixed $server;

    private Collection $fileStorages;

    private Collection $topLevel;

    private Collection $baseNetwork;

    private Collection $allMagicEnvironments;

    private string $originalCompose;

    private array $services;

    /**
     * Parse a Docker Compose file for an Application resource.
     *
     * @param  Application  $resource  The application resource
     * @param  int  $pull_request_id  Pull request ID (0 for non-PR deployments)
     * @param  int|null  $preview_id  Preview deployment ID
     * @param  string|null  $commit  Commit hash for image tagging
     * @return Collection The parsed compose configuration
     */
    public static function parse(
        Application $resource,
        int $pull_request_id = 0,
        ?int $preview_id = null,
        ?string $commit = null
    ): Collection {
        $parser = new self($resource, $pull_request_id, $preview_id, $commit);

        return $parser->doParse();
    }

    private function __construct(
        Application $resource,
        int $pull_request_id,
        ?int $preview_id,
        ?string $commit
    ) {
        $this->resource = $resource;
        $this->pullRequestId = $pull_request_id;
        $this->isPullRequest = $pull_request_id !== 0;
        $this->previewId = $preview_id;
        $this->commit = $commit;
        $this->uuid = data_get($resource, 'uuid');
        $this->server = data_get($resource, 'destination.server');
        $this->fileStorages = $resource->fileStorages();
        $this->allMagicEnvironments = collect([]);
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

        // First pass: collect and process magic environments
        $this->processMagicEnvironments();

        // Generate SERVICE_NAME variables for docker compose services
        $serviceNameEnvironments = collect([]);
        if ($this->resource->build_pack === 'dockercompose') {
            $serviceNameEnvironments = generateDockerComposeServiceName($this->services, $this->pullRequestId);
        }

        // Second pass: parse services
        $parsedServices = $this->parseServices($serviceNameEnvironments);
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
        $this->baseNetwork = $this->isPullRequest
            ? collect(["{$this->uuid}-{$this->pullRequestId}"])
            : collect([$this->uuid]);
    }

    private function processMagicEnvironments(): void
    {
        foreach ($this->services as $serviceName => $service) {
            $this->validateServiceName($serviceName);

            $magicEnvironments = collect([]);
            $image = data_get_str($service, 'image');
            $environment = collect(data_get($service, 'environment', []));
            $buildArgs = collect(data_get($service, 'build.args', []));
            $environment = $environment->merge($buildArgs);
            $environment = convertToKeyValueCollection($environment);

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

                // Process SERVICE_FQDN_ variables
                if ($key->startsWith('SERVICE_FQDN_')) {
                    $this->processServiceFqdnVariable($key, $value);
                }
            }

            $this->allMagicEnvironments = $this->allMagicEnvironments->merge($magicEnvironments);

            // Generate environment variables for magic environments
            if ($magicEnvironments->count() > 0) {
                $this->generateMagicEnvironmentVariables($magicEnvironments);
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

    private function processServiceFqdnVariable(\Illuminate\Support\Stringable $key, \Illuminate\Support\Stringable $value): void
    {
        $parsed = parseServiceEnvironmentVariable($key->value());
        $fqdnFor = $parsed['service_name'];
        $port = $parsed['port'];
        $fqdn = $this->resource->fqdn;

        if (blank($this->resource->fqdn)) {
            $fqdn = generateFqdn(server: $this->server, random: "{$this->uuid}", parserVersion: $this->resource->compose_parsing_version);
        }

        if ($value && get_class($value) === \Illuminate\Support\Stringable::class && $value->startsWith('/')) {
            $path = $value->value();
            if ($path !== '/') {
                $fqdn = "$fqdn$path";
            }
        }

        $fqdnWithPort = $fqdn;
        if ($port) {
            $fqdnWithPort = "$fqdn:$port";
        }

        if (is_null($this->resource->fqdn)) {
            data_forget($this->resource, 'environment_variables');
            data_forget($this->resource, 'environment_variables_preview');
            $this->resource->fqdn = $fqdnWithPort;
            $this->resource->save();
        }

        if (! $parsed['has_port']) {
            $this->resource->environment_variables()->updateOrCreate([
                'key' => $key->value(),
                'resourceable_type' => get_class($this->resource),
                'resourceable_id' => $this->resource->id,
            ], [
                'value' => $fqdn,
                'is_preview' => false,
            ]);
        }

        if ($parsed['has_port']) {
            $newKey = str($key)->beforeLast('_');
            $this->resource->environment_variables()->updateOrCreate([
                'key' => $newKey->value(),
                'resourceable_type' => get_class($this->resource),
                'resourceable_id' => $this->resource->id,
            ], [
                'value' => $fqdn,
                'is_preview' => false,
            ]);
        }
    }

    private function generateMagicEnvironmentVariables(Collection $magicEnvironments): void
    {
        foreach ($magicEnvironments as $key => $value) {
            $key = str($key);
            $value = replaceVariables($value);
            $command = parseCommandFromMagicEnvVariable($key);

            if ($command->value() === 'FQDN' || $command->value() === 'URL') {
                $this->generateFqdnUrlVariables($key, $command);
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

    private function generateFqdnUrlVariables(\Illuminate\Support\Stringable $key, \Illuminate\Support\Stringable $command): void
    {
        $parsed = parseServiceEnvironmentVariable($key->value());
        $serviceName = $parsed['service_name'];
        $port = $parsed['port'];

        // Extract case-preserved service name from template
        $strKey = str($key->value());
        if ($parsed['has_port']) {
            $serviceNamePreserved = $strKey->startsWith('SERVICE_URL_')
                ? $strKey->after('SERVICE_URL_')->beforeLast('_')->value()
                : $strKey->after('SERVICE_FQDN_')->beforeLast('_')->value();
        } else {
            $serviceNamePreserved = $strKey->startsWith('SERVICE_URL_')
                ? $strKey->after('SERVICE_URL_')->value()
                : $strKey->after('SERVICE_FQDN_')->value();
        }

        $originalServiceName = str($serviceName)->replace('_', '-')->value();
        $serviceName = str($serviceName)->replace('-', '_')->replace('.', '_')->value();

        // Generate BOTH FQDN & URL
        $fqdn = generateFqdn(server: $this->server, random: "$originalServiceName-{$this->uuid}", parserVersion: $this->resource->compose_parsing_version);
        $url = generateUrl(server: $this->server, random: "$originalServiceName-{$this->uuid}");

        // Strip scheme for environment variable values
        $fqdnValueForEnv = str($fqdn)->after('://')->value();

        // Append port if specified
        $urlWithPort = $url;
        $fqdnValueForEnvWithPort = $fqdnValueForEnv;
        if ($port && is_numeric($port)) {
            $urlWithPort = "$url:$port";
            $fqdnValueForEnvWithPort = "$fqdnValueForEnv:$port";
        }

        // Create base SERVICE_FQDN variable
        $this->resource->environment_variables()->firstOrCreate([
            'key' => "SERVICE_FQDN_{$serviceNamePreserved}",
            'resourceable_type' => get_class($this->resource),
            'resourceable_id' => $this->resource->id,
        ], [
            'value' => $fqdnValueForEnv,
            'is_preview' => false,
        ]);

        // Create base SERVICE_URL variable
        $this->resource->environment_variables()->firstOrCreate([
            'key' => "SERVICE_URL_{$serviceNamePreserved}",
            'resourceable_type' => get_class($this->resource),
            'resourceable_id' => $this->resource->id,
        ], [
            'value' => $url,
            'is_preview' => false,
        ]);

        // Create port-specific pairs if needed
        if ($parsed['has_port'] && $port) {
            $this->resource->environment_variables()->firstOrCreate([
                'key' => "SERVICE_FQDN_{$serviceNamePreserved}_{$port}",
                'resourceable_type' => get_class($this->resource),
                'resourceable_id' => $this->resource->id,
            ], [
                'value' => $fqdnValueForEnvWithPort,
                'is_preview' => false,
            ]);

            $this->resource->environment_variables()->firstOrCreate([
                'key' => "SERVICE_URL_{$serviceNamePreserved}_{$port}",
                'resourceable_type' => get_class($this->resource),
                'resourceable_id' => $this->resource->id,
            ], [
                'value' => $urlWithPort,
                'is_preview' => false,
            ]);
        }

        // Update docker_compose_domains for dockercompose build pack
        if ($this->resource->build_pack === 'dockercompose') {
            $this->updateDockerComposeDomains($serviceName, $url, $urlWithPort, $port);
        }
    }

    private function updateDockerComposeDomains(string $serviceName, string $url, string $urlWithPort, $port): void
    {
        // Check if service exists
        $serviceExists = false;
        foreach ($this->services as $serviceNameKey => $service) {
            $transformedServiceName = str($serviceNameKey)->replace('-', '_')->replace('.', '_')->value();
            if ($transformedServiceName === $serviceName) {
                $serviceExists = true;
                break;
            }
        }

        if ($serviceExists) {
            $domains = collect(json_decode(data_get($this->resource, 'docker_compose_domains'))) ?? collect([]);
            $domainExists = data_get($domains->get($serviceName), 'domain');

            $domainValue = $port ? $urlWithPort : $url;

            if (is_null($domainExists)) {
                $domains->put($serviceName, ['domain' => $domainValue]);
                $this->resource->docker_compose_domains = $domains->toJson();
                $this->resource->save();
            }
        }
    }

    private function parseServices(Collection $serviceNameEnvironments): Collection
    {
        $parsedServices = collect([]);

        foreach ($this->services as $serviceName => $service) {
            $payload = $this->parseService($serviceName, $service, $serviceNameEnvironments);
            $finalServiceName = $this->isPullRequest
                ? addPreviewDeploymentSuffix($serviceName, $this->pullRequestId)
                : $serviceName;
            $parsedServices->put($finalServiceName, $payload);
        }

        return $parsedServices;
    }

    private function parseService(string $serviceName, array $service, Collection $serviceNameEnvironments): Collection
    {
        $image = data_get_str($service, 'image');
        $restart = data_get_str($service, 'restart', RESTART_MODE);
        $logging = $this->getLoggingConfig($service);
        $volumes = collect(data_get($service, 'volumes', []));
        $networks = collect(data_get($service, 'networks', []));
        $useNetworkMode = data_get($service, 'network_mode') !== null;
        $dependsOn = collect(data_get($service, 'depends_on', []));
        $labels = $this->normalizeLabels(data_get($service, 'labels', []));
        $environment = $this->getServiceEnvironment($service);
        $ports = collect(data_get($service, 'ports', []));
        $saturnEnvironments = collect([]);

        $isDatabase = isDatabaseImage($image, $service);
        $baseName = generateApplicationContainerName(
            application: $this->resource,
            pull_request_id: $this->pullRequestId
        );
        $containerName = "$serviceName-$baseName";

        // Parse volumes
        $volumesParsed = $this->parseVolumes($volumes, $service, $serviceName);

        // Handle depends_on for preview deployments
        if ($dependsOn->count() > 0 && $this->isPullRequest) {
            $dependsOn = $this->adjustDependsOnForPreview($dependsOn);
        }

        // Handle networks
        if (! $useNetworkMode) {
            $networks = $this->processNetworks($networks);
        }

        // Process environment variables
        $environment = $this->processEnvironmentVariables($environment);

        // Add Saturn environment variables
        $this->addSaturnEnvironmentVariables($saturnEnvironments, $containerName);

        // Get FQDNs for this service
        $fqdns = $this->getServiceFqdns($serviceName);

        // Generate default labels
        $defaultLabels = defaultLabels(
            id: $this->resource->id,
            name: $containerName,
            projectName: $this->resource->project()->name,
            resourceName: $this->resource->name,
            pull_request_id: $this->pullRequestId,
            type: 'application',
            environment: $this->resource->environment->name,
        );

        // Add SATURN_FQDN & SATURN_URL
        if (! $isDatabase && $fqdns instanceof Collection && $fqdns->count() > 0) {
            $this->addFqdnEnvironmentVariables($saturnEnvironments, $fqdns);
        }

        add_saturn_default_environment_variables($this->resource, $saturnEnvironments, $this->resource->environment_variables);

        // Filter and map environment
        $environment = $this->finalizeEnvironment($environment);

        // Merge labels
        $serviceLabels = $this->processServiceLabels($labels, $defaultLabels, $fqdns, $isDatabase, $serviceName, $image);

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
            $dependsOn,
            $serviceName,
            $image
        );

        return $payload;
    }

    private function getLoggingConfig(array $service): ?array
    {
        $logging = data_get($service, 'logging');

        if ($this->server->isLogDrainEnabled() && $this->resource->isLogDrainEnabled()) {
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

    private function parseVolumes(Collection $volumes, array $service, string $serviceName): Collection
    {
        $volumesParsed = collect([]);

        foreach ($volumes as $index => $volume) {
            $parsedVolume = $this->parseVolume($volume, $service);
            if ($parsedVolume !== null) {
                $volumesParsed->put($index, $parsedVolume);
            }
        }

        return $volumesParsed;
    }

    private function parseVolume(mixed $volume, array $service): mixed
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
            $foundConfig = $this->fileStorages->whereMountPath($target)->first();

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
            return $this->parseArrayVolume($volume);
        }

        if ($type === null) {
            return $volume;
        }

        return $this->processVolumeByType($type, $source, $target, $content, $isDirectory, $parsed, $volume);
    }

    private function parseArrayVolume(array $volume): mixed
    {
        $type = data_get_str($volume, 'type');
        $source = data_get_str($volume, 'source');
        $target = data_get_str($volume, 'target');
        $content = data_get($volume, 'content');
        $isDirectory = (bool) data_get($volume, 'isDirectory', null) || (bool) data_get($volume, 'is_directory', null);

        // Validate for command injection
        $this->validateVolumeSource($source);
        $this->validateVolumeTarget($target);

        $foundConfig = $this->fileStorages->whereMountPath($target)->first();
        if ($foundConfig) {
            $content = data_get($foundConfig, 'content') ?: $content;
            $isDirectory = data_get($foundConfig, 'is_directory');
        } else {
            if ((is_null($isDirectory) || ! $isDirectory) && is_null($content)) {
                $isDirectory = true;
            }
        }

        return $this->processVolumeByType($type, $source, $target, $content, $isDirectory, null, $volume);
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
        mixed $volume
    ): mixed {
        if ($type->value() === 'bind') {
            return $this->processBindVolume($source, $target, $content, $isDirectory, $parsed, $volume);
        } elseif ($type->value() === 'volume') {
            return $this->processNamedVolume($source, $target, $parsed, $volume);
        }

        return $volume;
    }

    private function processBindVolume(
        mixed $source,
        mixed $target,
        mixed $content,
        bool $isDirectory,
        ?array $parsed,
        mixed $volume
    ): string {
        // Special cases for docker.sock and /tmp
        if ($source->value() === '/var/run/docker.sock' || $source->value() === '/tmp' || $source->value() === '/tmp/') {
            $volumeStr = $source->value().':'.$target->value();
            if (isset($parsed['mode']) && $parsed['mode']) {
                $volumeStr .= ':'.$parsed['mode']->value();
            }

            return $volumeStr;
        }

        $mainDirectory = str(base_configuration_dir().'/applications/'.$this->uuid);
        $source = replaceLocalSource($source, $mainDirectory);

        if ($this->isPullRequest) {
            $source = addPreviewDeploymentSuffix($source, $this->pullRequestId);
        }

        LocalFileVolume::updateOrCreate(
            [
                'mount_path' => $target,
                'resource_id' => $this->resource->id,
                'resource_type' => get_class($this->resource),
            ],
            [
                'fs_path' => $source,
                'mount_path' => $target,
                'content' => $content,
                'is_directory' => $isDirectory,
                'resource_id' => $this->resource->id,
                'resource_type' => get_class($this->resource),
            ]
        );

        if (isDev()) {
            $source = $source->replace($mainDirectory, '/var/lib/docker/volumes/saturn_dev_saturn_data/_data/applications/'.$this->uuid);
        }

        $volumeStr = "$source:$target";
        if (isset($parsed['mode']) && $parsed['mode']) {
            $volumeStr .= ':'.$parsed['mode']->value();
        }

        dispatch(new ServerFilesFromServerJob($this->resource));

        return $volumeStr;
    }

    private function processNamedVolume(
        mixed $source,
        mixed $target,
        ?array $parsed,
        mixed $volume
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

        if ($this->isPullRequest) {
            $name = addPreviewDeploymentSuffix($name, $this->pullRequestId);
        }

        if (is_string($volume)) {
            $parsed = parseDockerVolumeString($volume);
            $source = $parsed['source'];
            $target = $parsed['target'];
            $volumeStr = "$name:$target";
            if (isset($parsed['mode']) && $parsed['mode']) {
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
                'resource_id' => $this->resource->id,
                'resource_type' => get_class($this->resource),
            ],
            [
                'name' => $name,
                'mount_path' => $target,
                'resource_id' => $this->resource->id,
                'resource_type' => get_class($this->resource),
            ]
        );

        dispatch(new ServerFilesFromServerJob($this->resource));

        return $volume;
    }

    private function adjustDependsOnForPreview(Collection $dependsOn): Collection
    {
        $newDependsOn = collect([]);

        $dependsOn->each(function ($dependency, $condition) use ($newDependsOn) {
            if (is_numeric($condition)) {
                $dependency = addPreviewDeploymentSuffix($dependency, $this->pullRequestId);
                $newDependsOn->put($condition, $dependency);
            } else {
                $condition = addPreviewDeploymentSuffix($condition, $this->pullRequestId);
                $newDependsOn->put($condition, $dependency);
            }
        });

        return $newDependsOn;
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

        if ($value->startsWith('$SERVICE_')) {
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
        \Illuminate\Support\Stringable $key,
        \Illuminate\Support\Stringable $value,
        \Illuminate\Support\Stringable $originalValue,
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
        $branch = $this->resource->git_branch;
        if ($this->pullRequestId !== 0) {
            $branch = "pull/{$this->pullRequestId}/head";
        }

        if ($this->resource->environment_variables->where('key', 'SATURN_BRANCH')->isEmpty()) {
            $saturnEnvironments->put('SATURN_BRANCH', "\"{$branch}\"");
        }

        if ($this->resource->environment_variables->where('key', 'SATURN_RESOURCE_UUID')->isEmpty()) {
            $saturnEnvironments->put('SATURN_RESOURCE_UUID', "{$this->resource->uuid}");
        }

        if ($this->resource->environment_variables->where('key', 'SATURN_CONTAINER_NAME')->isEmpty()) {
            $saturnEnvironments->put('SATURN_CONTAINER_NAME', "{$containerName}");
        }
    }

    private function getServiceFqdns(string $serviceName): Collection
    {
        if ($this->isPullRequest) {
            $preview = $this->resource->previews()->find($this->previewId);
            $domains = collect(json_decode(data_get($preview, 'docker_compose_domains'))) ?? collect([]);
        } else {
            $domains = collect(json_decode(data_get($this->resource, 'docker_compose_domains'))) ?? collect([]);
        }

        if ($this->resource->build_pack !== 'dockercompose') {
            $domains = collect([]);
        }

        $changedServiceName = str($serviceName)->replace('-', '_')->replace('.', '_')->value();
        $fqdns = data_get($domains, "$changedServiceName.domain");

        if (filled($fqdns)) {
            $fqdns = str($fqdns)->explode(',');
            if ($this->isPullRequest) {
                $fqdns = $this->processPreviewFqdns($fqdns, $changedServiceName);
            }
        }

        // Generate SERVICE_FQDN & SERVICE_URL for dockercompose
        if ($this->resource->build_pack === 'dockercompose') {
            $this->generateDockerComposeServiceUrls($domains, $serviceName);
        }

        return $fqdns instanceof Collection ? $fqdns : collect([]);
    }

    private function processPreviewFqdns(Collection $fqdns, string $changedServiceName): Collection
    {
        $preview = $this->resource->previews()->find($this->previewId);
        $dockerComposeDomains = collect(json_decode(data_get($preview, 'docker_compose_domains')));

        if ($dockerComposeDomains->count() > 0) {
            $foundFqdn = data_get($dockerComposeDomains, "$changedServiceName.domain");
            if ($foundFqdn) {
                return collect($foundFqdn);
            }

            return collect([]);
        }

        return $fqdns->map(function ($fqdn) {
            $preview = ApplicationPreview::findPreviewByApplicationAndPullId($this->resource->id, $this->pullRequestId);
            $url = Url::fromString($fqdn);
            $template = $this->resource->preview_url_template;
            $host = $url->getHost();
            $schema = $url->getScheme();
            $portInt = $url->getPort();
            $port = $portInt !== null ? ':'.$portInt : '';
            $random = new Cuid2;
            $previewFqdn = str_replace('{{random}}', $random, $template);
            $previewFqdn = str_replace('{{domain}}', $host, $previewFqdn);
            $previewFqdn = str_replace('{{pr_id}}', $this->pullRequestId, $previewFqdn);
            $previewFqdn = "$schema://$previewFqdn{$port}";
            $preview->fqdn = $previewFqdn;
            $preview->save();

            return $previewFqdn;
        });
    }

    private function generateDockerComposeServiceUrls(Collection $domains, string $serviceName): void
    {
        $saturnEnvironments = collect([]);
        $serviceNameFormatted = str($serviceName)->upper()->replace('-', '_')->replace('.', '_');

        foreach ($domains as $forServiceName => $domain) {
            $parsedDomain = data_get($domain, 'domain');

            if (filled($parsedDomain)) {
                $parsedDomain = str($parsedDomain)->explode(',')->first();
                $saturnUrl = Url::fromString($parsedDomain);
                $saturnScheme = $saturnUrl->getScheme();
                $saturnFqdn = $saturnUrl->getHost();
                $saturnUrl = $saturnUrl->withScheme($saturnScheme)->withHost($saturnFqdn)->withPort(null);

                $this->resource->environment_variables()->updateOrCreate([
                    'resourceable_type' => Application::class,
                    'resourceable_id' => $this->resource->id,
                    'key' => 'SERVICE_URL_'.str($forServiceName)->upper()->replace('-', '_')->replace('.', '_'),
                ], [
                    'value' => $saturnUrl->__toString(),
                    'is_preview' => false,
                ]);

                $this->resource->environment_variables()->updateOrCreate([
                    'resourceable_type' => Application::class,
                    'resourceable_id' => $this->resource->id,
                    'key' => 'SERVICE_FQDN_'.str($forServiceName)->upper()->replace('-', '_')->replace('.', '_'),
                ], [
                    'value' => $saturnFqdn,
                    'is_preview' => false,
                ]);
            } else {
                $this->resource->environment_variables()->where('resourceable_type', Application::class)
                    ->where('resourceable_id', $this->resource->id)
                    ->where('key', 'LIKE', "SERVICE_FQDN_{$serviceNameFormatted}%")
                    ->update(['value' => null]);

                $this->resource->environment_variables()->where('resourceable_type', Application::class)
                    ->where('resourceable_id', $this->resource->id)
                    ->where('key', 'LIKE', "SERVICE_URL_{$serviceNameFormatted}%")
                    ->update(['value' => null]);
            }
        }
    }

    private function addFqdnEnvironmentVariables(Collection $saturnEnvironments, Collection $fqdns): void
    {
        $fqdnsWithoutPort = $fqdns->map(function ($fqdn) {
            return str($fqdn)->after('://')->before(':')->prepend(str($fqdn)->before('://')->append('://'));
        });
        $saturnEnvironments->put('SATURN_URL', $fqdnsWithoutPort->implode(','));

        $urls = $fqdns->map(function ($fqdn) {
            return str($fqdn)->replace('http://', '')->replace('https://', '')->before(':');
        });
        $saturnEnvironments->put('SATURN_FQDN', $urls->implode(','));
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
        mixed $image
    ): Collection {
        $serviceLabels = $labels->merge($defaultLabels);

        if ($serviceLabels->count() > 0) {
            $isContainerLabelEscapeEnabled = data_get($this->resource, 'settings.is_container_label_escape_enabled');
            if ($isContainerLabelEscapeEnabled) {
                $serviceLabels = $serviceLabels->map(function ($value, $key) {
                    return escapeDollarSign($value);
                });
            }
        }

        if (! $isDatabase && $fqdns instanceof Collection && $fqdns->count() > 0) {
            $serviceLabels = $this->generateProxyLabels($serviceLabels, $fqdns, $serviceName, $image);
        }

        return $serviceLabels;
    }

    private function generateProxyLabels(
        Collection $serviceLabels,
        Collection $fqdns,
        string $serviceName,
        mixed $image
    ): Collection {
        $shouldGenerateLabelsExactly = $this->resource->destination->server->settings->generate_exact_labels;
        $uuid = $this->resource->uuid;
        $network = data_get($this->resource, 'destination.network');

        if ($this->isPullRequest) {
            $uuid = "{$this->resource->uuid}-{$this->pullRequestId}";
            $network = "{$this->resource->destination->network}-{$this->pullRequestId}";
        }

        if ($shouldGenerateLabelsExactly) {
            switch ($this->server->proxyType()) {
                case ProxyTypes::TRAEFIK->value:
                    $serviceLabels = $serviceLabels->merge(fqdnLabelsForTraefik(
                        uuid: $uuid,
                        domains: $fqdns,
                        is_force_https_enabled: true,
                        serviceLabels: $serviceLabels,
                        is_gzip_enabled: $this->resource->isGzipEnabled(),
                        is_stripprefix_enabled: $this->resource->isStripprefixEnabled(),
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
                        is_gzip_enabled: $this->resource->isGzipEnabled(),
                        is_stripprefix_enabled: $this->resource->isStripprefixEnabled(),
                        service_name: $serviceName,
                        image: $image,
                        predefinedPort: null
                    ));
                    break;
            }
        } else {
            $serviceLabels = $serviceLabels->merge(fqdnLabelsForTraefik(
                uuid: $uuid,
                domains: $fqdns,
                is_force_https_enabled: true,
                serviceLabels: $serviceLabels,
                is_gzip_enabled: $this->resource->isGzipEnabled(),
                is_stripprefix_enabled: $this->resource->isStripprefixEnabled(),
                service_name: $serviceName,
                image: $image
            ));
            $serviceLabels = $serviceLabels->merge(fqdnLabelsForCaddy(
                network: $network,
                uuid: $uuid,
                domains: $fqdns,
                is_force_https_enabled: true,
                serviceLabels: $serviceLabels,
                is_gzip_enabled: $this->resource->isGzipEnabled(),
                is_stripprefix_enabled: $this->resource->isStripprefixEnabled(),
                service_name: $serviceName,
                image: $image,
                predefinedPort: null
            ));
        }

        return $serviceLabels;
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
        Collection $dependsOn,
        string $serviceName,
        mixed $image
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

            if (data_get($this->resource, 'settings.connect_to_docker_network')) {
                $network = $this->resource->destination->network;
                $networksTemp->put($network, null);
                $this->topLevel->get('networks')->put($network, [
                    'name' => $network,
                    'external' => true,
                ]);
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

        // Inject commit-based image tag for services with build directive
        $hasBuild = data_get($service, 'build') !== null;
        $hasImage = data_get($service, 'image') !== null;
        if ($hasBuild && ! $hasImage && $this->commit) {
            $imageTag = str($this->commit)->substr(0, 128)->value();
            if ($this->isPullRequest) {
                $imageTag = "pr-{$this->pullRequestId}";
            }
            $imageRepo = "{$this->uuid}_{$serviceName}";
            $payload['image'] = "{$imageRepo}:{$imageTag}";
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
            ray('Failed to update docker_compose_raw in applicationParser: '.$e->getMessage());
        }

        data_forget($this->resource, 'environment_variables');
        data_forget($this->resource, 'environment_variables_preview');
        $this->resource->save();
    }
}
