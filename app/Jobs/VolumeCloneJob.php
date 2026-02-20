<?php

namespace App\Jobs;

use App\Models\LocalPersistentVolume;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VolumeCloneJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $cloneDir = '/data/saturn/clone';

    public function __construct(
        protected string $sourceVolume,
        protected string $targetVolume,
        protected Server $sourceServer,
        protected ?Server $targetServer,
        protected LocalPersistentVolume $persistentVolume
    ) {
        $this->onQueue('high');
    }

    public function handle()
    {
        try {
            if (! $this->targetServer || $this->targetServer->id === $this->sourceServer->id) {
                $this->cloneLocalVolume();
            } else {
                $this->cloneRemoteVolume();
            }
        } catch (\Exception $e) {
            Log::error("Failed to copy volume data for {$this->sourceVolume}: ".$e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('VolumeCloneJob permanently failed', [
            'source_volume' => $this->sourceVolume,
            'target_volume' => $this->targetVolume,
            'source_server_id' => $this->sourceServer->id,
            'target_server_id' => $this->targetServer?->id,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function cloneLocalVolume()
    {
        // Security: Escape volume names to prevent command injection
        $escapedSource = escapeshellarg($this->sourceVolume);
        $escapedTarget = escapeshellarg($this->targetVolume);

        instant_remote_process([
            "docker volume create {$escapedTarget}",
            "docker run --rm -v {$escapedSource}:/source -v {$escapedTarget}:/target alpine sh -c 'cp -a /source/. /target/ && chown -R 1000:1000 /target'",
        ], $this->sourceServer);
    }

    protected function cloneRemoteVolume()
    {
        // Security: Escape volume names to prevent command injection
        $escapedSource = escapeshellarg($this->sourceVolume);
        $escapedTarget = escapeshellarg($this->targetVolume);

        // Sanitize volume names for directory paths (remove any path separators)
        $sanitizedSourceName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->sourceVolume);
        $sanitizedTargetName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->targetVolume);

        $sourceCloneDir = "{$this->cloneDir}/{$sanitizedSourceName}";
        $targetCloneDir = "{$this->cloneDir}/{$sanitizedTargetName}";

        $escapedSourceCloneDir = escapeshellarg($sourceCloneDir);
        $escapedTargetCloneDir = escapeshellarg($targetCloneDir);

        try {
            instant_remote_process([
                "mkdir -p {$escapedSourceCloneDir}",
                "chmod 777 {$escapedSourceCloneDir}",
                "docker run --rm -v {$escapedSource}:/source -v {$escapedSourceCloneDir}:/clone alpine sh -c 'cd /source && tar czf /clone/volume-data.tar.gz .'",
            ], $this->sourceServer);

            instant_remote_process([
                "mkdir -p {$escapedTargetCloneDir}",
                "chmod 777 {$escapedTargetCloneDir}",
            ], $this->targetServer);

            instant_scp(
                "{$sourceCloneDir}/volume-data.tar.gz",
                "{$targetCloneDir}/volume-data.tar.gz",
                $this->sourceServer,
                $this->targetServer
            );

            instant_remote_process([
                "docker volume create {$escapedTarget}",
                "docker run --rm -v {$escapedTarget}:/target -v {$escapedTargetCloneDir}:/clone alpine sh -c 'cd /target && tar xzf /clone/volume-data.tar.gz && chown -R 1000:1000 /target'",
            ], $this->targetServer);

        } catch (\Exception $e) {
            Log::error("Failed to clone volume {$this->sourceVolume} to {$this->targetVolume}: ".$e->getMessage());
            throw $e;
        } finally {
            try {
                instant_remote_process([
                    "rm -rf {$escapedSourceCloneDir}",
                ], $this->sourceServer, false);
            } catch (\Exception $e) {
                Log::warning('Failed to clean up source server clone directory: '.$e->getMessage());
            }

            try {
                if ($this->targetServer) {
                    instant_remote_process([
                        "rm -rf {$escapedTargetCloneDir}",
                    ], $this->targetServer, false);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to clean up target server clone directory: '.$e->getMessage());
            }
        }
    }
}
