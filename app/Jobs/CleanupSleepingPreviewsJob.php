<?php

namespace App\Jobs;

use App\Models\ApplicationPreview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupSleepingPreviewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(): void
    {
        Log::info('Starting preview environment cleanup job');

        // Handle auto-sleep for inactive previews
        $this->handleAutoSleep();

        // Handle auto-delete for old previews
        $this->handleAutoDelete();
    }

    protected function handleAutoSleep(): void
    {
        $previewsToSleep = ApplicationPreview::query()
            ->where('auto_sleep_enabled', true)
            ->where('is_sleeping', false)
            ->whereNotNull('auto_sleep_after_minutes')
            ->whereNotNull('last_activity_at')
            ->whereRaw('last_activity_at < NOW() - INTERVAL auto_sleep_after_minutes MINUTE')
            ->get();

        foreach ($previewsToSleep as $preview) {
            try {
                $this->sleepPreview($preview);
            } catch (\Exception $e) {
                Log::error("Failed to sleep preview {$preview->id}: {$e->getMessage()}");
            }
        }

        Log::info("Slept {$previewsToSleep->count()} inactive previews");
    }

    protected function handleAutoDelete(): void
    {
        $previewsToDelete = ApplicationPreview::query()
            ->where('auto_delete_enabled', true)
            ->whereNotNull('auto_delete_after_days')
            ->whereRaw('created_at < NOW() - INTERVAL auto_delete_after_days DAY')
            ->get();

        foreach ($previewsToDelete as $preview) {
            try {
                $this->deletePreview($preview);
            } catch (\Exception $e) {
                Log::error("Failed to delete preview {$preview->id}: {$e->getMessage()}");
            }
        }

        Log::info("Deleted {$previewsToDelete->count()} old previews");
    }

    protected function sleepPreview(ApplicationPreview $preview): void
    {
        $application = $preview->application;
        $server = $application->destination?->server;

        if (! $server || ! $server->isFunctional()) {
            return;
        }

        $containerName = generateApplicationContainerName($application, $preview->pull_request_id);

        // Stop the container (but don't remove it)
        instant_remote_process(
            ["docker stop {$containerName} 2>/dev/null || true"],
            $server
        );

        $preview->update([
            'is_sleeping' => true,
            'slept_at' => now(),
        ]);

        Log::info("Slept preview container: {$containerName}");
    }

    protected function deletePreview(ApplicationPreview $preview): void
    {
        // Use existing cleanup action with correct AsAction pattern
        \App\Actions\Application\CleanupPreviewDeployment::run(
            $preview->application,
            $preview->pull_request_id,
            $preview
        );

        Log::info("Deleted old preview: {$preview->id} (PR #{$preview->pull_request_id})");
    }
}
