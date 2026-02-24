<?php

namespace App\Actions\Application;

use App\Actions\Application\Concerns\CreatesApplication;
use App\Enums\BuildPackTypes;
use App\Models\Application;
use App\Models\GithubApp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Yaml\Yaml;

class CreatePrivateGhAppApplication
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

        // Type-specific validation
        $validationRules = [
            'git_repository' => 'string|required',
            'git_branch' => ['string', 'required', new \App\Rules\ValidGitBranch],
            'build_pack' => ['required', Rule::enum(BuildPackTypes::class)],
            'ports_exposes' => 'string|regex:/^(\d+)(,\d+)*$/|required',
            'application_type' => 'string|in:web,worker,both',
            'github_app_uuid' => 'string|required',
            'watch_paths' => 'string|nullable',
            'docker_compose_location' => ['string', 'regex:/^[a-zA-Z0-9._\\/\\-]+$/'],
            'docker_compose_raw' => 'string|nullable',
        ];

        // Workers don't need ports
        if ($request->application_type === 'worker') {
            $validationRules['ports_exposes'] = 'string|regex:/^(\d+)(,\d+)*$/|nullable';
        }

        // Docker compose ports are optional
        if ($request->build_pack === 'dockercompose') {
            $validationRules['ports_exposes'] = 'string|nullable';
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
        if ($request->build_pack === 'dockercompose' && blank($request->ports_exposes)) {
            $request->offsetSet('ports_exposes', null);
        }

        // Validate application data
        $dataError = $this->validateDataApplications($request, $server);
        if ($dataError) {
            return $dataError;
        }

        // Validate GitHub App
        $githubApp = GithubApp::whereTeamId($teamId)->where('uuid', $request->github_app_uuid)->first();
        if (! $githubApp) {
            return response()->json(['message' => 'Github App not found.'], 404);
        }

        $token = generateGithubInstallationToken($githubApp);
        if (! $token) {
            return response()->json(['message' => 'Failed to generate Github App token.'], 400);
        }

        // Load repositories
        $repositories = collect();
        $page = 1;
        $repositories = loadRepositoryByPage($githubApp, $token, $page);
        if ($repositories['total_count'] > 0) {
            while (count($repositories['repositories']) < $repositories['total_count']) {
                $page++;
                $repositories = loadRepositoryByPage($githubApp, $token, $page);
            }
        }

        // Find repository
        $gitRepository = $request->git_repository;
        if (str($gitRepository)->startsWith('http') || str($gitRepository)->contains('github.com')) {
            $gitRepository = str($gitRepository)->replace('https://', '')->replace('http://', '')->replace('github.com/', '');
        }
        $gitRepositoryFound = collect($repositories['repositories'])->firstWhere('full_name', $gitRepository);
        if (! $gitRepositoryFound) {
            return response()->json(['message' => 'Repository not found.'], 404);
        }
        $repository_project_id = data_get($gitRepositoryFound, 'id');

        // Create application
        $application = new Application;
        removeUnnecessaryFieldsFromRequest($request);
        $application->fill($request->all());

        // Handle docker compose domains
        $dockerComposeDomainsJson = collect();
        if ($request->has('docker_compose_domains')) {
            if (! $request->has('docker_compose_raw')) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'docker_compose_raw' => 'The base64 encoded docker_compose_raw is required.',
                    ],
                ], 422);
            }

            if (! isBase64Encoded($request->docker_compose_raw)) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'docker_compose_raw' => 'The docker_compose_raw should be base64 encoded.',
                    ],
                ], 422);
            }
            $dockerComposeRaw = base64_decode($request->docker_compose_raw);
            if (mb_detect_encoding($dockerComposeRaw, 'ASCII', true) === false) {
                return response()->json([
                    'message' => 'Validation failed.',
                    'errors' => [
                        'docker_compose_raw' => 'The docker_compose_raw should be base64 encoded.',
                    ],
                ], 422);
            }
            $yaml = Yaml::parse($dockerComposeRaw);
            $services = data_get($yaml, 'services');
            $dockerComposeDomains = collect($request->docker_compose_domains);
            if ($dockerComposeDomains->count() > 0) {
                $dockerComposeDomains->each(function ($domain, $key) use ($services, $dockerComposeDomainsJson) {
                    $name = data_get($domain, 'name');
                    if (data_get($services, $name)) {
                        $dockerComposeDomainsJson->put($name, ['domain' => data_get($domain, 'domain')]);
                    }
                });
            }
            $request->offsetUnset('docker_compose_domains');
        }
        if ($dockerComposeDomainsJson->count() > 0) {
            $application->docker_compose_domains = $dockerComposeDomainsJson;
        }

        $application->fqdn = $request->domains;
        $application->git_repository = str($gitRepository)->trim()->toString();
        $application->destination_id = $destination->id;
        $application->destination_type = $destination->getMorphClass();
        $application->environment_id = $environment->id;
        $application->source_type = $githubApp->getMorphClass();
        $application->source_id = $githubApp->id;
        $application->repository_project_id = $repository_project_id;

        $application->save();

        // Apply common settings
        $this->applyCommonSettings(
            $application,
            $server,
            $request->domains,
            $request->boolean('autogenerate_domain', true),
            null,
            null,
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
