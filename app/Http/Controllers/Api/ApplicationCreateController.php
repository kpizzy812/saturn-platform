<?php

namespace App\Http\Controllers\Api;

use App\Actions\Application\CreateDockerComposeApplication;
use App\Actions\Application\CreateDockerfileApplication;
use App\Actions\Application\CreateDockerImageApplication;
use App\Actions\Application\CreatePrivateDeployKeyApplication;
use App\Actions\Application\CreatePrivateGhAppApplication;
use App\Actions\Application\CreatePublicApplication;
use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ApplicationCreateController extends Controller
{
    #[OA\Post(
        summary: 'Create (Public)',
        description: 'Create new application based on a public git repository.',
        path: '/applications/public',
        operationId: 'create-public-application',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Applications'],
        requestBody: new OA\RequestBody(
            description: 'Application object that needs to be created.',
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        required: ['project_uuid', 'server_uuid', 'environment_name', 'environment_uuid', 'git_repository', 'git_branch', 'build_pack', 'ports_exposes'],
                        properties: [
                            new OA\Property(property: 'project_uuid', type: 'string', description: 'The project UUID.'),
                            new OA\Property(property: 'server_uuid', type: 'string', description: 'The server UUID.'),
                            new OA\Property(property: 'environment_name', type: 'string', description: 'The environment name.'),
                            new OA\Property(property: 'environment_uuid', type: 'string', description: 'The environment UUID.'),
                            new OA\Property(property: 'git_repository', type: 'string', description: 'The git repository URL.'),
                            new OA\Property(property: 'git_branch', type: 'string', description: 'The git branch.'),
                            new OA\Property(property: 'build_pack', type: 'string', enum: ['nixpacks', 'static', 'dockerfile', 'dockercompose'], description: 'The build pack type.'),
                            new OA\Property(property: 'ports_exposes', type: 'string', description: 'The ports to expose.'),
                            new OA\Property(property: 'instant_deploy', type: 'boolean', description: 'Deploy instantly after creation.'),
                        ],
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 201, description: 'Application created successfully.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 400, ref: '#/components/responses/400'),
            new OA\Response(response: 409, description: 'Domain conflicts detected.'),
        ]
    )]
    public function create_public_application(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $this->authorize('create', Application::class);

        return CreatePublicApplication::run($request, $teamId);
    }

    #[OA\Post(
        summary: 'Create (Private - GH App)',
        description: 'Create new application based on a private repository through a Github App.',
        path: '/applications/private-github-app',
        operationId: 'create-private-github-app-application',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Applications'],
        requestBody: new OA\RequestBody(
            description: 'Application object that needs to be created.',
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        required: ['project_uuid', 'server_uuid', 'environment_name', 'environment_uuid', 'github_app_uuid', 'git_repository', 'git_branch', 'build_pack', 'ports_exposes'],
                        properties: [
                            new OA\Property(property: 'project_uuid', type: 'string', description: 'The project UUID.'),
                            new OA\Property(property: 'server_uuid', type: 'string', description: 'The server UUID.'),
                            new OA\Property(property: 'environment_name', type: 'string', description: 'The environment name.'),
                            new OA\Property(property: 'environment_uuid', type: 'string', description: 'The environment UUID.'),
                            new OA\Property(property: 'github_app_uuid', type: 'string', description: 'The Github App UUID.'),
                            new OA\Property(property: 'git_repository', type: 'string', description: 'The git repository URL.'),
                            new OA\Property(property: 'git_branch', type: 'string', description: 'The git branch.'),
                            new OA\Property(property: 'build_pack', type: 'string', enum: ['nixpacks', 'static', 'dockerfile', 'dockercompose'], description: 'The build pack type.'),
                            new OA\Property(property: 'ports_exposes', type: 'string', description: 'The ports to expose.'),
                            new OA\Property(property: 'instant_deploy', type: 'boolean', description: 'Deploy instantly after creation.'),
                        ],
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 201, description: 'Application created successfully.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 400, ref: '#/components/responses/400'),
            new OA\Response(response: 409, description: 'Domain conflicts detected.'),
        ]
    )]
    public function create_private_gh_app_application(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $this->authorize('create', Application::class);

        return CreatePrivateGhAppApplication::run($request, $teamId);
    }

    #[OA\Post(
        summary: 'Create (Private - Deploy Key)',
        description: 'Create new application based on a private repository through a Deploy Key.',
        path: '/applications/private-deploy-key',
        operationId: 'create-private-deploy-key-application',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Applications'],
        requestBody: new OA\RequestBody(
            description: 'Application object that needs to be created.',
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        required: ['project_uuid', 'server_uuid', 'environment_name', 'environment_uuid', 'private_key_uuid', 'git_repository', 'git_branch', 'build_pack', 'ports_exposes'],
                        properties: [
                            new OA\Property(property: 'project_uuid', type: 'string', description: 'The project UUID.'),
                            new OA\Property(property: 'server_uuid', type: 'string', description: 'The server UUID.'),
                            new OA\Property(property: 'environment_name', type: 'string', description: 'The environment name.'),
                            new OA\Property(property: 'environment_uuid', type: 'string', description: 'The environment UUID.'),
                            new OA\Property(property: 'private_key_uuid', type: 'string', description: 'The private key UUID.'),
                            new OA\Property(property: 'git_repository', type: 'string', description: 'The git repository URL.'),
                            new OA\Property(property: 'git_branch', type: 'string', description: 'The git branch.'),
                            new OA\Property(property: 'build_pack', type: 'string', enum: ['nixpacks', 'static', 'dockerfile', 'dockercompose'], description: 'The build pack type.'),
                            new OA\Property(property: 'ports_exposes', type: 'string', description: 'The ports to expose.'),
                            new OA\Property(property: 'instant_deploy', type: 'boolean', description: 'Deploy instantly after creation.'),
                        ],
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 201, description: 'Application created successfully.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 400, ref: '#/components/responses/400'),
            new OA\Response(response: 409, description: 'Domain conflicts detected.'),
        ]
    )]
    public function create_private_deploy_key_application(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $this->authorize('create', Application::class);

        return CreatePrivateDeployKeyApplication::run($request, $teamId);
    }

    #[OA\Post(
        summary: 'Create (Dockerfile)',
        description: 'Create new application based on a simple Dockerfile.',
        path: '/applications/dockerfile',
        operationId: 'create-dockerfile-application',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Applications'],
        requestBody: new OA\RequestBody(
            description: 'Application object that needs to be created.',
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        required: ['project_uuid', 'server_uuid', 'environment_name', 'environment_uuid', 'dockerfile'],
                        properties: [
                            new OA\Property(property: 'project_uuid', type: 'string', description: 'The project UUID.'),
                            new OA\Property(property: 'server_uuid', type: 'string', description: 'The server UUID.'),
                            new OA\Property(property: 'environment_name', type: 'string', description: 'The environment name.'),
                            new OA\Property(property: 'environment_uuid', type: 'string', description: 'The environment UUID.'),
                            new OA\Property(property: 'dockerfile', type: 'string', description: 'The Dockerfile content (base64 encoded).'),
                            new OA\Property(property: 'instant_deploy', type: 'boolean', description: 'Deploy instantly after creation.'),
                        ],
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 201, description: 'Application created successfully.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 400, ref: '#/components/responses/400'),
            new OA\Response(response: 409, description: 'Domain conflicts detected.'),
        ]
    )]
    public function create_dockerfile_application(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $this->authorize('create', Application::class);

        return CreateDockerfileApplication::run($request, $teamId);
    }

    #[OA\Post(
        summary: 'Create (Docker Image)',
        description: 'Create new application based on a prebuilt docker image',
        path: '/applications/dockerimage',
        operationId: 'create-dockerimage-application',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Applications'],
        requestBody: new OA\RequestBody(
            description: 'Application object that needs to be created.',
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        required: ['project_uuid', 'server_uuid', 'environment_name', 'environment_uuid', 'docker_registry_image_name', 'ports_exposes'],
                        properties: [
                            new OA\Property(property: 'project_uuid', type: 'string', description: 'The project UUID.'),
                            new OA\Property(property: 'server_uuid', type: 'string', description: 'The server UUID.'),
                            new OA\Property(property: 'environment_name', type: 'string', description: 'The environment name.'),
                            new OA\Property(property: 'environment_uuid', type: 'string', description: 'The environment UUID.'),
                            new OA\Property(property: 'docker_registry_image_name', type: 'string', description: 'The docker registry image name.'),
                            new OA\Property(property: 'docker_registry_image_tag', type: 'string', description: 'The docker registry image tag.'),
                            new OA\Property(property: 'ports_exposes', type: 'string', description: 'The ports to expose.'),
                            new OA\Property(property: 'instant_deploy', type: 'boolean', description: 'Deploy instantly after creation.'),
                        ],
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 201, description: 'Application created successfully.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 400, ref: '#/components/responses/400'),
            new OA\Response(response: 409, description: 'Domain conflicts detected.'),
        ]
    )]
    public function create_dockerimage_application(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $this->authorize('create', Application::class);

        return CreateDockerImageApplication::run($request, $teamId);
    }

    #[OA\Post(
        summary: 'Create (Docker Compose)',
        description: 'Create new application based on a docker-compose file.',
        path: '/applications/dockercompose',
        operationId: 'create-dockercompose-application',
        security: [
            ['bearerAuth' => []],
        ],
        tags: ['Applications'],
        requestBody: new OA\RequestBody(
            description: 'Application object that needs to be created.',
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        type: 'object',
                        required: ['project_uuid', 'server_uuid', 'environment_name', 'environment_uuid', 'docker_compose_raw'],
                        properties: [
                            new OA\Property(property: 'project_uuid', type: 'string', description: 'The project UUID.'),
                            new OA\Property(property: 'server_uuid', type: 'string', description: 'The server UUID.'),
                            new OA\Property(property: 'environment_name', type: 'string', description: 'The environment name.'),
                            new OA\Property(property: 'environment_uuid', type: 'string', description: 'The environment UUID.'),
                            new OA\Property(property: 'docker_compose_raw', type: 'string', description: 'The Docker Compose raw content (base64 encoded).'),
                            new OA\Property(property: 'instant_deploy', type: 'boolean', description: 'Deploy instantly after creation.'),
                        ],
                    )
                ),
            ]
        ),
        responses: [
            new OA\Response(response: 201, description: 'Application created successfully.'),
            new OA\Response(response: 401, ref: '#/components/responses/401'),
            new OA\Response(response: 400, ref: '#/components/responses/400'),
            new OA\Response(response: 409, description: 'Domain conflicts detected.'),
        ]
    )]
    public function create_dockercompose_application(Request $request)
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $this->authorize('create', Application::class);

        return CreateDockerComposeApplication::run($request, $teamId);
    }
}
