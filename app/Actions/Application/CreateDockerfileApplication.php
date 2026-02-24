<?php

namespace App\Actions\Application;

use App\Actions\Application\Concerns\CreatesApplication;
use App\Models\Application;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Visus\Cuid2\Cuid2;

class CreateDockerfileApplication
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
            'dockerfile' => 'string|required',
            'application_type' => 'string|in:web,worker,both',
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
            $request->offsetSet('name', 'dockerfile-'.new Cuid2);
        }

        // Validate application data
        $dataError = $this->validateDataApplications($request, $server);
        if ($dataError) {
            return $dataError;
        }

        // Validate dockerfile encoding
        if (! isBase64Encoded($request->dockerfile)) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => [
                    'dockerfile' => 'The dockerfile should be base64 encoded.',
                ],
            ], 422);
        }
        $dockerFile = base64_decode($request->dockerfile);
        if (mb_detect_encoding($dockerFile, 'ASCII', true) === false) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => [
                    'dockerfile' => 'The dockerfile should be base64 encoded.',
                ],
            ], 422);
        }
        $dockerFile = base64_decode($request->dockerfile);
        removeUnnecessaryFieldsFromRequest($request);

        $port = get_port_from_dockerfile($request->dockerfile);
        if (! $port && $request->application_type !== 'worker') {
            $port = 80;
        }

        // Create application
        $application = new Application;
        $application->fill($request->all());
        $application->fqdn = $request->domains;
        $application->ports_exposes = $port ? (string) $port : null;
        $application->build_pack = 'dockerfile';
        $application->dockerfile = $dockerFile;
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
