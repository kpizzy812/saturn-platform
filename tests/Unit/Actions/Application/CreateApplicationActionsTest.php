<?php

namespace Tests\Unit\Actions\Application;

use Tests\TestCase;

/**
 * Unit tests for Application Creation Actions:
 * CreateDockerComposeApplication, CreateDockerfileApplication,
 * CreateDockerImageApplication, CreatePublicApplication,
 * CreatePrivateGhAppApplication, CreatePrivateDeployKeyApplication.
 */
class CreateApplicationActionsTest extends TestCase
{
    // =========================================================================
    // CreateDockerComposeApplication
    // =========================================================================

    /** @test */
    public function docker_compose_validates_base64_encoded_raw(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerComposeApplication.php'));

        $this->assertStringContainsString('The docker_compose_raw should be base64 encoded.', $source);
    }

    /** @test */
    public function docker_compose_requires_docker_compose_raw(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerComposeApplication.php'));

        $this->assertStringContainsString("'docker_compose_raw' => 'string|required'", $source);
    }

    /** @test */
    public function docker_compose_creates_service_model(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerComposeApplication.php'));

        $this->assertStringContainsString('Service', $source);
        $this->assertStringContainsString('$service->parse(isNew: true)', $source);
        $this->assertStringContainsString('applyServiceApplicationPrerequisites()', $source);
    }

    /** @test */
    public function docker_compose_supports_instant_deploy(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerComposeApplication.php'));

        $this->assertStringContainsString('instant_deploy', $source);
        $this->assertStringContainsString('StartService::dispatch($service)', $source);
    }

    /** @test */
    public function docker_compose_generates_auto_name(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerComposeApplication.php'));

        $this->assertStringContainsString("'service'", $source);
        $this->assertStringContainsString('new Cuid2', $source);
    }

    /** @test */
    public function docker_compose_validates_yaml(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerComposeApplication.php'));

        $this->assertStringContainsString('Yaml::parse(', $source);
        $this->assertStringContainsString('DUMP_MULTI_LINE_LITERAL_BLOCK', $source);
    }

    /** @test */
    public function docker_compose_validates_environment_and_server(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerComposeApplication.php'));

        $this->assertStringContainsString('validateEnvironment()', $source);
        $this->assertStringContainsString('validateServer()', $source);
    }

    // =========================================================================
    // CreateDockerfileApplication
    // =========================================================================

    /** @test */
    public function dockerfile_validates_base64_encoding(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerfileApplication.php'));

        $this->assertStringContainsString('The dockerfile should be base64 encoded.', $source);
    }

    /** @test */
    public function dockerfile_requires_dockerfile_field(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerfileApplication.php'));

        $this->assertStringContainsString("'dockerfile' => 'string|required'", $source);
    }

    /** @test */
    public function dockerfile_sets_build_pack_to_dockerfile(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerfileApplication.php'));

        $this->assertStringContainsString("'build_pack' => 'dockerfile'", $source);
    }

    /** @test */
    public function dockerfile_extracts_port_from_content(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerfileApplication.php'));

        $this->assertStringContainsString('get_port_from_dockerfile($request->dockerfile)', $source);
    }

    /** @test */
    public function dockerfile_sets_default_git_repo(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerfileApplication.php'));

        $this->assertStringContainsString("'git_repository' => 'coollabsio/coolify'", $source);
        $this->assertStringContainsString("'git_branch' => 'main'", $source);
    }

    /** @test */
    public function dockerfile_applies_common_settings(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerfileApplication.php'));

        $this->assertStringContainsString('applyCommonSettings(', $source);
        $this->assertStringContainsString('handleInstantDeploy(', $source);
    }

    /** @test */
    public function dockerfile_supports_worker_app_type(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerfileApplication.php'));

        $this->assertStringContainsString("'application_type' => 'string|in:web,worker,both'", $source);
    }

    // =========================================================================
    // CreateDockerImageApplication
    // =========================================================================

    /** @test */
    public function docker_image_requires_image_name(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerImageApplication.php'));

        $this->assertStringContainsString("'docker_registry_image_name' => 'string|required'", $source);
    }

