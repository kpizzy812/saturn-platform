<?php

namespace App\Actions\Application;

use App\Actions\Application\Concerns\CreatesApplication;
use App\Enums\BuildPackTypes;
use App\Models\Application;
use App\Models\PrivateKey;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Yaml\Yaml;

class CreatePrivateDeployKeyApplication
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
            'git_repository' => ['string', 'required', new \App\Rules\ValidGitRepositoryUrl],
            'git_branch' => ['string', 'required', new \App\Rules\ValidGitBranch],
            'build_pack' => ['required', Rule::enum(BuildPackTypes::class)],
            'ports_exposes' => 'string|regex:/^(\d+)(,\d+)*$/|required',
            'private_key_uuid' => 'string|required',
            'watch_paths' => 'string|nullable',
            'docker_compose_location' => ['string', 'regex:/^[a-zA-Z0-9._\\/\\-]+$/'],
            'docker_compose_raw' => 'string|nullable',
        ];

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
        if ($request->build_pack === 'dockercompose') {
            $request->offsetSet('ports_exposes', '80');
        }

        // Validate application data
        $dataError = $this->validateDataApplications($request, $server);
        if ($dataError) {
            return $dataError;
        }

        // Validate private key
        $privateKey = PrivateKey::whereTeamId($teamId)->where('uuid', $request->private_key_uuid)->first();
        if (! $privateKey) {
            return response()->json(['message' => 'Private Key not found.'], 404);
        }

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
            $dockerComposeRaw = base64_decode($request->docker_compose_raw);
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
        $application->private_key_id = $privateKey->id;
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
