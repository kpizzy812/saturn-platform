<?php

namespace Tests\Unit\Jobs;

use App\Jobs\AutoProvisionServerJob;
use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Mockery;
use ReflectionClass;

/*
 * Unit tests for AutoProvisionServerJob.
 *
 * handle() requires live Hetzner API and SSH — covered in integration/e2e tests.
 * These tests verify job configuration, interface contracts, constructor behavior,
 * middleware setup, failed() callback, and source-level guard logic.
 */

function makeServerForAutoProvision(): Server
{
    $server = Mockery::mock(Server::class)->makePartial();
    $server->id = 1;
    $server->uuid = 'auto-provision-uuid';
    $server->name = 'trigger-server';
    $server->team_id = 1;
    $server->private_key_id = null;

    return $server;
}

afterEach(function () {
    Mockery::close();
});

// ===========================================================================
// 1. Interface contracts
// ===========================================================================

it('implements ShouldQueue', function () {
    $interfaces = class_implements(AutoProvisionServerJob::class);

    expect($interfaces)->toContain(ShouldQueue::class);
});

it('implements ShouldBeEncrypted (contains sensitive API tokens)', function () {
    $interfaces = class_implements(AutoProvisionServerJob::class);

    expect($interfaces)->toContain(ShouldBeEncrypted::class);
});

// ===========================================================================
// 2. Job configuration
// ===========================================================================

it('has $tries equal to 1', function () {
    $server = makeServerForAutoProvision();
    $job = new AutoProvisionServerJob($server, 'high_cpu');

    expect($job->tries)->toBe(1);
});

it('has $timeout equal to 600', function () {
    $server = makeServerForAutoProvision();
    $job = new AutoProvisionServerJob($server, 'high_cpu');

    expect($job->timeout)->toBe(600);
});

it('declares $tries and $timeout via reflection', function () {
    $defaults = (new ReflectionClass(AutoProvisionServerJob::class))->getDefaultProperties();

    expect($defaults['tries'])->toBe(1)
        ->and($defaults['timeout'])->toBe(600);
});

// ===========================================================================
// 3. Middleware — WithoutOverlapping prevents concurrent provisioning
// ===========================================================================

it('returns exactly one middleware entry', function () {
    $server = makeServerForAutoProvision();
    $job = new AutoProvisionServerJob($server, 'high_cpu');

    expect($job->middleware())->toHaveCount(1);
});

it('uses WithoutOverlapping middleware', function () {
    $server = makeServerForAutoProvision();
    $job = new AutoProvisionServerJob($server, 'high_cpu');

    expect($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class);
});

it('uses the global key "auto-provision-server" for WithoutOverlapping', function () {
    $server = makeServerForAutoProvision();
    $job = new AutoProvisionServerJob($server, 'high_cpu');

    $serialized = serialize($job->middleware()[0]);

    expect($serialized)->toContain('auto-provision-server');
});

it('middleware uses expireAfter(600) matching job timeout', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain('expireAfter(600)');
});

it('middleware uses dontRelease() to drop duplicate dispatches', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain('dontRelease()');
});

// ===========================================================================
// 4. Constructor — stores all parameters
// ===========================================================================

it('stores the triggerServer instance', function () {
    $server = makeServerForAutoProvision();
    $job = new AutoProvisionServerJob($server, 'memory_pressure');

    expect($job->triggerServer)->toBe($server);
});

it('stores the triggerReason string', function () {
    $server = makeServerForAutoProvision();
    $job = new AutoProvisionServerJob($server, 'disk_full');

    expect($job->triggerReason)->toBe('disk_full');
});

it('defaults triggerMetrics to an empty array', function () {
    $server = makeServerForAutoProvision();
    $job = new AutoProvisionServerJob($server, 'cpu_spike');

    expect($job->triggerMetrics)->toBe([]);
});

it('stores provided triggerMetrics array', function () {
    $server = makeServerForAutoProvision();
    $metrics = ['cpu' => 95.5, 'memory' => 80.0];
    $job = new AutoProvisionServerJob($server, 'cpu_spike', $metrics);

    expect($job->triggerMetrics)->toBe($metrics);
});

// ===========================================================================
// 5. Guard conditions — source-level verification
// ===========================================================================

it('returns early when auto_provision_enabled is false', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('auto_provision_enabled')
        ->toContain('Auto-provisioning is disabled');
});

it('returns early when daily server limit is reached', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('countProvisionedToday()')
        ->toContain('auto_provision_max_servers_per_day')
        ->toContain('Daily auto-provisioning limit reached');
});

it('returns early when another provisioning is already active', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('hasActiveProvisioning()')
        ->toContain('Another auto-provisioning is already in progress');
});

