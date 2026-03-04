<?php

namespace App\Actions\Deployment;

use App\Enums\ApplicationDeploymentStatus;
use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Environment;
use Visus\Cuid2\Cuid2;

/**
 * Lightweight image promotion: take a successful deployment's Docker image
 * and deploy it to a target environment without a full rebuild.
 */
class PromoteImageAction
{
    /**
     * @return array{deployment_uuid: string, promoted_image: string}
     *
     * @throws \InvalidArgumentException
     */
    public function execute(
        ApplicationDeploymentQueue $sourceDeployment,
        Environment $targetEnvironment,
    ): array {
        // 1. Validate source deployment is finished and has a commit
        if ($sourceDeployment->status !== ApplicationDeploymentStatus::FINISHED->value) {
            throw new \InvalidArgumentException(
                'Source deployment must have finished successfully. Current status: '.$sourceDeployment->status
            );
        }

        if (empty($sourceDeployment->commit)) {
            throw new \InvalidArgumentException(
                'Source deployment has no commit hash — cannot determine image tag.'
            );
        }

        // 2. Resolve source application
        $sourceApp = $sourceDeployment->application;
        if (! $sourceApp) {
            throw new \InvalidArgumentException('Source deployment has no associated application.');
        }

        // 3. Verify target environment is in the same project
        $sourceEnvironment = $sourceApp->environment;
        if (! $sourceEnvironment || ! $sourceEnvironment->project_id) {
            throw new \InvalidArgumentException('Cannot determine source application project.');
        }

        if ($targetEnvironment->project_id !== $sourceEnvironment->project_id) {
            throw new \InvalidArgumentException(
                'Target environment must be in the same project as the source application.'
            );
        }

        if ($targetEnvironment->id === $sourceEnvironment->id) {
            throw new \InvalidArgumentException(
                'Target environment cannot be the same as the source environment.'
            );
        }

        // 4. Find same-named application in target environment
        $targetApp = Application::where('environment_id', $targetEnvironment->id)
            ->where('name', $sourceApp->name)
            ->first();

        if (! $targetApp) {
            throw new \InvalidArgumentException(
                "Application '{$sourceApp->name}' not found in target environment '{$targetEnvironment->name}'. Create it first, then promote."
            );
        }

        // 5. Build promoted image name (same logic as PromoteResourceAction)
        $commit = $sourceDeployment->commit;
        $promoted_from_image = $sourceApp->docker_registry_image_name
            ? "{$sourceApp->docker_registry_image_name}:{$commit}"
            : "{$sourceApp->uuid}:{$commit}";

        // 6. Determine if approval is required (production environments)
        $requiresApproval = $targetEnvironment->isProduction();

        // 7. Queue the deployment
        $deploymentUuid = new Cuid2;

        queue_application_deployment(
            application: $targetApp,
            deployment_uuid: (string) $deploymentUuid,
            commit: $commit,
            no_questions_asked: true,
            is_api: true,
            requires_approval: $requiresApproval,
            is_promotion: true,
            promoted_from_image: $promoted_from_image,
        );

        return [
            'deployment_uuid' => (string) $deploymentUuid,
            'promoted_image' => $promoted_from_image,
        ];
    }
}
