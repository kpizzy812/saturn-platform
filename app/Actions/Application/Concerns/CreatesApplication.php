<?php

namespace App\Actions\Application\Concerns;

use App\Models\Project;
use App\Models\Server;
use Illuminate\Http\Request;

trait CreatesApplication
{
    protected function getCommonAllowedFields(): array
    {
        return [
            'project_uuid', 'environment_name', 'environment_uuid', 'server_uuid',
            'destination_uuid', 'type', 'name', 'description', 'is_static', 'domains',
            'git_repository', 'git_branch', 'git_commit_sha', 'private_key_uuid',
            'docker_registry_image_name', 'docker_registry_image_tag', 'build_pack',
            'install_command', 'build_command', 'start_command', 'ports_exposes',
            'ports_mappings', 'base_directory', 'publish_directory',
            'health_check_enabled', 'health_check_path', 'health_check_port',
            'health_check_host', 'health_check_method', 'health_check_return_code',
            'health_check_scheme', 'health_check_response_text', 'health_check_interval',
            'health_check_timeout', 'health_check_retries', 'health_check_start_period',
            'limits_memory', 'limits_memory_swap', 'limits_memory_swappiness',
            'limits_memory_reservation', 'limits_cpus', 'limits_cpuset', 'limits_cpu_shares',
            'custom_labels', 'custom_docker_run_options', 'post_deployment_command',
            'post_deployment_command_container', 'pre_deployment_command',
            'pre_deployment_command_container', 'manual_webhook_secret_github',
            'manual_webhook_secret_gitlab', 'manual_webhook_secret_bitbucket',
            'manual_webhook_secret_gitea', 'redirect', 'github_app_uuid', 'instant_deploy',
            'dockerfile', 'docker_compose_location', 'docker_compose_raw',
            'docker_compose_custom_start_command', 'docker_compose_custom_build_command',
            'docker_compose_domains', 'watch_paths', 'use_build_server', 'static_image',
            'custom_nginx_configuration', 'is_http_basic_auth_enabled',
            'http_basic_auth_username', 'http_basic_auth_password',
            'connect_to_docker_network', 'force_domain_override', 'autogenerate_domain',
        ];
    }

    protected function validateCommonRequest(Request $request, int $teamId): ?\Illuminate\Http\JsonResponse
    {
        $return = validateIncomingRequest($request);
        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }

        $validator = customApiValidator($request->all(), [
            'name' => 'string|max:255',
            'description' => 'string|nullable',
            'project_uuid' => 'string|required',
            'environment_name' => 'string|nullable',
            'environment_uuid' => 'string|nullable',
            'server_uuid' => 'string|required',
            'destination_uuid' => 'string',
            'is_http_basic_auth_enabled' => 'boolean',
            'http_basic_auth_username' => 'string|nullable',
            'http_basic_auth_password' => 'string|nullable',
            'autogenerate_domain' => 'boolean',
        ]);

        $extraFields = array_diff(array_keys($request->all()), $this->getCommonAllowedFields());
        if ($validator->fails() || ! empty($extraFields)) {
            $errors = $validator->errors();
            if (! empty($extraFields)) {
                foreach ($extraFields as $field) {
                    $errors->add($field, 'This field is not allowed.');
                }
            }

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        return null;
    }

    protected function validateEnvironment(Request $request, int $teamId): array
    {
        $environmentUuid = $request->environment_uuid;
        $environmentName = $request->environment_name;

        if (blank($environmentUuid) && blank($environmentName)) {
            return [
                'error' => response()->json([
                    'message' => 'You need to provide at least one of environment_name or environment_uuid.',
                ], 422),
            ];
        }

        $project = Project::whereTeamId($teamId)->whereUuid($request->project_uuid)->first();
        if (! $project) {
            return ['error' => response()->json(['message' => 'Project not found.'], 404)];
        }

        $environment = $project->environments()->where('name', $environmentName)->first();
        if (! $environment) {
            $environment = $project->environments()->where('uuid', $environmentUuid)->first();
        }
        if (! $environment) {
            return ['error' => response()->json(['message' => 'Environment not found.'], 404)];
        }

        return ['project' => $project, 'environment' => $environment];
    }

    protected function validateServer(Request $request, int $teamId): array
    {
        $server = Server::whereTeamId($teamId)->whereUuid($request->server_uuid)->first();
        if (! $server) {
            return ['error' => response()->json(['message' => 'Server not found.'], 404)];
        }

        $destinations = $server->destinations();
        if ($destinations->count() == 0) {
            return ['error' => response()->json(['message' => 'Server has no destinations.'], 400)];
        }
        if ($destinations->count() > 1 && ! $request->has('destination_uuid')) {
            return [
                'error' => response()->json([
                    'message' => 'Server has multiple destinations and you do not set destination_uuid.',
                ], 400),
            ];
        }

        return ['server' => $server, 'destination' => $destinations->first()];
    }

