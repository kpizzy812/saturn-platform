<?php

namespace App\Actions\Server;

use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;

class CleanupDocker
{
    use AsAction;

    public string $jobQueue = 'high';

    public function handle(Server $server, bool $deleteUnusedVolumes = false, bool $deleteUnusedNetworks = false)
    {
        $realtimeImage = config('constants.saturn.realtime_image');
        $realtimeImageVersion = config('constants.saturn.realtime_version');
        $realtimeImageWithVersion = "$realtimeImage:$realtimeImageVersion";
        $realtimeImageWithoutPrefix = 'coollabsio/coolify-realtime';
        $realtimeImageWithoutPrefixVersion = "coollabsio/coolify-realtime:$realtimeImageVersion";

        $helperImageVersion = getHelperVersion();
        $helperImage = config('constants.saturn.helper_image');
        $helperImageWithVersion = "$helperImage:$helperImageVersion";
        $helperImageWithoutPrefix = 'coollabsio/coolify-helper';
        $helperImageWithoutPrefixVersion = "coollabsio/coolify-helper:$helperImageVersion";

        $cleanupLog = [];

        // Get all application image repositories to exclude from prune
        $applications = $server->applications();
        $applicationImageRepos = collect($applications)->map(function ($app) {
            return $app->docker_registry_image_name ?? $app->uuid;
        })->unique()->values();

        // Clean up old application images while preserving N most recent for rollback
        $applicationCleanupLog = $this->cleanupApplicationImages($server, $applications);
        $cleanupLog = array_merge($cleanupLog, $applicationCleanupLog);

        // Build image prune command that excludes application images and current Saturn Platform infrastructure images
        // This ensures we clean up non-Saturn Platform images while preserving rollback images and current helper/realtime images
        // Note: Only the current version is protected; old versions will be cleaned up by explicit commands below
        // We pass the version strings so all registry variants are protected (ghcr.io, docker.io, no prefix)
        $imagePruneCmd = $this->buildImagePruneCommand(
            $applicationImageRepos,
            $helperImageVersion,
            $realtimeImageVersion
        );

        $commands = [
            'docker container prune -f --filter "label=saturn.managed=true" --filter "label!=saturn.proxy=true"',
            $imagePruneCmd,
            'docker builder prune -af',
            // Remove -f flag: do not force-remove images that may be in use by running containers.
            // If the image is in use, docker rmi will fail gracefully; '|| true' suppresses the error.
            "docker images --filter before=$helperImageWithVersion --filter reference=$helperImage | grep $helperImage | awk '{print \$3}' | xargs -r -I {} sh -c 'docker rmi {} || true'",
            "docker images --filter before=$realtimeImageWithVersion --filter reference=$realtimeImage | grep $realtimeImage | awk '{print \$3}' | xargs -r -I {} sh -c 'docker rmi {} || true'",
            "docker images --filter before=$helperImageWithoutPrefixVersion --filter reference=$helperImageWithoutPrefix | grep $helperImageWithoutPrefix | awk '{print \$3}' | xargs -r -I {} sh -c 'docker rmi {} || true'",
            "docker images --filter before=$realtimeImageWithoutPrefixVersion --filter reference=$realtimeImageWithoutPrefix | grep $realtimeImageWithoutPrefix | awk '{print \$3}' | xargs -r -I {} sh -c 'docker rmi {} || true'",
        ];

        if ($deleteUnusedVolumes) {
            $commands[] = 'docker volume prune -af';
        }

        if ($deleteUnusedNetworks) {
            $commands[] = 'docker network prune -f';
        }

        // Execute all cleanup commands in a single SSH call
        $batchedCommand = implode(' && ', $commands);
        $commandOutput = instant_remote_process([$batchedCommand], $server, false);
        if ($commandOutput !== null) {
            $cleanupLog[] = [
                'command' => 'batch docker cleanup ('.count($commands).' commands)',
                'output' => $commandOutput,
            ];
        }

        return $cleanupLog;
    }