    /** @test */
    public function docker_image_sets_dockerimage_build_pack(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerImageApplication.php'));

        $this->assertStringContainsString("'build_pack' => 'dockerimage'", $source);
    }

    /** @test */
    public function docker_image_uses_docker_image_parser(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerImageApplication.php'));

        $this->assertStringContainsString('DockerImageParser', $source);
        $this->assertStringContainsString('$parser->parse(', $source);
        $this->assertStringContainsString('$parser->getFullImageNameWithoutTag()', $source);
        $this->assertStringContainsString('$parser->getTag()', $source);
    }

    /** @test */
    public function docker_image_handles_sha256_hash(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerImageApplication.php'));

        $this->assertStringContainsString('$parser->isImageHash()', $source);
        $this->assertStringContainsString('@sha256', $source);
    }

    /** @test */
    public function docker_image_generates_auto_name(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreateDockerImageApplication.php'));

        $this->assertStringContainsString("'docker-image-'", $source);
        $this->assertStringContainsString('new Cuid2', $source);
    }

    // =========================================================================
    // CreatePublicApplication
    // =========================================================================

    /** @test */
    public function public_app_validates_git_repository_url(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePublicApplication.php'));

        $this->assertStringContainsString('ValidGitRepositoryUrl', $source);
        $this->assertStringContainsString('ValidGitBranch', $source);
    }

    /** @test */
    public function public_app_requires_build_pack_enum(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePublicApplication.php'));

        $this->assertStringContainsString('Rule::enum(BuildPackTypes::class)', $source);
    }

    /** @test */
    public function public_app_detects_github_source(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePublicApplication.php'));

        $this->assertStringContainsString('GithubApp::class', $source);
        $this->assertStringContainsString('GithubApp::find(0)', $source);
    }

    /** @test */
    public function public_app_validates_nginx_configuration(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePublicApplication.php'));

        $this->assertStringContainsString('validateNginxConfiguration()', $source);
    }

    /** @test */
    public function public_app_handles_docker_compose_domains(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePublicApplication.php'));

        $this->assertStringContainsString('docker_compose_domains', $source);
    }

    /** @test */
    public function public_app_uses_generate_application_name(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePublicApplication.php'));

        $this->assertStringContainsString('generate_application_name(', $source);
    }

    /** @test */
    public function public_app_dispatches_load_compose_for_compose_buildpack(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePublicApplication.php'));

        $this->assertStringContainsString('LoadComposeFile::dispatch($application)', $source);
    }

    // =========================================================================
    // CreatePrivateGhAppApplication
    // =========================================================================

    /** @test */
    public function gh_app_requires_github_app_uuid(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateGhAppApplication.php'));

        $this->assertStringContainsString("'github_app_uuid' => 'string|required'", $source);
    }

    /** @test */
    public function gh_app_generates_installation_token(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateGhAppApplication.php'));

        $this->assertStringContainsString('generateGithubInstallationToken($githubApp)', $source);
    }

    /** @test */
    public function gh_app_returns_error_on_missing_app(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateGhAppApplication.php'));

        $this->assertStringContainsString('Github App not found.', $source);
    }

    /** @test */
    public function gh_app_returns_error_on_token_failure(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateGhAppApplication.php'));

        $this->assertStringContainsString('Failed to generate Github App token.', $source);
    }

    /** @test */
    public function gh_app_loads_repositories_paginated(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateGhAppApplication.php'));

        $this->assertStringContainsString('loadRepositoryByPage(', $source);
        $this->assertStringContainsString('total_count', $source);
    }

    /** @test */
    public function gh_app_validates_repository_exists(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateGhAppApplication.php'));

        $this->assertStringContainsString('Repository not found.', $source);
        $this->assertStringContainsString('repository_project_id', $source);
    }

    /** @test */
    public function gh_app_sets_source_to_github_app(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateGhAppApplication.php'));

        $this->assertStringContainsString('$githubApp->getMorphClass()', $source);
        $this->assertStringContainsString('$githubApp->id', $source);
    }

    // =========================================================================
    // CreatePrivateDeployKeyApplication
    // =========================================================================

    /** @test */
    public function deploy_key_requires_private_key_uuid(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateDeployKeyApplication.php'));

        $this->assertStringContainsString("'private_key_uuid' => 'string|required'", $source);
    }

