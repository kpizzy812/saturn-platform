<?php

namespace App\Traits\Deployment;

use App\Jobs\MonitorCanaryDeploymentJob;
use App\Models\ApplicationRollbackEvent;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Trait for canary deployment management.
 *
 * Implements gradual traffic shifting via Traefik weighted service routing.
 * After a successful blue-green deploy, if canary is enabled:
 *   - The new container becomes the "canary" container.
 *   - The old container stays running as the "stable" container.
 *   - Traefik dynamic config is written to the server to split traffic.
 *   - MonitorCanaryDeploymentJob steps through traffic weights over time.
 *
 * Required properties from parent class:
 * - $application, $application_deployment_queue, $server
 */
trait HandlesCanaryDeployment
{
    /**
     * Kick off a canary deployment after a successful blue-green deploy.
     *
     * Writes initial Traefik weighted config (step[0] % canary),
     * stores canary state on the deployment record, and dispatches
     * the monitoring job.
     */
    private function initiate_canary(string $canaryContainerName, string $stableContainerName): void
    {
        $settings = $this->application->settings;
        $steps = $settings->canary_steps ?? [10, 25, 50, 100];

        if (empty($steps)) {
            $steps = [10, 25, 50, 100];
        }

        $initialWeight = (int) $steps[0];

        Log::info("Initiating canary deployment for {$this->application->name}: canary={$canaryContainerName}, stable={$stableContainerName}, initial_weight={$initialWeight}%");

        // Write initial Traefik routing config
        $this->update_canary_traffic($initialWeight, $canaryContainerName, $stableContainerName);

        // Persist canary state on the deployment record
        $this->application_deployment_queue->update([
            'canary_state' => [
                'canary_container' => $canaryContainerName,
                'stable_container' => $stableContainerName,
                'current_step' => 0,
                'current_weight' => $initialWeight,
                'started_at' => now()->toIso8601String(),
            ],
        ]);

        $this->application_deployment_queue->addLogEntry(
            "Canary deployment initiated: {$initialWeight}% traffic to {$canaryContainerName}, {$this->calcStableWeight($initialWeight)}% to {$stableContainerName}."
        );

        // Start monitoring job
        MonitorCanaryDeploymentJob::dispatch(
            deployment: $this->application_deployment_queue,
            canaryContainer: $canaryContainerName,
            stableContainer: $stableContainerName,
            currentStep: 0,
        )->delay(now()->addMinutes($settings->canary_step_minutes ?? 5));
    }

