<?php

namespace App\Actions\Application;

use App\Actions\Application\Concerns\CreatesApplication;
use App\Enums\BuildPackTypes;
use App\Models\Application;
use App\Models\GithubApp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Url\Url;

class CreatePublicApplication
{
    use AsAction;
    use CreatesApplication;

    public function handle(Request $request, int $teamId): \Illuminate\Http\JsonResponse
    {
        // Common validation
        $validationError = $this->validateCommonRequest($request, $teamId);
        if ($validationError) {
            return $validationError;
        }

        // Validate environment
        $envResult = $this->validateEnvironment($request, $teamId);
        if (isset($envResult['error'])) {
            return $envResult['error'];
        }
        $environment = $envResult['environment'];

        // Validate server
        $serverResult = $this->validateServer($request, $teamId);
        if (isset($serverResult['error'])) {
            return $serverResult['error'];
        }
        $server = $serverResult['server'];
        $destination = $serverResult['destination'];

        // Validate nginx configuration
        $customNginxConfiguration = $request->custom_nginx_configuration;
        $nginxError = $this->validateNginxConfiguration($customNginxConfiguration);
        if ($nginxError) {
            return $nginxError;
        }
        if ($customNginxConfiguration) {
            $customNginxConfiguration = base64_decode($customNginxConfiguration);
        }

        // Type-specific validation
        $validationRules = [
            'git_repository' => ['string', 'required', new \App\Rules\ValidGitRepositoryUrl],
            'git_branch' => ['string', 'required', new \App\Rules\ValidGitBranch],
            'build_pack' => ['required', Rule::enum(BuildPackTypes::class)],
            'ports_exposes' => 'string|regex:/^(\d+)(,\d+)*$/|required',
            'docker_compose_location' => ['string', 'regex:/^[a-zA-Z0-9._\\/\\-]+$/'],
            'docker_compose_raw' => 'string|nullable',
            'docker_compose_domains' => 'array|nullable',
        ];

        // ports_exposes is not required for dockercompose
        if ($request->build_pack === 'dockercompose') {
            $validationRules['ports_exposes'] = 'string';
            $request->offsetSet('ports_exposes', '80');
        }

        $validationRules = array_merge(sharedDataApplications(), $validationRules);
        $validator = customApiValidator($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! $request->has('name')) {
            $request->offsetSet('name', generate_application_name($request->git_repository, $request->git_branch));
        }

        // Validate application data
        $dataError = $this->validateDataApplications($request, $server);
        if ($dataError) {
            return $dataError;
        }

        // Create application
        $application = new Application;
        removeUnnecessaryFieldsFromRequest($request);
        $application->fill($request->all());

        // Handle docker compose domains
        $dockerComposeDomainsJson = collect();
        if ($request->has('docker_compose_domains')) {
            $dockerComposeDomains = collect($request->docker_compose_domains);
            if ($dockerComposeDomains->count() > 0) {
                $dockerComposeDomains->each(function ($domain, $key) use ($dockerComposeDomainsJson) {
                    $dockerComposeDomainsJson->put(data_get($domain, 'name'), ['domain' => data_get($domain, 'domain')]);
                });
            }
            $request->offsetUnset('docker_compose_domains');
        }
        if ($dockerComposeDomainsJson->count() > 0) {
            $application->docker_compose_domains = $dockerComposeDomainsJson;
        }

        // Parse git repository
        $repository_url_parsed = Url::fromString($request->git_repository);
        $git_host = $repository_url_parsed->getHost();
        if ($git_host === 'github.com') {
            $application->source_type = GithubApp::class;
            $application->source_id = GithubApp::find(0)->id;
        }

        $application->git_repository = str($repository_url_parsed->getSegment(1).'/'.$repository_url_parsed->getSegment(2))->trim()->toString();
        $application->fqdn = $request->domains;
        $application->destination_id = $destination->id;
        $application->destination_type = $destination->getMorphClass();
        $application->environment_id = $environment->id;
        $application->save();

        // Apply common settings
        $this->applyCommonSettings(
            $application,
            $server,
            $request->domains,
            $request->boolean('autogenerate_domain', true),
            $request->is_static,
            $request->connect_to_docker_network,
            $request->use_build_server
        );

        // Handle instant deploy
        $instantDeploy = $request->instant_deploy;
        if ($instantDeploy) {
            $deployResult = $this->handleInstantDeploy($application, true);
            if ($deployResult && isset($deployResult['skipped'])) {
                return response()->json(['message' => $deployResult['message']], 200);
            }
        } else {
            if ($application->build_pack === 'dockercompose') {
                LoadComposeFile::dispatch($application);
            }
        }

        return $this->createSuccessResponse($application);
    }
}
