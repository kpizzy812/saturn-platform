<?php

namespace Tests\Unit\Traits\Deployment;

use Tests\TestCase;

/**
 * Unit tests for HandlesDeploymentStatus deployment trait.
 *
 * This trait is the single source of truth for deployment status transitions.
 * It enforces terminal state guards, triggers side effects (notifications, events,
 * additional deployments), and manages rollback event tracking.
 *
 * Tests use source-level assertions because the trait requires Eloquent models
 * and SSH execution context that cannot run in unit tests.
 */
class HandlesDeploymentStatusTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = file_get_contents(
            app_path('Traits/Deployment/HandlesDeploymentStatus.php')
        );
    }

    // =========================================================================
    // isInTerminalState() — terminal state guard
    // =========================================================================

    public function test_terminal_state_checks_finished_status(): void
    {
        $this->assertStringContainsString('ApplicationDeploymentStatus::FINISHED->value', $this->source);
    }

    public function test_terminal_state_checks_failed_status(): void
    {
        $this->assertStringContainsString('ApplicationDeploymentStatus::FAILED->value', $this->source);
    }

    public function test_terminal_state_cancelled_throws_deployment_exception(): void
    {
        // Cancelled status is special: it stops execution via exception rather than silent return
        $this->assertStringContainsString('CANCELLED_BY_USER', $this->source);
        $this->assertStringContainsString('throw new DeploymentException(\'Deployment cancelled by user\'', $this->source);
    }

    public function test_terminal_state_cancelled_logs_message(): void
    {
        $this->assertStringContainsString('Deployment cancelled by user, stopping execution.', $this->source);
    }

    public function test_terminal_state_refreshes_model_before_checking(): void
    {
        // Must refresh from DB to detect concurrent status changes
        $this->assertStringContainsString('$this->application_deployment_queue->refresh()', $this->source);
    }

    // =========================================================================
    // transitionToStatus() — guarded transition
    // =========================================================================

    public function test_transition_guards_with_terminal_state_check(): void
    {
        $this->assertStringContainsString('$this->isInTerminalState()', $this->source);
    }

    public function test_transition_updates_deployment_status(): void
    {
        $this->assertStringContainsString('$this->updateDeploymentStatus($status)', $this->source);
    }

    public function test_transition_handles_status_side_effects(): void
    {
        $this->assertStringContainsString('$this->handleStatusTransition($status)', $this->source);
    }

    public function test_transition_queues_next_deployment(): void
    {
        // After status change, next queued deployment must be triggered
        $this->assertStringContainsString('queue_next_deployment($this->application)', $this->source);
    }

    // =========================================================================
    // handleStatusTransition() — match expression dispatch
    // =========================================================================

    public function test_finished_status_triggers_success_handler(): void
    {
        $this->assertStringContainsString('ApplicationDeploymentStatus::FINISHED => $this->handleSuccessfulDeployment()', $this->source);
    }

    public function test_failed_status_triggers_failure_handler(): void
    {
        $this->assertStringContainsString('ApplicationDeploymentStatus::FAILED => $this->handleFailedDeployment()', $this->source);
    }

    // =========================================================================
    // handleSuccessfulDeployment() — success side effects
    // =========================================================================

    public function test_success_resets_restart_count(): void
    {
        // Successful deploy clears auto-restart state
        $this->assertStringContainsString("'restart_count' => 0", $this->source);
    }

    public function test_success_records_last_successful_deployment_id(): void
    {
        // Needed for rollback target selection
        $this->assertStringContainsString('last_successful_deployment_id', $this->source);
    }

    public function test_success_fires_application_configuration_changed_event(): void
    {
        // Frontend uses this event to update application state
        $this->assertStringContainsString('ApplicationConfigurationChanged', $this->source);
    }

    public function test_success_triggers_additional_destination_deployments(): void
    {
        // Multi-server deploys must propagate to additional destinations
        $this->assertStringContainsString('$this->deploy_to_additional_destinations()', $this->source);
    }

    public function test_success_only_deploys_to_additional_when_not_single_server(): void
    {
        $this->assertStringContainsString('$this->only_this_server', $this->source);
    }

    public function test_success_sends_deployment_success_notification(): void
    {
        $this->assertStringContainsString('DeploymentSuccess', $this->source);
    }

    public function test_success_starts_health_monitor_when_auto_rollback_enabled(): void
    {
        $this->assertStringContainsString('auto_rollback_enabled', $this->source);
        $this->assertStringContainsString('MonitorDeploymentHealthJob', $this->source);
    }

    public function test_success_skips_health_monitor_for_pr_deployments(): void
    {
        // PR deployments are temporary — no auto-rollback monitoring needed
        $this->assertStringContainsString('pull_request_id ?? 0) === 0', $this->source);
    }

    public function test_success_skips_health_monitor_for_rollback_deployments(): void
    {
        // A rollback itself should not trigger another rollback monitor
        $this->assertStringContainsString('! $isRollback', $this->source);
    }

    public function test_success_syncs_master_proxy_route_for_remote_servers(): void
    {
        // Remote server apps need their route synced to the master proxy
        $this->assertStringContainsString('MasterProxyConfigService', $this->source);
        $this->assertStringContainsString('syncRemoteRoute', $this->source);
    }

    public function test_success_proxy_sync_failure_only_warns(): void
    {
        // Proxy sync failure must not fail the deployment
        $this->assertStringContainsString("Log::warning('Failed to sync master proxy route'", $this->source);
    }

    // =========================================================================
    // handleFailedDeployment() — failure side effects
    // =========================================================================

    public function test_failure_sends_deployment_failed_notification(): void
    {
        $this->assertStringContainsString('DeploymentFailed', $this->source);
    }

    // =========================================================================
    // Rollback event tracking
    // =========================================================================

    public function test_rollback_event_marked_success_on_successful_rollback(): void
    {
        $this->assertStringContainsString('?->markSuccess()', $this->source);
    }

    public function test_rollback_event_marked_failed_on_failed_rollback(): void
    {
        $this->assertStringContainsString("?->markFailed('Rollback deployment failed')", $this->source);
    }

    public function test_rollback_tracking_queries_by_rollback_deployment_id(): void
    {
        $this->assertStringContainsString('rollback_deployment_id', $this->source);
    }

    // =========================================================================
    // sendDeploymentNotification() — null-safe chain
    // =========================================================================

    public function test_notification_uses_null_safe_chain_to_team(): void
    {
        // environment → project → team may be null during cleanup — must not crash
        $this->assertStringContainsString('$this->application->environment?->project?->team?->notify(', $this->source);
    }

    // =========================================================================
    // completeDeployment() / failDeployment() — public API
    // =========================================================================

    public function test_complete_deployment_transitions_to_finished(): void
    {
        $this->assertStringContainsString('$this->transitionToStatus(ApplicationDeploymentStatus::FINISHED)', $this->source);
    }

    public function test_fail_deployment_transitions_to_failed(): void
    {
        $this->assertStringContainsString('$this->transitionToStatus(ApplicationDeploymentStatus::FAILED)', $this->source);
    }

    public function test_fail_deployment_is_protected(): void
    {
        // failDeployment() is called from child traits — must be protected, not private
        $this->assertStringContainsString('protected function failDeployment()', $this->source);
    }

    // =========================================================================
    // Import checks
    // =========================================================================

    public function test_trait_imports_deployment_exception(): void
    {
        $this->assertStringContainsString('use App\\Exceptions\\DeploymentException;', $this->source);
    }

    public function test_trait_imports_application_deployment_status_enum(): void
    {
        $this->assertStringContainsString('use App\\Enums\\ApplicationDeploymentStatus;', $this->source);
    }
}