    /**
     * Write Traefik weighted routing config to the master server's dynamic config directory.
     *
     * Generates a YAML file for Traefik's file provider that splits traffic
     * between the stable and canary containers using weighted services.
     * The config must be written to the master server where Traefik is running,
     * not to the application deployment server.
     */
    private function update_canary_traffic(int $canaryPercent, string $canaryContainer, string $stableContainer): void
    {
        $stablePercent = $this->calcStableWeight($canaryPercent);
        $yaml = $this->generate_canary_traefik_yaml($canaryPercent, $stablePercent, $canaryContainer, $stableContainer);

        $masterServer = Server::masterServer();
        if (! $masterServer) {
            throw new \RuntimeException('Canary deployment requires a master server with Traefik proxy configured.');
        }

        $configPath = $this->get_canary_config_path($masterServer);
        $dynamicDir = dirname($configPath);
        $base64 = base64_encode($yaml);

        try {
            instant_remote_process(
                [
                    "mkdir -p {$dynamicDir}",
                    "echo '{$base64}' | base64 -d > {$configPath}",
                ],
                $masterServer,
                throwError: true
            );

            Log::info("Canary traffic updated for {$this->application->name}: canary={$canaryPercent}%, stable={$stablePercent}%");
        } catch (\Throwable $e) {
            Log::error("Failed to write canary Traefik config for {$this->application->name}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Get the canary Traefik config file path on the master server's dynamic config directory.
     */
    private function get_canary_config_path(Server $masterServer): string
    {
        $dynamicPath = rtrim($masterServer->proxyPath(), '/').'/dynamic';

        return "{$dynamicPath}/saturn-canary-{$this->application->uuid}.yaml";
    }

    /**
     * Promote canary to 100%: shift all traffic, rename containers, clean up.
     */
    private function promote_canary(string $canaryContainer, string $stableContainer): void
    {
        Log::info("Promoting canary for {$this->application->name}: {$canaryContainer} → {$stableContainer}");

        // Send 100% traffic to canary
        $this->update_canary_traffic(100, $canaryContainer, $stableContainer);

        // Brief pause to let Traefik reload the dynamic config before removing the stable container
        Sleep::for(5)->seconds();

        // Remove old stable container
        $escapedStable = escapeshellarg($stableContainer);
        instant_remote_process(
            ["docker rm -f {$escapedStable} 2>/dev/null || true"],
            $this->server,
            throwError: false
        );

        // Rename canary → stable so the name stays consistent
        $escapedCanary = escapeshellarg($canaryContainer);
        instant_remote_process(
            ["docker rename {$escapedCanary} {$escapedStable} 2>/dev/null || true"],
            $this->server,
            throwError: false
        );

        // Remove temporary Traefik config
        $this->remove_canary_config();

        $this->application_deployment_queue->addLogEntry("Canary promoted successfully. {$canaryContainer} is now serving 100% of traffic as {$stableContainer}.");

        Log::info("Canary promoted for {$this->application->name}");
    }

    /**
     * Roll back canary: shift all traffic back to stable, remove canary container.
     */
    private function rollback_canary(string $canaryContainer, string $stableContainer, string $reason): void
    {
        Log::warning("Rolling back canary for {$this->application->name}, reason: {$reason}");

        // Shift all traffic back to stable
        $this->update_canary_traffic(0, $canaryContainer, $stableContainer);

        // Brief pause to let Traefik reload before removing the canary container
        Sleep::for(5)->seconds();

        // Remove canary container
        $escapedCanary = escapeshellarg($canaryContainer);
        instant_remote_process(
            ["docker rm -f {$escapedCanary} 2>/dev/null || true"],
            $this->server,
            throwError: false
        );

        // Remove temporary Traefik config
        $this->remove_canary_config();

        // Create rollback event
        ApplicationRollbackEvent::create([
            'application_id' => $this->application->id,
            'failed_deployment_id' => $this->application_deployment_queue->id,
            'trigger_reason' => 'canary_'.$reason,
            'trigger_type' => 'automatic',
            'metrics_snapshot' => [
                'canary_container' => $canaryContainer,
                'stable_container' => $stableContainer,
                'rollback_reason' => $reason,
                'rolled_back_at' => now()->toIso8601String(),
            ],
            'status' => ApplicationRollbackEvent::STATUS_SUCCESS,
            'from_commit' => $this->application_deployment_queue->commit,
            'triggered_at' => now(),
            'completed_at' => now(),
        ]);

        $this->application_deployment_queue->addLogEntry("Canary rolled back due to: {$reason}. Stable container {$stableContainer} is serving 100% of traffic.");

        // Notify team
        $team = $this->application->environment?->project?->team;
        if ($team) {
            $team->notify(
                new \App\Notifications\Application\DeploymentFailed(
                    $this->application,
                    $this->application_deployment_queue->deployment_uuid
                )
            );
        }
    }

    /**
     * Remove the Traefik canary config file from the master server's dynamic config directory.
     */
    private function remove_canary_config(): void
    {
        $masterServer = Server::masterServer();
        if (! $masterServer) {
            Log::warning("Canary: cannot remove config for {$this->application->name} — master server not found");

            return;
        }

        $configPath = $this->get_canary_config_path($masterServer);
        instant_remote_process(
            ["rm -f {$configPath}"],
            $masterServer,
            throwError: false
        );
    }

    /**
     * Generate Traefik YAML for weighted traffic splitting between canary and stable.
     */
    private function generate_canary_traefik_yaml(
        int $canaryWeight,
        int $stableWeight,
        string $canaryContainer,
        string $stableContainer
    ): string {
        $appUuid = $this->application->uuid;
        $port = $this->get_canary_app_port();
        $fqdn = $this->get_canary_fqdn();

        $routerRule = "Host(`{$fqdn}`)";

        return <<<YAML
http:
  services:
    {$appUuid}-canary-weighted:
      weighted:
        services:
          - name: {$appUuid}-stable-backend
            weight: {$stableWeight}
          - name: {$appUuid}-canary-backend
            weight: {$canaryWeight}
    {$appUuid}-stable-backend:
      loadBalancer:
        servers:
          - url: 'http://{$stableContainer}:{$port}'
    {$appUuid}-canary-backend:
      loadBalancer:
        servers:
          - url: 'http://{$canaryContainer}:{$port}'
  routers:
    {$appUuid}-canary:
      entryPoints:
        - web
        - websecure
      service: {$appUuid}-canary-weighted
      rule: '{$routerRule}'
      tls:
        certResolver: letsencrypt
YAML;
    }

    /**
     * Get the primary port exposed by the application.
     */
    private function get_canary_app_port(): int
    {
        $ports = $this->application->ports_exposes;
        if ($ports) {
            $first = explode(',', $ports)[0];
            $port = (int) trim($first);
            if ($port > 0) {
                return $port;
            }
        }

        return 80;
    }

    /**
     * Get the primary FQDN for the application.
     */
    private function get_canary_fqdn(): string
    {
        $fqdn = $this->application->fqdn;
        if ($fqdn) {
            // fqdn can be comma-separated; take the first one
            $first = explode(',', $fqdn)[0];

            // Strip protocol prefix if present
            return preg_replace('#^https?://#', '', trim($first));
        }

        return $this->application->uuid.'.local';
    }

    /**
     * Capture the currently running container name as the stable container,
     * to be preserved when canary deployment is enabled.
     *
     * This is called from rolling_update() AFTER health_check() passes but
     * BEFORE stop_running_container() would normally be called. It stores
     * the old container name in $this->stableContainerName so that
     * post_deployment() can hand it to initiate_canary().
     */
    private function capture_stable_container_for_canary(): void
    {
        try {
            $containers = getCurrentApplicationContainerStatus($this->server, $this->application->id, 0);

            $stableContainer = $containers
                ->filter(fn ($c) => data_get($c, 'Names') !== $this->container_name)
                ->first();

            if ($stableContainer) {
                $this->stableContainerName = data_get($stableContainer, 'Names');
                $this->application_deployment_queue->addLogEntry(
                    "Canary mode: keeping existing container '{$this->stableContainerName}' as stable. New container '{$this->container_name}' will be promoted gradually."
                );
            } else {
                // No previous container found — this is the first deploy.
                // Canary makes no sense without a stable baseline; fall back to normal flow.
                $this->stableContainerName = null;
                $this->application_deployment_queue->addLogEntry(
                    'Canary mode: no existing container found — skipping canary for this deployment (first deploy).'
                );
            }
        } catch (\Throwable $e) {
            Log::warning("Canary: could not detect stable container for {$this->application->name}: {$e->getMessage()}");
            $this->stableContainerName = null;
        }
    }

    /**
     * Calculate stable weight given canary weight (100 - canaryWeight, min 0).
     */
    private function calcStableWeight(int $canaryWeight): int
    {
        return max(0, 100 - $canaryWeight);
    }
}
