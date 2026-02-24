<?php

namespace App\Jobs;

use App\Actions\Proxy\StartProxy;
use App\Actions\Server\InstallDocker;
use App\Enums\ProxyTypes;
use App\Exceptions\RateLimitException;
use App\Models\AutoProvisioningEvent;
use App\Models\CloudProviderToken;
use App\Models\InstanceSettings;
use App\Models\PrivateKey;
use App\Models\Server;
use App\Notifications\Server\ServerAutoProvisioned;
use App\Services\HetznerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AutoProvisionServerJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 600; // 10 minutes for full provisioning

    public function middleware(): array
    {
        // Prevent concurrent auto-provisioning
        return [(new WithoutOverlapping('auto-provision-server'))->expireAfter(600)->dontRelease()];
    }

    public function __construct(
        public Server $triggerServer,
        public string $triggerReason,
        public array $triggerMetrics = []
    ) {}

    public function handle(): void
    {
        try {
            $settings = InstanceSettings::get();

            // Check if auto-provisioning is enabled
            if (! $settings->auto_provision_enabled) {
                Log::debug('Auto-provisioning is disabled');

                return;
            }

            // Check daily limit
            $provisionedToday = AutoProvisioningEvent::countProvisionedToday();
            if ($provisionedToday >= $settings->auto_provision_max_servers_per_day) {
                Log::debug('Daily auto-provisioning limit reached', [
                    'limit' => $settings->auto_provision_max_servers_per_day,
                    'provisioned_today' => $provisionedToday,
                ]);

                return;
            }

            // Check if there's already an active provisioning in progress
            if (AutoProvisioningEvent::hasActiveProvisioning()) {
                Log::debug('Another auto-provisioning is already in progress');

                return;
            }

            // Check cooldown
            $cooldownKey = "auto-provision-cooldown-{$this->triggerServer->uuid}";
            if (Cache::has($cooldownKey)) {
                Log::debug('Auto-provisioning cooldown active for server', ['server' => $this->triggerServer->name]);

                return;
            }

            // Get provider token (from InstanceSettings or first available Hetzner token)
            $provider = $settings->auto_provision_provider ?? 'hetzner';
            $token = $this->getCloudProviderToken($provider);

            if (! $token) {
                Log::debug('No cloud provider token found for auto-provisioning', ['provider' => $provider]);

                return;
            }

            // Get private key (use trigger server's key or first available)
            $privateKey = $this->getPrivateKey();
            if (! $privateKey) {
                Log::debug('No private key found for auto-provisioning');

                return;
            }

            // Create provisioning event for tracking
            $event = AutoProvisioningEvent::create([
                'trigger_server_id' => $this->triggerServer->id,
                'team_id' => $this->triggerServer->team_id,
                'trigger_reason' => $this->triggerReason,
                'provider' => $provider,
                'trigger_metrics' => $this->triggerMetrics,
                'server_config' => [
                    'type' => $settings->auto_provision_server_type,
                    'location' => $settings->auto_provision_location,
                    'image' => 'ubuntu-24.04',
                ],
                'status' => AutoProvisioningEvent::STATUS_PENDING,
            ]);

            // Set cooldown to prevent rapid provisioning
            Cache::put($cooldownKey, true, now()->addMinutes($settings->auto_provision_cooldown_minutes));

            // Create server based on provider
            $server = match ($provider) {
                'hetzner' => $this->createHetznerServer($token, $privateKey, $settings, $event),
                default => throw new \Exception("Unsupported provider: {$provider}"),
            };

            // Mark as installing
            $event->markAsInstalling($server->id);

            // Wait for server to be reachable and install Docker
            $this->waitAndInstallDocker($server, $event);

            // Mark as ready
            $event->markAsReady();

            // Send notification
            $this->triggerServer->team?->notify(new ServerAutoProvisioned(
                $server,
                $this->triggerServer,
                $this->triggerReason,
                $this->triggerMetrics
            ));

            Log::info('Auto-provisioning completed successfully', [
                'new_server' => $server->name,
                'trigger_server' => $this->triggerServer->name,
                'trigger_reason' => $this->triggerReason,
            ]);

        } catch (RateLimitException $e) {
            Log::warning('Rate limit exceeded during auto-provisioning', ['retry_after' => $e->retryAfter]);

            // Re-dispatch with delay
            if ($e->retryAfter) {
                self::dispatch($this->triggerServer, $this->triggerReason, $this->triggerMetrics)
                    ->delay(now()->addSeconds($e->retryAfter));
            }
        } catch (\Throwable $e) {
            Log::error('Auto-provisioning failed', ['error' => $e->getMessage()]);

            // Mark event as failed if exists
            if (isset($event)) {
                $event->markAsFailed($e->getMessage());
            }
        }
    }

    /**
     * Get cloud provider token for auto-provisioning.
     */
    private function getCloudProviderToken(string $provider): ?CloudProviderToken
    {
        // Try to use instance-level API key if configured
        $settings = InstanceSettings::get();
        if ($settings->auto_provision_api_key) {
            // Create a temporary token object
            $tempToken = new CloudProviderToken;
            $tempToken->token = $settings->auto_provision_api_key;
            $tempToken->provider = $provider;

            return $tempToken;
        }

        // Fall back to first available token for the provider in the team
        return CloudProviderToken::where('team_id', $this->triggerServer->team_id)
            ->where('provider', $provider)
            ->first();
    }

    /**
     * Get private key for the new server.
     */
    private function getPrivateKey(): ?PrivateKey
    {
        // First try to use trigger server's private key
        if ($this->triggerServer->private_key_id) {
            $key = PrivateKey::find($this->triggerServer->private_key_id);
            if ($key) {
                return $key;
            }
        }

        // Fall back to first available private key in the team
        return PrivateKey::where('team_id', $this->triggerServer->team_id)
            ->where('is_git_related', false)
            ->first();
    }

    /**
     * Create server on Hetzner Cloud.
     */
    private function createHetznerServer(
        CloudProviderToken $token,
        PrivateKey $privateKey,
        InstanceSettings $settings,
        AutoProvisioningEvent $event
    ): Server {
        $hetznerService = new HetznerService($token->token);

        // Get public key and MD5 fingerprint
        $publicKey = $privateKey->getPublicKey();
        $md5Fingerprint = PrivateKey::generateMd5Fingerprint($privateKey->private_key);

        // Check if SSH key already exists on Hetzner
        $existingSshKeys = $hetznerService->getSshKeys();
        $existingKey = null;

        foreach ($existingSshKeys as $key) {
            if ($key['fingerprint'] === $md5Fingerprint) {
                $existingKey = $key;
                break;
            }
        }

        // Upload SSH key if it doesn't exist
        if ($existingKey) {
            $sshKeyId = $existingKey['id'];
        } else {
            $sshKeyName = 'saturn-auto-'.$privateKey->name;
            $uploadedKey = $hetznerService->uploadSshKey($sshKeyName, $publicKey);
            $sshKeyId = $uploadedKey['id'];
        }

        // Generate server name
        $serverName = 'saturn-auto-'.strtolower(generate_random_name());

        // Get Ubuntu 24.04 image ID
        $images = $hetznerService->getImages();
        $ubuntuImage = collect($images)->first(function ($image) {
            return str_contains(strtolower($image['name'] ?? ''), 'ubuntu') &&
                   str_contains($image['name'] ?? '', '24.04');
        });

        $imageId = $ubuntuImage['id'] ?? 114690389; // Default to Ubuntu 24.04 LTS

        $event->markAsProvisioning();

        // Create server on Hetzner
        $params = [
            'name' => $serverName,
            'server_type' => $settings->auto_provision_server_type,
            'image' => $imageId,
            'location' => $settings->auto_provision_location,
            'start_after_create' => true,
            'ssh_keys' => [$sshKeyId],
            'public_net' => [
                'enable_ipv4' => true,
                'enable_ipv6' => true,
            ],
        ];

        $hetznerServer = $hetznerService->createServer($params);

        if (empty($hetznerServer['id'])) {
            throw new \Exception('Hetzner API did not return server ID');
        }

        $event->markAsProvisioning((string) $hetznerServer['id']);

        // Wait for server to be running
        $ipAddress = $this->waitForServerReady($hetznerService, $hetznerServer['id']);

        if (! $ipAddress) {
            throw new \Exception('Failed to get IP address for new server');
        }

        // Create server in Saturn Platform database
        $server = Server::create([
            'name' => $serverName,
            'ip' => $ipAddress,
            'user' => 'root',
            'port' => 22,
            'team_id' => $this->triggerServer->team_id,
            'private_key_id' => $privateKey->id,
            'cloud_provider_token_id' => $token->id ?? null,
            'hetzner_server_id' => $hetznerServer['id'],
        ]);

        $server->proxy->set('status', 'exited');
        $server->proxy->set('type', ProxyTypes::TRAEFIK->value);
        $server->save();

        return $server;
    }

    /**
     * Wait for Hetzner server to be running and return IP.
     */
    private function waitForServerReady(HetznerService $hetznerService, int $serverId, int $timeout = 180): ?string
    {
        $startTime = time();

        while (time() - $startTime < $timeout) {
            $server = $hetznerService->getServer($serverId);

            if (($server['status'] ?? '') === 'running') {
                // Prefer IPv4, fallback to IPv6
                $ipv4 = $server['public_net']['ipv4']['ip'] ?? null;
                $ipv6 = $server['public_net']['ipv6']['ip'] ?? null;

                return $ipv4 ?: $ipv6;
            }

            sleep(5);
        }

        return null;
    }

    /**
     * Wait for SSH and install Docker.
     */
    private function waitAndInstallDocker(Server $server, AutoProvisioningEvent $event): void
    {
        // Wait for SSH to be available (max 2 minutes)
        $sshReady = false;
        $attempts = 0;
        $maxAttempts = 24; // 2 minutes with 5 second intervals

        while (! $sshReady && $attempts < $maxAttempts) {
            try {
                $result = $server->validateConnection();
                if (! empty($result['uptime'])) {
                    $sshReady = true;
                }
            } catch (\Throwable $e) {
                // SSH not ready yet
            }

            if (! $sshReady) {
                sleep(5);
                $attempts++;
            }
        }

        if (! $sshReady) {
            throw new \Exception('Server SSH not available after 2 minutes');
        }

        // Install Docker
        InstallDocker::run($server);

        // Start Traefik proxy on the new server
        StartProxy::run($server, async: false);

        // Update server settings
        $server->settings->update([
            'is_reachable' => true,
            'is_usable' => true,
        ]);
    }
}
