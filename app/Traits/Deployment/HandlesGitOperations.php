<?php

namespace App\Traits\Deployment;

use App\Models\ApplicationDeploymentQueue;

/**
 * Trait for handling Git operations during deployment.
 *
 * Required properties from parent class:
 * - $source, $customRepository, $application, $branch, $pull_request_id
 * - $git_type, $customPort, $fullRepoUrl, $deployment_uuid
 * - $rollback, $commit, $application_deployment_queue, $saved_outputs
 * - $basedir, $workdir
 *
 * Required methods from parent class:
 * - execute_remote_command(), set_saturn_variables()
 * - restart_builder_container_with_actual_commit(), create_workdir()
 */
trait HandlesGitOperations
{
    /**
     * Check if build is needed by querying git remote.
     */
    private function check_git_if_build_needed(): void
    {
        if (is_object($this->source) && $this->source->getMorphClass() === \App\Models\GithubApp::class && $this->source->is_public === false) {
            $repository = githubApi($this->source, "repos/{$this->customRepository}");
            $data = data_get($repository, 'data');
            $repository_project_id = data_get($data, 'id');
            if (isset($repository_project_id)) {
                if (blank($this->application->repository_project_id) || $this->application->repository_project_id !== $repository_project_id) {
                    $this->application->repository_project_id = $repository_project_id;
                    $this->application->save();
                }
            }
        }
        $this->generate_git_import_commands();
        $local_branch = $this->branch;
        if ($this->pull_request_id !== 0) {
            $local_branch = "pull/{$this->pull_request_id}/head";
        }
        // Build an exact refspec for ls-remote so we don't match similarly named branches (e.g., changeset-release/main)
        if ($this->pull_request_id === 0) {
            $lsRemoteRef = "refs/heads/{$local_branch}";
        } else {
            if ($this->git_type === 'github' || $this->git_type === 'gitea') {
                $lsRemoteRef = "refs/pull/{$this->pull_request_id}/head";
            } elseif ($this->git_type === 'gitlab') {
                $lsRemoteRef = "refs/merge-requests/{$this->pull_request_id}/head";
            } else {
                // Fallback to the original value if provider-specific ref is unknown
                $lsRemoteRef = $local_branch;
            }
        }
        $private_key = data_get($this->application, 'private_key.private_key');
        if ($private_key) {
            $private_key = base64_encode($private_key);
            $this->execute_remote_command(
                [
                    executeInDocker($this->deployment_uuid, 'mkdir -p /root/.ssh'),
                ],
                [
                    executeInDocker($this->deployment_uuid, "echo '{$private_key}' | base64 -d | tee /root/.ssh/id_rsa > /dev/null"),
                ],
                [
                    executeInDocker($this->deployment_uuid, 'chmod 600 /root/.ssh/id_rsa'),
                ],
                [
                    executeInDocker($this->deployment_uuid, "GIT_SSH_COMMAND=\"ssh -o ConnectTimeout=30 -p {$this->customPort} -o Port={$this->customPort} -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null\" git ls-remote {$this->fullRepoUrl} {$lsRemoteRef}"),
                    'hidden' => true,
                    'save' => 'git_commit_sha',
                ]
            );
        } else {
            $this->execute_remote_command(
                [
                    executeInDocker($this->deployment_uuid, "GIT_SSH_COMMAND=\"ssh -o ConnectTimeout=30 -p {$this->customPort} -o Port={$this->customPort} -o LogLevel=ERROR -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null\" git ls-remote {$this->fullRepoUrl} {$lsRemoteRef}"),
                    'hidden' => true,
                    'save' => 'git_commit_sha',
                ],
            );
        }
        if ($this->saved_outputs->get('git_commit_sha') && ! $this->rollback) {
            // Extract commit SHA from git ls-remote output, handling multi-line output (e.g., redirect warnings)
            // Expected format: "commit_sha\trefs/heads/branch" possibly preceded by warning lines
            // Note: Git warnings can be on the same line as the result (no newline)
            $lsRemoteOutput = $this->saved_outputs->get('git_commit_sha');

            // Find the part containing a tab (the actual ls-remote result)
            // Handle cases where warning is on the same line as the result
            if ($lsRemoteOutput->contains("\t")) {
                // Get everything from the last occurrence of a valid commit SHA pattern before the tab
                // A valid commit SHA is 40 hex characters
                $output = $lsRemoteOutput->value();

                // Extract the line with the tab (actual ls-remote result)
                preg_match('/\b([0-9a-fA-F]{40})(?=\s*\t)/', $output, $matches);

                if (isset($matches[1])) {
                    $this->commit = $matches[1];
                    $this->application_deployment_queue->commit = $this->commit;
                    $this->application_deployment_queue->save();
                }
            }
        }
        $this->set_saturn_variables();

        // Restart helper container with actual SOURCE_COMMIT value
        if ($this->application->settings->use_build_secrets && $this->commit !== 'HEAD') {
            $this->application_deployment_queue->addLogEntry('Restarting helper container with actual SOURCE_COMMIT value.');
            $this->restart_builder_container_with_actual_commit();
        }
    }

    /**
     * Clone the git repository into the deployment container.
     */
    private function clone_repository(): void
    {
        $this->application_deployment_queue->setStage(ApplicationDeploymentQueue::STAGE_CLONE);
        $importCommands = $this->generate_git_import_commands();
        $this->application_deployment_queue->addLogEntry("\n----------------------------------------");
        $this->application_deployment_queue->addLogEntry("Importing {$this->customRepository}:{$this->application->git_branch} (commit sha {$this->commit}) to {$this->basedir}.");
        if ($this->pull_request_id !== 0) {
            $this->application_deployment_queue->addLogEntry("Checking out tag pull/{$this->pull_request_id}/head.");
        }
        $this->execute_remote_command(
            [
                $importCommands,
                'hidden' => true,
            ]
        );
        $this->create_workdir();
        $this->execute_remote_command(
            [
                executeInDocker($this->deployment_uuid, "cd {$this->workdir} && git log -1 {$this->commit} --pretty=%B"),
                'hidden' => true,
                'save' => 'commit_message',
            ]
        );
        if ($this->saved_outputs->get('commit_message')) {
            $commit_message = str($this->saved_outputs->get('commit_message'));
            $this->application_deployment_queue->commit_message = $commit_message->value();
            \App\Models\ApplicationDeploymentQueue::whereCommit($this->commit)->whereApplicationId($this->application->id)->update(
                ['commit_message' => $commit_message->value()]
            );
        }

        // Detect and import environment variables from .env.example
        $this->detectAndImportEnvExample();
    }

    /**
     * Generate git import commands for cloning.
     *
     * @return string The git import commands
     */
    private function generate_git_import_commands(): string
    {
        ['commands' => $commands, 'branch' => $this->branch, 'fullRepoUrl' => $this->fullRepoUrl] = $this->application->generateGitImportCommands(
            deployment_uuid: $this->deployment_uuid,
            pull_request_id: $this->pull_request_id,
            git_type: $this->git_type,
            commit: $this->commit
        );

        return $commands;
    }

    /**
     * Remove .git directory from the cloned repository.
     */
    private function cleanup_git(): void
    {
        $this->execute_remote_command(
            [executeInDocker($this->deployment_uuid, "rm -fr {$this->basedir}/.git")],
        );
    }
}