    /**
     * Build a docker image prune command that excludes application image repositories.
     *
     * Since docker image prune doesn't support excluding by repository name directly,
     * we use a shell script approach to delete unused images while preserving application images.
     */
    private function buildImagePruneCommand(
        $applicationImageRepos,
        string $helperImageVersion,
        string $realtimeImageVersion
    ): string {
        // Step 1: Always prune dangling images (untagged)
        $commands = ['docker image prune -f'];

        // Build grep pattern to exclude application image repositories (matches repo:tag and repo_service:tag)
        $appExcludePatterns = $applicationImageRepos->map(function ($repo) {
            // Escape special characters for grep extended regex (ERE)
            // ERE special chars: . \ + * ? [ ^ ] $ ( ) { } |
            return preg_replace('/([.\\\\+*?\[\]^$(){}|])/', '\\\\$1', $repo);
        })->implode('|');

        // Build grep pattern to exclude Saturn Platform infrastructure images (current version only)
        // This pattern matches the image name regardless of registry prefix:
        // - ghcr.io/coollabsio/coolify-helper:1.0.12
        // - docker.io/coollabsio/coolify-helper:1.0.12
        // - coollabsio/coolify-helper:1.0.12
        // Pattern: (^|/)coollabsio/coolify-(helper|realtime):VERSION$
        $escapedHelperVersion = preg_replace('/([.\\\\+*?\[\]^$(){}|])/', '\\\\$1', $helperImageVersion);
        $escapedRealtimeVersion = preg_replace('/([.\\\\+*?\[\]^$(){}|])/', '\\\\$1', $realtimeImageVersion);
        $infraExcludePattern = "(^|/)coollabsio/coolify-helper:{$escapedHelperVersion}$|(^|/)coollabsio/coolify-realtime:{$escapedRealtimeVersion}$";

        // Delete unused images that:
        // - Are not application images (don't match app repos)
        // - Are not current Saturn Platform infrastructure images (any registry)
        // - Don't have saturn.managed=true label
        // Images in use by containers will fail silently with docker rmi
        // Pattern matches both uuid:tag and uuid_servicename:tag (Docker Compose with build)
        $grepCommands = "grep -v '<none>'";

        // Add application repo exclusion if there are applications
        if ($applicationImageRepos->isNotEmpty()) {
            $grepCommands .= " | grep -v -E '^({$appExcludePatterns})[_:].+'";
        }

        // Add infrastructure image exclusion (matches any registry prefix)
        $grepCommands .= " | grep -v -E '{$infraExcludePattern}'";

        $commands[] = "docker images --format '{{.Repository}}:{{.Tag}}' | ".
            $grepCommands.' | '.
            "xargs -r -I {} sh -c 'docker inspect --format \"{{{{index .Config.Labels \\\"saturn.managed\\\"}}}}\" \"{}\" 2>/dev/null | grep -q true || docker rmi \"{}\" 2>/dev/null' || true";

        return implode(' && ', $commands);
    }

