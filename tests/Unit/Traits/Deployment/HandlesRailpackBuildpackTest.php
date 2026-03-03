<?php

namespace Tests\Unit\Traits\Deployment;

use Tests\TestCase;

/**
 * Unit tests for HandlesRailpackBuildpack deployment trait.
 *
 * Railpack is the successor to Nixpacks — zero-config builder using BuildKit.
 * Tests use source-level assertions since SSH execution cannot run in unit tests.
 */
class HandlesRailpackBuildpackTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = file_get_contents(
            app_path('Traits/Deployment/HandlesRailpackBuildpack.php')
        );
    }

    // =========================================================================
    // Railpack installation
    // =========================================================================

    public function test_railpack_install_check_uses_command_which(): void
    {
        $this->assertStringContainsString('command -v railpack', $this->source);
    }

    public function test_railpack_install_uses_official_install_script(): void
    {
        $this->assertStringContainsString('https://railpack.com/install.sh', $this->source);
    }

    public function test_railpack_install_only_if_missing(): void
    {
        // Must check before installing to avoid re-installing on every deploy
        $this->assertStringContainsString('missing', $this->source);
    }

    // =========================================================================
    // BuildKit daemon management
    // =========================================================================

    public function test_buildkitd_uses_docker_inspect_to_check_status(): void
    {
        $this->assertStringContainsString('docker inspect buildkitd', $this->source);
    }

    public function test_buildkitd_starts_as_privileged_container(): void
    {
        // buildkitd requires --privileged to work with kernel namespaces
        $this->assertStringContainsString('--privileged', $this->source);
    }

    public function test_buildkitd_starts_with_restart_policy(): void
    {
        // Persistent container — survives server reboots
        $this->assertStringContainsString('--restart=unless-stopped', $this->source);
    }

    public function test_buildkitd_uses_official_moby_image(): void
    {
        $this->assertStringContainsString('moby/buildkit', $this->source);
    }

    // =========================================================================
    // Railpack build command
    // =========================================================================

    public function test_build_cmd_uses_buildkit_host_with_buildkitd(): void
    {
        $this->assertStringContainsString('BUILDKIT_HOST=docker-container://buildkitd', $this->source);
    }

    public function test_build_cmd_uses_railpack_build(): void
    {
        $this->assertStringContainsString('railpack build', $this->source);
    }

    public function test_build_cmd_sets_image_name(): void
    {
        $this->assertStringContainsString('--name', $this->source);
    }

    public function test_build_cmd_supports_no_cache_for_force_rebuild(): void
    {
        $this->assertStringContainsString('--no-cache', $this->source);
    }

    // =========================================================================
    // Railpack plan generation
    // =========================================================================

    public function test_plan_generation_uses_railpack_prepare(): void
    {
        $this->assertStringContainsString('railpack prepare', $this->source);
    }

    public function test_plan_saved_to_artifacts_directory(): void
    {
        $this->assertStringContainsString('RAILPACK_PLAN_PATH', $this->source);
    }

    public function test_plan_output_flag_used(): void
    {
        $this->assertStringContainsString('--plan-out', $this->source);
    }

    public function test_plan_file_cleaned_up_after_build(): void
    {
        // Source uses 'rm -f '.self::RAILPACK_PLAN_PATH constant reference
        $this->assertStringContainsString("'rm -f '.self::RAILPACK_PLAN_PATH", $this->source);
    }

    // =========================================================================
    // Port auto-detection
    // =========================================================================

    public function test_port_auto_detection_checks_railpack_plan(): void
    {
        $this->assertStringContainsString('autoDetectPortFromRailpack', $this->source);
    }

    public function test_port_detection_reads_deploy_variables(): void
    {
        $this->assertStringContainsString('deploy.variables.PORT', $this->source);
    }

    // =========================================================================
    // Environment variables
    // =========================================================================

    public function test_railpack_env_vars_passed_with_env_flag(): void
    {
        $this->assertStringContainsString('--env ', $this->source);
    }

    public function test_railpack_env_vars_use_escapeshellarg(): void
    {
        // SECURITY: env var values must be shell-escaped to prevent injection
        $this->assertStringContainsString('escapeshellarg', $this->source);
    }

    public function test_saturn_env_vars_included_in_build(): void
    {
        $this->assertStringContainsString('generate_saturn_env_variables', $this->source);
    }

    // =========================================================================
    // Deployment flow
    // =========================================================================

    public function test_deploy_method_exists(): void
    {
        $this->assertStringContainsString('function deploy_railpack_buildpack', $this->source);
    }

    public function test_deploy_calls_clone_repository(): void
    {
        $this->assertStringContainsString('clone_repository', $this->source);
    }

    public function test_deploy_calls_rolling_update(): void
    {
        $this->assertStringContainsString('rolling_update', $this->source);
    }

    public function test_deploy_calls_push_to_docker_registry(): void
    {
        $this->assertStringContainsString('push_to_docker_registry', $this->source);
    }

    public function test_deploy_auto_detects_dockerfile(): void
    {
        // Railpack should also switch to Dockerfile buildpack if Dockerfile found
        $this->assertStringContainsString('autoDetectAndSwitchToDockerfile', $this->source);
    }

    // =========================================================================
    // RAILPACK_* env var support in Application model
    // =========================================================================

    public function test_application_model_has_railpack_env_var_relations(): void
    {
        $modelSource = file_get_contents(app_path('Models/Application.php'));

        $this->assertStringContainsString('function railpack_environment_variables()', $modelSource);
        $this->assertStringContainsString('function railpack_environment_variables_preview()', $modelSource);
        $this->assertStringContainsString("'RAILPACK_%'", $modelSource);
    }

    // =========================================================================
    // Enum
    // =========================================================================

    public function test_railpack_case_exists_in_buildpack_types_enum(): void
    {
        $enumSource = file_get_contents(app_path('Enums/BuildPackTypes.php'));

        $this->assertStringContainsString("case RAILPACK = 'railpack'", $enumSource);
    }

    private const RAILPACK_PLAN_PATH_VALUE = '/artifacts/railpack-plan.json';
}