it('returns early when cooldown cache key is active', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('auto-provision-cooldown-')
        ->toContain('Cache::has($cooldownKey)')
        ->toContain('Auto-provisioning cooldown active for server');
});

it('returns early when no cloud provider token is found', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('getCloudProviderToken(')
        ->toContain('No cloud provider token found for auto-provisioning');
});

it('returns early when no private key is available', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('getPrivateKey()')
        ->toContain('No private key found for auto-provisioning');
});

// ===========================================================================
// 6. Token resolution strategy — instance key takes priority
// ===========================================================================

it('prefers instance-level API key over team token', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    // Instance key checked first
    expect($source)->toContain('auto_provision_api_key');
    // Team token is the fallback
    expect($source)->toContain("CloudProviderToken::where('team_id'");
});

it('creates a temporary CloudProviderToken when using instance-level API key', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('new CloudProviderToken')
        ->toContain('$tempToken->token');
});

// ===========================================================================
// 7. Private key resolution strategy — server key takes priority
// ===========================================================================

it('prefers the trigger server private key over team keys', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('$this->triggerServer->private_key_id')
        ->toContain('PrivateKey::find($this->triggerServer->private_key_id)');
});

it('falls back to first non-git team private key', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain("where('is_git_related', false)")
        ->toContain("PrivateKey::where('team_id'");
});

// ===========================================================================
// 8. Cooldown is set even on failure (prevents rapid retry storms)
// ===========================================================================

it('sets cooldown cache key before creating the server', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('Cache::put($cooldownKey')
        ->toContain('auto_provision_cooldown_minutes');
});

// ===========================================================================
// 9. Event lifecycle — status transitions
// ===========================================================================

it('creates AutoProvisioningEvent with STATUS_PENDING at start', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('AutoProvisioningEvent::create')
        ->toContain('AutoProvisioningEvent::STATUS_PENDING');
});

it('marks event as provisioning when Hetzner creation starts', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain('$event->markAsProvisioning()');
});

it('marks event as installing after server is created', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain('$event->markAsInstalling(');
});

it('marks event as ready after docker installation', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain('$event->markAsReady()');
});

it('marks event as failed when an exception occurs', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain('$event->markAsFailed(');
});

// ===========================================================================
// 10. RateLimitException — re-dispatches with delay
// ===========================================================================

it('catches RateLimitException and re-dispatches with retryAfter delay', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('RateLimitException')
        ->toContain('Rate limit exceeded during auto-provisioning')
        ->toContain('->delay(now()->addSeconds($e->retryAfter))');
});

// ===========================================================================
// 11. Provider selection — hetzner is the only supported provider
// ===========================================================================

it('only supports hetzner as cloud provider (match expression)', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain("'hetzner' => \$this->createHetznerServer(")
        ->toContain('Unsupported provider:');
});

// ===========================================================================
// 12. Hetzner server creation — source checks
// ===========================================================================

it('checks for existing SSH key fingerprint before uploading', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('getSshKeys()')
        ->toContain('$key[\'fingerprint\'] === $md5Fingerprint');
});

it('generates server name with saturn-auto- prefix', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain("'saturn-auto-'.strtolower(generate_random_name())");
});

it('falls back to hardcoded Ubuntu 24.04 image id when API returns no match', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    // Default Ubuntu 24.04 LTS image ID as fallback
    expect($source)->toContain('114690389');
});

it('throws when Hetzner API returns no server ID', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain("'Hetzner API did not return server ID'");
});

// ===========================================================================
// 13. waitForServerReady — timeout logic
// ===========================================================================

it('waitForServerReady returns null when server does not become running within timeout', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain('return null;')
        ->toContain('return $ipv4 ?: $ipv6');
});

it('waitForServerReady prefers IPv4 over IPv6', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain("'ipv4']['ip'] ?? null")
        ->toContain("'ipv6']['ip'] ?? null");
});

it('throws when IP address is not obtained after polling', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain("'Failed to get IP address for new server'");
});

// ===========================================================================
// 14. waitAndInstallDocker — SSH readiness polling
// ===========================================================================

it('throws when SSH is not available after max attempts', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain("'Server SSH not available after 2 minutes'");
});

it('marks server as reachable and usable after successful installation', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)
        ->toContain("'is_reachable' => true")
        ->toContain("'is_usable' => true");
});

it('sends ServerAutoProvisioned notification on success', function () {
    $source = file_get_contents(app_path('Jobs/AutoProvisionServerJob.php'));

    expect($source)->toContain('ServerAutoProvisioned');
});