    private function cleanupApplicationImages(Server $server, $applications = null): array
    {
        $cleanupLog = [];

        if ($applications === null) {
            $applications = $server->applications();
        }

        if (empty($applications) || collect($applications)->isEmpty()) {
            return $cleanupLog;
        }

        $disableRetention = $server->settings->disable_application_image_retention ?? false;

        // Batch: collect all app UUIDs and image repos, then run a single SSH command
        // to get current tags and image lists for all applications at once
        $appData = collect($applications)->map(fn ($app) => [
            'uuid' => $app->uuid,
            'repo' => $app->docker_registry_image_name ?? $app->uuid,
            'keep' => $disableRetention ? 0 : ($app->settings->docker_images_to_keep ?? 2),
        ]);

        // Build a single batched command to get current tags for all apps
        $inspectParts = $appData->map(fn ($a) => "echo \"APP_TAG:{$a['uuid']}:\$(docker inspect --format='{{.Config.Image}}' {$a['uuid']} 2>/dev/null | grep -oP '(?<=:)[^:]+$' || true)\"")->implode(' && ');

        // Build a single batched command to list images for all apps
        $listParts = $appData->map(fn ($a) => "echo \"APP_IMAGES:{$a['uuid']}:\" && docker images --format '{{.Repository}}:{{.Tag}}#{{.CreatedAt}}' --filter reference='{$a['repo']}*' 2>/dev/null || true")->implode(' && ');

        // Execute both in a single SSH call
        $batchCommand = $inspectParts.' && echo "---SEPARATOR---" && '.$listParts;
        $batchOutput = instant_remote_process([$batchCommand], $server, false);

        if (empty($batchOutput)) {
            return $cleanupLog;
        }

        // Parse batched output
        $sections = explode('---SEPARATOR---', $batchOutput);
        $tagSection = trim($sections[0] ?? '');
        $imageSection = trim($sections[1] ?? '');

        // Parse current tags per app
        $currentTags = [];
        foreach (explode("\n", $tagSection) as $line) {
            if (preg_match('/^APP_TAG:([^:]+):(.*)$/', trim($line), $m)) {
                $currentTags[$m[1]] = trim($m[2]);
            }
        }

        // Parse images per app
        $appImages = [];
        $currentAppUuid = null;
        foreach (explode("\n", $imageSection) as $line) {
            $line = trim($line);
            if (preg_match('/^APP_IMAGES:([^:]+):$/', $line, $m)) {
                $currentAppUuid = $m[1];
                $appImages[$currentAppUuid] = [];
            } elseif ($currentAppUuid && $line !== '') {
                $appImages[$currentAppUuid][] = $line;
            }
        }

        // Collect all images to delete in a single batch
        $imagesToRemove = [];

        foreach ($appData as $app) {
            $uuid = $app['uuid'];
            $imagesToKeep = $app['keep'];
            $currentTag = $currentTags[$uuid] ?? '';
            $rawImages = $appImages[$uuid] ?? [];

            if (empty($rawImages)) {
                continue;
            }

            $images = collect($rawImages)
                ->filter()
                ->map(function ($line) {
                    $parts = explode('#', $line);
                    $imageRef = $parts[0];
                    $tagParts = explode(':', $imageRef);

                    return [
                        'repository' => $tagParts[0],
                        'tag' => $tagParts[1] ?? '',
                        'created_at' => $parts[1] ?? '',
                        'image_ref' => $imageRef,
                    ];
                })
                ->filter(fn ($image) => ! empty($image['tag']));

            // PR images — always delete
            $imagesToRemove = array_merge(
                $imagesToRemove,
                $images->filter(fn ($image) => str_starts_with($image['tag'], 'pr-'))
                    ->pluck('image_ref')
                    ->all()
            );

            // Regular images — keep N most recent, delete the rest
            $regularImages = $images->filter(fn ($image) => ! str_starts_with($image['tag'], 'pr-') && ! str_ends_with($image['tag'], '-build'));

            $sortedRegularImages = $regularImages
                ->filter(fn ($image) => $image['tag'] !== $currentTag)
                ->sortByDesc('created_at')
                ->values();

            $imagesToRemove = array_merge(
                $imagesToRemove,
                $sortedRegularImages->skip($imagesToKeep)->pluck('image_ref')->all()
            );
        }

        // Execute all deletions in a single SSH call
        if (! empty($imagesToRemove)) {
            $deleteCommand = collect($imagesToRemove)
                ->map(fn ($ref) => "docker rmi {$ref} 2>/dev/null || true")
                ->implode(' && ');

            $deleteOutput = instant_remote_process([$deleteCommand], $server, false);
            $cleanupLog[] = [
                'command' => 'batch docker rmi ('.count($imagesToRemove).' images)',
                'output' => $deleteOutput ?? 'Images removed or were in use',
            ];
        }

        return $cleanupLog;
    }
}