    protected function validateNginxConfiguration(?string $customNginxConfiguration): ?\Illuminate\Http\JsonResponse
    {
        if (is_null($customNginxConfiguration)) {
            return null;
        }

        if (! isBase64Encoded($customNginxConfiguration)) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => [
                    'custom_nginx_configuration' => 'The custom_nginx_configuration should be base64 encoded.',
                ],
            ], 422);
        }

        $decoded = base64_decode($customNginxConfiguration);
        if (mb_detect_encoding($decoded, 'ASCII', true) === false) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => [
                    'custom_nginx_configuration' => 'The custom_nginx_configuration should be base64 encoded.',
                ],
            ], 422);
        }

        return null;
    }

    protected function validateDataApplications(Request $request, Server $server): ?\Illuminate\Http\JsonResponse
    {
        $teamId = getTeamIdFromToken();

        // Validate ports_mappings
        if ($request->has('ports_mappings')) {
            $ports = [];
            foreach (explode(',', $request->ports_mappings) as $portMapping) {
                $port = explode(':', $portMapping);
                if (in_array($port[0], $ports)) {
                    return response()->json([
                        'message' => 'Validation failed.',
                        'errors' => [
                            'ports_mappings' => 'The first number before : should be unique between mappings.',
                        ],
                    ], 422);
                }
                $ports[] = $port[0];
            }
        }

        // Validate custom_labels
        if ($request->has('custom_labels')) {
            if (! isBase64Encoded($request->custom_labels)) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'custom_labels' => 'The custom_labels should be base64 encoded.',
                    ],
                ], 422);
            }
            $customLabels = base64_decode($request->custom_labels);
            if (mb_detect_encoding($customLabels, 'ASCII', true) === false) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'custom_labels' => 'The custom_labels should be base64 encoded.',
                    ],
                ], 422);
            }
        }

        if ($request->has('domains') && $server->isProxyShouldRun()) {
            $uuid = $request->uuid;
            $fqdn = $request->domains;
            $fqdn = str($fqdn)->replaceEnd(',', '')->trim();
            $fqdn = str($fqdn)->replaceStart(',', '')->trim();
            $errors = [];
            $fqdn = str($fqdn)->trim()->explode(',')->map(function ($domain) use (&$errors) {
                $domain = trim($domain);
                if (filter_var($domain, FILTER_VALIDATE_URL) === false) {
                    $errors[] = 'Invalid domain: '.$domain;
                }

                return str($domain)->lower();
            });
            if (count($errors) > 0) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => $errors,
                ], 422);
            }

            // Check for domain conflicts
            $result = checkIfDomainIsAlreadyUsedViaAPI($fqdn, $teamId, $uuid);
            if (isset($result['error'])) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => ['domains' => $result['error']],
                ], 422);
            }

            // If there are conflicts and force is not enabled, return warning
            if ($result['hasConflicts'] && ! $request->boolean('force_domain_override')) {
                return response()->json([
                    'message' => 'Domain conflicts detected. Use force_domain_override=true to proceed.',
                    'conflicts' => $result['conflicts'],
                    'warning' => 'Using the same domain for multiple resources can cause routing conflicts and unpredictable behavior.',
                ], 409);
            }
        }

        return null;
    }

    protected function applyCommonSettings(
        $application,
        Server $server,
        ?string $fqdn,
        bool $autogenerateDomain,
        ?bool $isStatic,
        ?bool $connectToDockerNetwork,
        ?bool $useBuildServer
    ): void {
        if (isset($isStatic)) {
            $application->settings->is_static = $isStatic;
            $application->settings->save();
        }
        if (isset($connectToDockerNetwork)) {
            $application->settings->connect_to_docker_network = $connectToDockerNetwork;
            $application->settings->save();
        }
        if (isset($useBuildServer)) {
            $application->settings->is_build_server_enabled = $useBuildServer;
            $application->settings->save();
        }

        $application->refresh();

        // Auto-generate domain from app name if requested and no custom domain provided
        if ($autogenerateDomain && blank($fqdn)) {
            $projectName = $application->environment?->project?->name;
            $slug = generateSubdomainFromName($application->name, $server, $projectName);
            $application->fqdn = generateUrl(server: $server, random: $slug);
            $application->save();
        }

        if ($application->settings->is_container_label_readonly_enabled) {
            $application->custom_labels = str(implode('|saturn|', generateLabelsApplication($application)))->replace('|saturn|', "\n");
            $application->save();
        }

        $application->isConfigurationChanged(true);
    }

    protected function handleInstantDeploy($application, bool $instantDeploy): ?array
    {
        if (! $instantDeploy) {
            return null;
        }

        $deployment_uuid = new \Visus\Cuid2\Cuid2;

        $result = queue_application_deployment(
            application: $application,
            deployment_uuid: $deployment_uuid,
            no_questions_asked: true,
            is_api: true,
        );

        if ($result['status'] === 'skipped') {
            return ['skipped' => true, 'message' => $result['message']];
        }

        return null;
    }

    protected function createSuccessResponse($application): \Illuminate\Http\JsonResponse
    {
        return response()->json(serializeApiResponse([
            'uuid' => data_get($application, 'uuid'),
            'domains' => data_get($application, 'fqdn'),
        ]))->setStatusCode(201);
    }
}
