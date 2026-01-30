<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\ServerHealthCheck;
use App\Services\ConfigurationRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ServerConnectionCheckJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 30;

    public function __construct(
        public Server $server,
        public bool $disableMux = true
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('server-connection-check-'.$this->server->uuid))->expireAfter(45)->dontRelease()];
    }

    private function disableSshMux(): void
    {
        $configRepository = app(ConfigurationRepository::class);
        $configRepository->disableSshMux();
    }

    public function handle()
    {
        $startTime = microtime(true);
        $isReachable = false;
        $isUsable = false;
        $errorMessage = null;
        $dockerVersion = null;
        $responseTimeMs = null;

        try {
            // Check if server is disabled
            if ($this->server->settings->force_disabled) {
                $this->server->settings->update([
                    'is_reachable' => false,
                    'is_usable' => false,
                ]);
                Log::debug('ServerConnectionCheck: Server is disabled', [
                    'server_id' => $this->server->id,
                    'server_name' => $this->server->name,
                ]);

                $errorMessage = 'Server is manually disabled';
                $this->logHealthCheck($isReachable, $isUsable, $responseTimeMs, $errorMessage, $dockerVersion);

                return;
            }

            // Check Hetzner server status if applicable
            if ($this->server->hetzner_server_id && $this->server->cloudProviderToken) {
                $this->checkHetznerStatus();
            }

            // Temporarily disable mux if requested
            if ($this->disableMux) {
                $this->disableSshMux();
            }

            // Check basic connectivity first
            $isReachable = $this->checkConnection();
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if (! $isReachable) {
                $this->server->settings->update([
                    'is_reachable' => false,
                    'is_usable' => false,
                ]);

                Log::warning('ServerConnectionCheck: Server not reachable', [
                    'server_id' => $this->server->id,
                    'server_name' => $this->server->name,
                    'server_ip' => $this->server->ip,
                ]);

                $errorMessage = 'Server not reachable via SSH';
                $this->logHealthCheck($isReachable, $isUsable, $responseTimeMs, $errorMessage, $dockerVersion);

                return;
            }

            // Server is reachable, check if Docker is available
            [$isUsable, $dockerVersion] = $this->checkDockerAvailability();

            $this->server->settings->update([
                'is_reachable' => true,
                'is_usable' => $isUsable,
            ]);

            if (! $isUsable) {
                $errorMessage = 'Docker not available or not responding';
            }

            // Log successful health check
            $this->logHealthCheck($isReachable, $isUsable, $responseTimeMs, $errorMessage, $dockerVersion);

        } catch (\Throwable $e) {

            Log::error('ServerConnectionCheckJob failed', [
                'error' => $e->getMessage(),
                'server_id' => $this->server->id,
            ]);
            $this->server->settings->update([
                'is_reachable' => false,
                'is_usable' => false,
            ]);

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);
            $errorMessage = get_class($e).': '.$e->getMessage();
            $this->logHealthCheck($isReachable, $isUsable, $responseTimeMs, $errorMessage, $dockerVersion);

            throw $e;
        }
    }

    private function logHealthCheck(
        bool $isReachable,
        bool $isUsable,
        ?int $responseTimeMs,
        ?string $errorMessage,
        ?string $dockerVersion
    ): void {
        try {
            // Get additional metrics if server is usable
            $diskUsage = null;
            $containerCounts = null;

            if ($isUsable) {
                // Try to get disk usage
                try {
                    $diskUsageStr = $this->server->getDiskUsage();
                    if ($diskUsageStr) {
                        $diskUsage = (float) $diskUsageStr;
                    }
                } catch (\Throwable $e) {
                    // Ignore disk usage errors
                }

                // Try to get container counts
                try {
                    ['containers' => $containers] = $this->server->getContainers();
                    if ($containers) {
                        $running = $containers->filter(fn ($c) => data_get($c, 'State.Status') === 'running')->count();
                        $stopped = $containers->filter(fn ($c) => data_get($c, 'State.Status') !== 'running')->count();
                        $containerCounts = [
                            'running' => $running,
                            'stopped' => $stopped,
                            'total' => $running + $stopped,
                        ];
                    }
                } catch (\Throwable $e) {
                    // Ignore container count errors
                }
            }

            // Determine overall status
            $status = ServerHealthCheck::determineStatus($isReachable, $isUsable, $diskUsage);

            // Create health check record
            ServerHealthCheck::create([
                'server_id' => $this->server->id,
                'status' => $status,
                'is_reachable' => $isReachable,
                'is_usable' => $isUsable,
                'response_time_ms' => $responseTimeMs,
                'disk_usage_percent' => $diskUsage,
                'error_message' => $errorMessage,
                'docker_version' => $dockerVersion,
                'container_counts' => $containerCounts,
                'checked_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Don't fail the job if health check logging fails
            Log::warning('Failed to log server health check', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function checkHetznerStatus(): void
    {
        try {
            $hetznerService = new \App\Services\HetznerService($this->server->cloudProviderToken->token);
            $serverData = $hetznerService->getServer($this->server->hetzner_server_id);
            $status = $serverData['status'] ?? null;

        } catch (\Throwable $e) {
            Log::debug('ServerConnectionCheck: Hetzner status check failed', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
            ]);
        }
        if ($this->server->hetzner_server_status !== $status) {
            $this->server->update(['hetzner_server_status' => $status]);
            $this->server->hetzner_server_status = $status;
            if ($status === 'off') {
                ray('Server is powered off, marking as unreachable');
                throw new \Exception('Server is powered off');
            }
        }

    }

    private function checkConnection(): bool
    {
        try {
            // Use instant_remote_process with a simple command
            // This will automatically handle mux, sudo, IPv6, Cloudflare tunnel, etc.
            $output = instant_remote_process_with_timeout(
                ['ls -la /'],
                $this->server,
                false // don't throw error
            );

            return $output !== null;
        } catch (\Throwable $e) {
            Log::debug('ServerConnectionCheck: Connection check failed', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function checkDockerAvailability(): array
    {
        try {
            // Use instant_remote_process to check Docker
            // The function will automatically handle sudo for non-root users
            $output = instant_remote_process_with_timeout(
                ['docker version --format json'],
                $this->server,
                false // don't throw error
            );

            if ($output === null) {
                return [false, null];
            }

            // Try to parse the JSON output to ensure Docker is really working
            $output = trim($output);
            if (! empty($output)) {
                $dockerInfo = json_decode($output, true);
                $isAvailable = isset($dockerInfo['Server']['Version']);
                $version = $isAvailable ? $dockerInfo['Server']['Version'] : null;

                return [$isAvailable, $version];
            }

            return [false, null];
        } catch (\Throwable $e) {
            Log::debug('ServerConnectionCheck: Docker check failed', [
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
            ]);

            return [false, null];
        }
    }
}
