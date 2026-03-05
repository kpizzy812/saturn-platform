<?php

namespace Tests\Unit\Actions\Project;

use Tests\TestCase;

/**
 * Unit tests for Project Actions: CloneProjectAction, ExportProjectAction.
 */
class ProjectActionsTest extends TestCase
{
    // =========================================================================
    // CloneProjectAction
    // =========================================================================

    /** @test */
    public function clone_project_skips_default_environments(): void
    {
        $source = file_get_contents(app_path('Actions/Project/CloneProjectAction.php'));

        $this->assertStringContainsString("'development'", $source);
        $this->assertStringContainsString("'uat'", $source);
        $this->assertStringContainsString("'production'", $source);
    }

    /** @test */
    public function clone_project_generates_new_uuid_for_environments(): void
    {
        $source = file_get_contents(app_path('Actions/Project/CloneProjectAction.php'));

        $this->assertStringContainsString('new Cuid2', $source);
    }

    /** @test */
    public function clone_project_preserves_team_id(): void
    {
        $source = file_get_contents(app_path('Actions/Project/CloneProjectAction.php'));

        $this->assertStringContainsString('team_id', $source);
        $this->assertStringContainsString('$source->team_id', $source);
    }

    /** @test */
    public function clone_project_copies_description(): void
    {
        $source = file_get_contents(app_path('Actions/Project/CloneProjectAction.php'));

        $this->assertStringContainsString('description', $source);
        $this->assertStringContainsString('$source->description', $source);
    }

    /** @test */
    public function clone_project_supports_clone_shared_vars_flag(): void
    {
        $source = file_get_contents(app_path('Actions/Project/CloneProjectAction.php'));

        $this->assertStringContainsString('cloneSharedVars', $source);
        $this->assertStringContainsString('SharedEnvironmentVariable', $source);
        $this->assertStringContainsString('environment_id', $source);
    }

    /** @test */
    public function clone_project_supports_clone_tags_flag(): void
    {
        $source = file_get_contents(app_path('Actions/Project/CloneProjectAction.php'));

        $this->assertStringContainsString('cloneTags', $source);
        $this->assertStringContainsString('tag', $source);
    }

    /** @test */
    public function clone_project_supports_clone_settings_flag(): void
    {
        $source = file_get_contents(app_path('Actions/Project/CloneProjectAction.php'));

        $this->assertStringContainsString('cloneSettings', $source);
        $this->assertStringContainsString('default_server_id', $source);
    }

    /** @test */
    public function clone_project_preserves_shared_var_properties(): void
    {
        $source = file_get_contents(app_path('Actions/Project/CloneProjectAction.php'));

        $this->assertStringContainsString("'key'", $source);
        $this->assertStringContainsString("'value'", $source);
        $this->assertStringContainsString("'is_shown_once'", $source);
    }

    // =========================================================================
    // ExportProjectAction
    // =========================================================================

    /** @test */
    public function export_project_includes_version(): void
    {
        $source = file_get_contents(app_path('Actions/Project/ExportProjectAction.php'));

        $this->assertStringContainsString("'export_version'", $source);
        $this->assertStringContainsString("'1.0'", $source);
    }

    /** @test */
    public function export_project_includes_timestamp(): void
    {
        $source = file_get_contents(app_path('Actions/Project/ExportProjectAction.php'));

        $this->assertStringContainsString("'exported_at'", $source);
    }

    /** @test */
    public function export_project_includes_project_metadata(): void
    {
        $source = file_get_contents(app_path('Actions/Project/ExportProjectAction.php'));

        $this->assertStringContainsString("'project'", $source);
        $this->assertStringContainsString("'name'", $source);
        $this->assertStringContainsString("'uuid'", $source);
    }

    /** @test */
    public function export_project_masks_secrets_when_not_included(): void
    {
        $source = file_get_contents(app_path('Actions/Project/ExportProjectAction.php'));

        $this->assertStringContainsString('includeSecrets', $source);
        $this->assertStringContainsString('***MASKED***', $source);
    }

    /** @test */
    public function export_project_filters_shared_variables(): void
    {
        $source = file_get_contents(app_path('Actions/Project/ExportProjectAction.php'));

        $this->assertStringContainsString('whereNull', $source);
        $this->assertStringContainsString('environment_id', $source);
    }

    /** @test */
    public function export_project_includes_environments(): void
    {
        $source = file_get_contents(app_path('Actions/Project/ExportProjectAction.php'));

        $this->assertStringContainsString("'environments'", $source);
        $this->assertStringContainsString('requires_approval', $source);
    }

    /** @test */
    public function export_project_includes_tags(): void
    {
        $source = file_get_contents(app_path('Actions/Project/ExportProjectAction.php'));

        $this->assertStringContainsString("'tags'", $source);
        $this->assertStringContainsString("pluck('name')", $source);
    }

    /** @test */
    public function export_project_includes_settings(): void
    {
        $source = file_get_contents(app_path('Actions/Project/ExportProjectAction.php'));

        $this->assertStringContainsString("'settings'", $source);
        $this->assertStringContainsString('default_server_id', $source);
    }
}
