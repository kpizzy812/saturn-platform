<?php

namespace App\Traits\Deployment;

use App\Models\ApplicationDeploymentQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Trait for handling local file upload deployments.
 *
 * Replaces git clone with downloading and extracting a user-uploaded archive.
 * The archive is stored on the Saturn server and served via a temporary signed URL.
 * The deployment server downloads the archive over HTTP and extracts it to workdir.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $deployment_uuid
 * - $workdir, $basedir, $saved_outputs
 *
 * Required methods from parent class:
 * - execute_remote_command(), create_workdir()
 */
trait HandlesLocalUpload
{
    /**
     * Check whether this deployment uses a locally uploaded archive.
     */
    private function is_local_upload(): bool
    {
        return ! empty($this->application_deployment_queue->local_source_path);
    }

    /**
     * Extract the uploaded archive to workdir on the deployment server.
     * Replaces clone_repository() for local upload deployments.
     */
    private function extract_local_upload(): void
    {
        $this->application_deployment_queue->setStage(ApplicationDeploymentQueue::STAGE_CLONE);

        $sourcePath = $this->application_deployment_queue->local_source_path;

        if (! Storage::disk('local')->exists($sourcePath)) {
            throw new \RuntimeException("Local upload archive not found: {$sourcePath}");
        }

        // Generate a signed temporary URL (valid for 30 minutes) so the remote server can download the archive
        $signedUrl = URL::temporarySignedRoute(
            'local-upload.download',
            now()->addMinutes(30),
            ['path' => base64_encode($sourcePath)]
        );

        $this->application_deployment_queue->addLogEntry("\n----------------------------------------");
        $this->application_deployment_queue->addLogEntry('Extracting locally uploaded archive to build directory.');

        $this->create_workdir();

        // Download archive from Saturn and extract directly into workdir
        $this->execute_remote_command(
            [
                executeInDocker(
                    $this->deployment_uuid,
                    'curl -fsSL '.escapeshellarg($signedUrl)." | tar xzf - -C {$this->workdir}"
                ),
                'hidden' => false,
            ]
        );

        $this->application_deployment_queue->addLogEntry('Archive extracted successfully.');

        // Set a placeholder commit for display purposes
        $this->application_deployment_queue->commit = 'local';
        $this->application_deployment_queue->commit_message = 'Local upload';
        $this->application_deployment_queue->save();
    }

    /**
     * Clean up the uploaded archive after deployment completes (or fails).
     */
    private function cleanup_local_upload(): void
    {
        $sourcePath = $this->application_deployment_queue->local_source_path;
        if ($sourcePath && Storage::disk('local')->exists($sourcePath)) {
            Storage::disk('local')->delete($sourcePath);
        }
    }
}
