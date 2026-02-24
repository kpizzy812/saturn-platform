<?php

/**
 * Tests for the deployment grace period protection in status checks.
 *
 * When a re-deploy fails but the old container is still running, the status
 * should NOT be incorrectly set to "exited". Both GetContainersStatus (SSH path)
 * and PushServerUpdateJob (Sentinel path) must check for active or recently
 * completed deployments before marking applications as exited.
 */
it('GetContainersStatus has deployment grace period check', function () {
    $actionFile = file_get_contents(__DIR__.'/../../app/Actions/Docker/GetContainersStatus.php');

    // Must import ApplicationDeploymentQueue and ApplicationDeploymentStatus
    expect($actionFile)
        ->toContain('use App\Enums\ApplicationDeploymentStatus;')
        ->toContain('use App\Models\ApplicationDeploymentQueue;');

    // Must have the hasActiveOrRecentDeployment method
    expect($actionFile)
        ->toContain('private function hasActiveOrRecentDeployment(int $applicationId): bool');

    // Must check for in-progress and queued deployments
    expect($actionFile)
        ->toContain('ApplicationDeploymentStatus::IN_PROGRESS->value')
        ->toContain('ApplicationDeploymentStatus::QUEUED->value');

    // Must check for recently failed/finished deployments (grace period)
    expect($actionFile)
        ->toContain('ApplicationDeploymentStatus::FAILED->value')
        ->toContain('ApplicationDeploymentStatus::FINISHED->value')
        ->toContain('subSeconds(120)');

    // Must call the check before setting exited status
    expect($actionFile)
        ->toContain('$this->hasActiveOrRecentDeployment($applicationId)');
});

it('PushServerUpdateJob filters apps with recent deployments before marking exited', function () {
    $jobFile = file_get_contents(__DIR__.'/../../app/Jobs/PushServerUpdateJob.php');

    // Must query ApplicationDeploymentQueue for recent deployments
    expect($jobFile)
        ->toContain('ApplicationDeploymentQueue::whereIn(\'application_id\', $notFoundApplicationIds)');

    // Must check for active deployments (in_progress, queued)
    expect($jobFile)
        ->toContain('ApplicationDeploymentStatus::IN_PROGRESS->value')
        ->toContain('ApplicationDeploymentStatus::QUEUED->value');

    // Must check for recently failed/finished (grace period)
    expect($jobFile)
        ->toContain('ApplicationDeploymentStatus::FAILED->value')
        ->toContain('subSeconds(120)');

    // Must diff out apps with recent deployments
    expect($jobFile)
        ->toContain('$safeToMarkExited = $notFoundApplicationIds->diff($appsWithRecentDeployments)');

    // Must only update safe-to-mark apps
    expect($jobFile)
        ->toContain('Application::whereIn(\'id\', $safeToMarkExited)');
});

it('ApplicationDeploymentJob dispatches GetContainersStatus with delay on failure', function () {
    $jobFile = file_get_contents(__DIR__.'/../../app/Jobs/ApplicationDeploymentJob.php');

    // Must dispatch with delay in the failed() method
    expect($jobFile)
        ->toContain('GetContainersStatus::dispatch($this->server)->delay(now()->addSeconds(10))');

    // The delayed dispatch should appear AFTER the failDeployment() call
    $delayPos = strpos($jobFile, 'GetContainersStatus::dispatch($this->server)->delay(');
    $failedPos = strpos($jobFile, 'public function failed(Throwable');
    expect($delayPos)->toBeGreaterThan($failedPos, 'Delayed dispatch should be inside failed() method');
});

it('both status check paths have consistent grace period protection', function () {
    $actionFile = file_get_contents(__DIR__.'/../../app/Actions/Docker/GetContainersStatus.php');
    $jobFile = file_get_contents(__DIR__.'/../../app/Jobs/PushServerUpdateJob.php');

    // Both paths must check for in-progress deployments
    expect($actionFile)->toContain('ApplicationDeploymentStatus::IN_PROGRESS->value');
    expect($jobFile)->toContain('ApplicationDeploymentStatus::IN_PROGRESS->value');

    // Both paths must have the same 120-second grace period
    expect($actionFile)->toContain('subSeconds(120)');
    expect($jobFile)->toContain('subSeconds(120)');
});
