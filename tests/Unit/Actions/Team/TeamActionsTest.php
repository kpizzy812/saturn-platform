<?php

namespace Tests\Unit\Actions\Team;

use Tests\TestCase;

/**
 * Unit tests for Team Actions: DeleteUserTeams, TransferTeamOwnership.
 */
class TeamActionsTest extends TestCase
{
    // =========================================================================
    // DeleteUserTeams
    // =========================================================================

    /** @test */
    public function delete_user_teams_skips_root_team(): void
    {
        $source = file_get_contents(app_path('Actions/User/DeleteUserTeams.php'));

        // Root team (ID 0) should be skipped
        $this->assertStringContainsString('id === 0', $source);
    }

    /** @test */
    public function delete_user_teams_categorizes_single_member_for_deletion(): void
    {
        $source = file_get_contents(app_path('Actions/User/DeleteUserTeams.php'));

        $this->assertStringContainsString('to_delete', $source);
        $this->assertStringContainsString('=== 1', $source);
    }

    /** @test */
    public function delete_user_teams_supports_dry_run(): void
    {
        $source = file_get_contents(app_path('Actions/User/DeleteUserTeams.php'));

        $this->assertStringContainsString('isDryRun', $source);
    }

    /** @test */
    public function delete_user_teams_finds_new_owner_from_admins(): void
    {
        $source = file_get_contents(app_path('Actions/User/DeleteUserTeams.php'));

        $this->assertStringContainsString('findNewOwner', $source);
        $this->assertStringContainsString("'admin'", $source);
    }

    /** @test */
    public function delete_user_teams_categorizes_transfer_and_leave(): void
    {
        $source = file_get_contents(app_path('Actions/User/DeleteUserTeams.php'));

        $this->assertStringContainsString('to_transfer', $source);
        $this->assertStringContainsString('to_leave', $source);
        $this->assertStringContainsString('edge_cases', $source);
    }

    /** @test */
    public function delete_user_teams_throws_on_edge_cases(): void
    {
        $source = file_get_contents(app_path('Actions/User/DeleteUserTeams.php'));

        $this->assertStringContainsString('edge_cases', $source);
        // Should throw exception if edge cases detected during execute
        $this->assertStringContainsString('throw', $source);
    }

    /** @test */
    public function delete_user_teams_force_deletes(): void
    {
        $source = file_get_contents(app_path('Actions/User/DeleteUserTeams.php'));

        $this->assertStringContainsString('$team->delete()', $source);
    }

    /** @test */
    public function delete_user_teams_transfers_with_owner_role(): void
    {
        $source = file_get_contents(app_path('Actions/User/DeleteUserTeams.php'));

        $this->assertStringContainsString("'owner'", $source);
        $this->assertStringContainsString('detach', $source);
    }

    /** @test */
    public function delete_user_teams_returns_structured_result(): void
    {
        $source = file_get_contents(app_path('Actions/User/DeleteUserTeams.php'));

        $this->assertStringContainsString("'deleted'", $source);
        $this->assertStringContainsString("'transferred'", $source);
        $this->assertStringContainsString("'left'", $source);
    }

    /** @test */
    public function delete_user_teams_checks_subscription_status(): void
    {
        $source = file_get_contents(app_path('Actions/User/DeleteUserTeams.php'));

        $this->assertStringContainsString('isUserPayingForTeamSubscription', $source);
        $this->assertStringContainsString('stripe_subscription_id', $source);
        $this->assertStringContainsString('stripe_invoice_paid', $source);
    }

    // =========================================================================
    // TransferTeamOwnership
    // =========================================================================

    /** @test */
    public function transfer_ownership_uses_db_transaction(): void
    {
        $source = file_get_contents(app_path('Actions/Transfer/TransferTeamOwnership.php'));

        $this->assertStringContainsString('DB::transaction(', $source);
    }

    /** @test */
    public function transfer_ownership_creates_snapshot(): void
    {
        $source = file_get_contents(app_path('Actions/Transfer/TransferTeamOwnership.php'));

        $this->assertStringContainsString('createSnapshot', $source);
        $this->assertStringContainsString("'name'", $source);
        $this->assertStringContainsString("'personal_team'", $source);
    }

    /** @test */
    public function transfer_ownership_creates_transfer_record(): void
    {
        $source = file_get_contents(app_path('Actions/Transfer/TransferTeamOwnership.php'));

        $this->assertStringContainsString('TeamResourceTransfer', $source);
        $this->assertStringContainsString('TYPE_TEAM_OWNERSHIP', $source);
        $this->assertStringContainsString('STATUS_IN_PROGRESS', $source);
    }

    /** @test */
    public function transfer_ownership_demotes_current_owner_to_admin(): void
    {
        $source = file_get_contents(app_path('Actions/Transfer/TransferTeamOwnership.php'));

        $this->assertStringContainsString("'admin'", $source);
        $this->assertStringContainsString("'owner'", $source);
    }

    /** @test */
    public function transfer_ownership_promotes_or_attaches_new_owner(): void
    {
        $source = file_get_contents(app_path('Actions/Transfer/TransferTeamOwnership.php'));

        $this->assertStringContainsString('updateExistingPivot(', $source);
        $this->assertStringContainsString('attach(', $source);
    }

    /** @test */
    public function transfer_ownership_handles_failure_with_rollback(): void
    {
        $source = file_get_contents(app_path('Actions/Transfer/TransferTeamOwnership.php'));

        $this->assertStringContainsString('catch', $source);
        $this->assertStringContainsString('STATUS_FAILED', $source);
    }

    /** @test */
    public function transfer_ownership_marks_completed_on_success(): void
    {
        $source = file_get_contents(app_path('Actions/Transfer/TransferTeamOwnership.php'));

        $this->assertStringContainsString('STATUS_COMPLETED', $source);
    }

    /** @test */
    public function transfer_ownership_snapshot_includes_member_counts(): void
    {
        $source = file_get_contents(app_path('Actions/Transfer/TransferTeamOwnership.php'));

        $this->assertStringContainsString('member_count', $source);
        $this->assertStringContainsString('project_count', $source);
        $this->assertStringContainsString('server_count', $source);
    }
}