    /** @test */
    public function deploy_key_validates_private_key_exists(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateDeployKeyApplication.php'));

        $this->assertStringContainsString('Private Key not found.', $source);
    }

    /** @test */
    public function deploy_key_sets_private_key_id(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateDeployKeyApplication.php'));

        $this->assertStringContainsString('private_key_id', $source);
        $this->assertStringContainsString('$privateKey->id', $source);
    }

    /** @test */
    public function deploy_key_validates_docker_compose_raw_base64(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateDeployKeyApplication.php'));

        $this->assertStringContainsString('The docker_compose_raw should be base64 encoded.', $source);
    }

    /** @test */
    public function deploy_key_validates_git_repository_url(): void
    {
        $source = file_get_contents(app_path('Actions/Application/CreatePrivateDeployKeyApplication.php'));

        $this->assertStringContainsString('ValidGitRepositoryUrl', $source);
        $this->assertStringContainsString('ValidGitBranch', $source);
    }

    // =========================================================================
    // Shared trait — CreatesApplication
    // =========================================================================

    /** @test */
    public function creates_application_trait_validates_environment(): void
    {
        $source = file_get_contents(app_path('Actions/Application/Concerns/CreatesApplication.php'));

        $this->assertStringContainsString('validateEnvironment()', $source);
        $this->assertStringContainsString('Project not found.', $source);
        $this->assertStringContainsString('Environment not found.', $source);
    }

    /** @test */
    public function creates_application_trait_validates_server(): void
    {
        $source = file_get_contents(app_path('Actions/Application/Concerns/CreatesApplication.php'));

        $this->assertStringContainsString('validateServer()', $source);
        $this->assertStringContainsString('Server not found.', $source);
        $this->assertStringContainsString('Server has no destinations.', $source);
        $this->assertStringContainsString('Server has multiple destinations', $source);
    }

    /** @test */
    public function creates_application_trait_handles_worker_type(): void
    {
        $source = file_get_contents(app_path('Actions/Application/Concerns/CreatesApplication.php'));

        $this->assertStringContainsString('worker', $source);
        $this->assertStringContainsString('health_check_enabled', $source);
    }

    /** @test */
    public function creates_application_trait_supports_domain_autogeneration(): void
    {
        $source = file_get_contents(app_path('Actions/Application/Concerns/CreatesApplication.php'));

        $this->assertStringContainsString('generateSubdomainFromName()', $source);
        $this->assertStringContainsString('generateUrl(', $source);
    }

    /** @test */
    public function creates_application_trait_handles_instant_deploy(): void
    {
        $source = file_get_contents(app_path('Actions/Application/Concerns/CreatesApplication.php'));

        $this->assertStringContainsString('handleInstantDeploy()', $source);
        $this->assertStringContainsString('queue_application_deployment(', $source);
        $this->assertStringContainsString('no_questions_asked: true', $source);
        $this->assertStringContainsString('is_api: true', $source);
    }

    /** @test */
    public function creates_application_trait_returns_201_with_uuid(): void
    {
        $source = file_get_contents(app_path('Actions/Application/Concerns/CreatesApplication.php'));

        $this->assertStringContainsString('setStatusCode(201)', $source);
        $this->assertStringContainsString('$application->uuid', $source);
    }

    /** @test */
    public function creates_application_trait_validates_data_applications(): void
    {
        $source = file_get_contents(app_path('Actions/Application/Concerns/CreatesApplication.php'));

        $this->assertStringContainsString('validateDataApplications()', $source);
    }

    /** @test */
    public function creates_application_trait_generates_container_labels(): void
    {
        $source = file_get_contents(app_path('Actions/Application/Concerns/CreatesApplication.php'));

        $this->assertStringContainsString('is_container_label_readonly_enabled', $source);
        $this->assertStringContainsString('generateLabelsApplication(', $source);
    }

    /** @test */
    public function creates_application_trait_sets_config_changed(): void
    {
        $source = file_get_contents(app_path('Actions/Application/Concerns/CreatesApplication.php'));

        $this->assertStringContainsString('isConfigurationChanged(true)', $source);
    }
}
