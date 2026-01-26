<?php

namespace App\Actions\Application;

use App\Actions\Application\Concerns\CreatesApplication;
use App\Models\Application;
use App\Services\DockerImageParser;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Visus\Cuid2\Cuid2;

class CreateDockerImageApplication
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
            'docker_registry_image_name' => 'string|required',
            'docker_registry_image_tag' => 'string',
            'ports_exposes' => 'string|regex:/^(\d+)(,\d+)*$/|required',
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
            $request->offsetSet('name', 'docker-image-'.new Cuid2);
        }

        // Validate application data
        $dataError = $this->validateDataApplications($request, $server);
        if ($dataError) {
            return $dataError;
        }

        // Process docker image name and tag using DockerImageParser
        $dockerImageName = $request->docker_registry_image_name;
        $dockerImageTag = $request->docker_registry_image_tag;

        // Build the full Docker image string for parsing
        if ($dockerImageTag) {
            $dockerImageString = $dockerImageName.':'.$dockerImageTag;
        } else {
            $dockerImageString = $dockerImageName;
        }

        // Parse using DockerImageParser to normalize the image reference
        $parser = new DockerImageParser;
        $parser->parse($dockerImageString);

        // Get normalized image name and tag
        $normalizedImageName = $parser->getFullImageNameWithoutTag();

        // Append @sha256 to image name if using digest
        if ($parser->isImageHash() && ! str_ends_with($normalizedImageName, '@sha256')) {
            $normalizedImageName .= '@sha256';
        }

        // Set processed values back to request
        $request->offsetSet('docker_registry_image_name', $normalizedImageName);
        $request->offsetSet('docker_registry_image_tag', $parser->getTag());

        // Create application
        $application = new Application;
        removeUnnecessaryFieldsFromRequest($request);

        $application->fill($request->all());
        $application->fqdn = $request->domains;
        $application->build_pack = 'dockerimage';
        $application->destination_id = $destination->id;
        $application->destination_type = $destination->getMorphClass();
        $application->environment_id = $environment->id;

        $application->git_repository = 'coollabsio/coolify';
        $application->git_branch = 'main';
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
        }

        return $this->createSuccessResponse($application);
    }
}
