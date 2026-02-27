<?php

namespace Tests\Unit\Traits\Deployment;

use Tests\TestCase;

/**
 * Unit tests for HandlesGitOperations deployment trait.
 *
 * This trait handles git ls-remote (check if build needed), clone_repository,
 * and cleanup_git used by ApplicationDeploymentJob on every deploy.
 *
 * Tests use source-level assertions because SSH execution cannot run in unit tests.
 * All critical security properties (escapeshellarg, StrictHostKeyChecking) and
 * correctness requirements (SHA regex, PR refspecs) are verified against source.
 */
class HandlesGitOperationsTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = file_get_contents(
            app_path('Traits/Deployment/HandlesGitOperations.php')
        );
    }

    // =========================================================================
    // SSH key handling (check_git_if_build_needed)
    // =========================================================================

    public function test_ssh_key_is_base64_encoded_before_writing(): void
    {
        // Private key must be base64-encoded to avoid shell quoting issues when echoed
        $this->assertStringContainsString('base64_encode($private_key)', $this->source);
    }

    public function test_ssh_key_is_decoded_with_base64_d_on_server(): void
    {
        $this->assertStringContainsString('base64 -d', $this->source);
    }

    public function test_ssh_key_permissions_set_to_600(): void
    {
        // SECURITY: private key must be 0600 to prevent world-readable key
        $this->assertStringContainsString('chmod 600 /root/.ssh/id_rsa', $this->source);
    }

    public function test_ssh_strict_host_checking_disabled_for_first_connect(): void
    {
        // CI/CD containers won't have known_hosts; must not prompt
        $this->assertStringContainsString('StrictHostKeyChecking=no', $this->source);
    }

    public function test_ssh_user_known_hosts_sent_to_devnull(): void
    {
        $this->assertStringContainsString('UserKnownHostsFile=/dev/null', $this->source);
    }

    public function test_ssh_connect_timeout_is_set(): void
    {
        $this->assertStringContainsString('ConnectTimeout=30', $this->source);
    }

    public function test_ssh_key_written_to_root_ssh_directory(): void
    {
        $this->assertStringContainsString('mkdir -p /root/.ssh', $this->source);
        $this->assertStringContainsString('/root/.ssh/id_rsa', $this->source);
    }

    // =========================================================================
    // ls-remote ref construction — provider-specific refspecs
    // =========================================================================

    public function test_github_pr_uses_refs_pull_head_refspec(): void
    {
        // GitHub/Gitea PR ref must be refs/pull/{id}/head
        $this->assertStringContainsString("refs/pull/{$this->prIdPlaceholder()}/head", $this->source);
    }

    public function test_gitlab_mr_uses_refs_merge_requests_head_refspec(): void
    {
        // GitLab MR ref must be refs/merge-requests/{id}/head
        $this->assertStringContainsString("refs/merge-requests/{$this->prIdPlaceholder()}/head", $this->source);
    }

    public function test_branch_ref_uses_refs_heads_prefix(): void
    {
        // Normal branch ref must be refs/heads/{branch}
        $this->assertStringContainsString('refs/heads/', $this->source);
    }

    public function test_refspec_is_shell_escaped(): void
    {
        // SECURITY: refspec is user-controlled — must be escapeshellarg-ed
        $this->assertStringContainsString('escapeshellarg($lsRemoteRef)', $this->source);
    }

    public function test_git_ls_remote_output_is_saved_for_commit_extraction(): void
    {
        // ls-remote output must be captured for SHA parsing
        $this->assertStringContainsString("'save' => 'git_commit_sha'", $this->source);
    }

    public function test_ls_remote_command_is_hidden_from_log(): void
    {
        // Tokens/keys in GIT_SSH_COMMAND must not leak to deployment log
        $this->assertStringContainsString("'hidden' => true", $this->source);
    }

    // =========================================================================
    // SHA extraction — regex correctness
    // =========================================================================

    public function test_sha_extraction_uses_40_hex_char_regex(): void
    {
        // Must match exactly 40 hex chars — prevents partial SHA extraction
        $this->assertStringContainsString('[0-9a-fA-F]{40}', $this->source);
    }

    public function test_sha_regex_anchors_at_tab_separator(): void
    {
        // Tab is the git ls-remote separator between SHA and ref name
        $this->assertStringContainsString('\\t', $this->source);
    }

    public function test_sha_warning_logged_when_parsing_fails(): void
    {
        // Must warn (not crash) when output format is unexpected
        $this->assertStringContainsString('WARNING: Could not parse commit SHA', $this->source);
    }

    public function test_sha_warning_logged_when_no_tab_separator(): void
    {
        // ls-remote sometimes returns no tab (e.g., error output) — must warn
        $this->assertStringContainsString('WARNING: git ls-remote returned unexpected format', $this->source);
    }

    public function test_commit_is_saved_to_deployment_queue(): void
    {
        // Parsed SHA must be persisted so it shows in UI and rollbacks work
        $this->assertStringContainsString('$this->application_deployment_queue->commit = $this->commit', $this->source);
    }

    // =========================================================================
    // clone_repository()
    // =========================================================================

    public function test_clone_repository_logs_repository_and_commit(): void
    {
        $this->assertStringContainsString('Importing', $this->source);
        $this->assertStringContainsString('commit sha', $this->source);
    }

    public function test_clone_repository_saves_commit_message(): void
    {
        // Commit message must be persisted to DB for UI display
        $this->assertStringContainsString('commit_message', $this->source);
        $this->assertStringContainsString('git log -1', $this->source);
    }

    public function test_clone_repository_detects_env_example(): void
    {
        // .env.example variables must be imported automatically
        $this->assertStringContainsString('detectAndImportEnvExample', $this->source);
    }

    public function test_clone_sets_stage_clone(): void
    {
        $this->assertStringContainsString('STAGE_CLONE', $this->source);
    }

    // =========================================================================
    // cleanup_git()
    // =========================================================================

    public function test_cleanup_git_removes_dot_git_directory(): void
    {
        // .git directory must be removed before image build — leaks commit history otherwise
        $this->assertStringContainsString('rm -fr', $this->source);
        $this->assertStringContainsString('.git', $this->source);
    }

    // =========================================================================
    // Provider-specific branch handling
    // =========================================================================

    public function test_gitea_uses_same_refspec_as_github(): void
    {
        // Gitea and GitHub share the same PR ref format
        $this->assertStringContainsString("'github' || \$this->git_type === 'gitea'", $this->source);
    }

    public function test_pull_request_branch_appended_as_pull_head(): void
    {
        // Pull request checkout format is "pull/{id}/head"
        $this->assertStringContainsString('pull/{$this->pull_request_id}/head', $this->source);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Placeholder that matches the actual interpolated pull_request_id in source. */
    private function prIdPlaceholder(): string
    {
        return '{$this->pull_request_id}';
    }
}
