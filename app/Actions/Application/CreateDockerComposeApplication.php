<?php

namespace App\Actions\Application;

use App\Actions\Application\Concerns\CreatesApplication;
use App\Actions\Service\StartService;
use App\Models\Service;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Yaml\Yaml;
use Visus\Cuid2\Cuid2;

class CreateDockerComposeApplication
{
    use AsAction;
    use CreatesApplication;

    protected function getDockerComposeAllowedFields(): array
    {
        return [
            'project_uuid', 'environment_name', 'environment_uuid', 'server_uuid',
            'destination_uuid', 'type', 'name', 'description', 'instant_deploy',
            'docker_compose_raw', 'force_domain_override',
        ];
    }

    public function handle(Request $request, int $teamId): \Illuminate\Http\JsonResponse
    {
        // Validate incoming request
        $return = validateIncomingRequest($request);
        if ($return instanceof \Illuminate\Http\JsonResponse) {
            return $return;
        }

        // Validate allowed fields for docker compose
        $validator = customApiValidator($request->all(), [
            'name' => 'string|max:255',
            'description' => 'string|nullable',
            'project_uuid' => 'string|required',
            'environment_name' => 'string|nullable',
            'environment_uuid' => 'string|nullable',
            'server_uuid' => 'string|required',
            'destination_uuid' => 'string',
        ]);

        $extraFields = array_diff(array_keys($request->all()), $this->getDockerComposeAllowedFields());
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

        if (! $request->has('name')) {
            $request->offsetSet('name', 'service'.new Cuid2);
        }

        // Type-specific validation
        $validationRules = [
            'docker_compose_raw' => 'string|required',
        ];
        $validationRules = array_merge(sharedDataApplications(), $validationRules);
        $validator = customApiValidator($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate application data
        $dataError = $this->validateDataApplications($request, $server);
        if ($dataError) {
            return $dataError;
        }

        // Validate docker compose raw
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
        $dockerCompose = base64_decode($request->docker_compose_raw);
        $dockerComposeRaw = Yaml::dump(Yaml::parse($dockerCompose), 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        // Create service
        $service = new Service;
        removeUnnecessaryFieldsFromRequest($request);
        $service->fill($request->all());

        $service->docker_compose_raw = $dockerComposeRaw;
        $service->environment_id = $environment->id;
        $service->server_id = $server->id;
        $service->destination_id = $destination->id;
        $service->destination_type = $destination->getMorphClass();
        $service->save();

        $service->parse(isNew: true);

        // Apply service-specific application prerequisites
        applyServiceApplicationPrerequisites($service);

        // Handle instant deploy
        if ($request->instant_deploy) {
            StartService::dispatch($service);
        }

        return response()->json(serializeApiResponse([
            'uuid' => data_get($service, 'uuid'),
            'domains' => data_get($service, 'domains'),
        ]))->setStatusCode(201);
    }
}
