<?php

/**
 * Unit tests for ProxyDashboardCacheService.
 *
 * Tests cover:
 * - getCacheKey() — returns deterministic key from server ID
 * - isTraefikDashboardAvailableFromConfiguration() — detects api.dashboard + api.insecure flags
 * - isTraefikDashboardAvailableFromCache() — reads cached value
 * - clearCache() / clearCacheForServers() — removes keys from cache
 */

use App\Models\Server;
use App\Services\ProxyDashboardCacheService;
use Illuminate\Support\Facades\Cache;

// ─── getCacheKey() ────────────────────────────────────────────────────────────

test('getCacheKey returns expected key format', function () {
    $server = new Server;
    $server->id = 42;
    expect(ProxyDashboardCacheService::getCacheKey($server))
        ->toBe('server:42:traefik:dashboard_available');
});

test('getCacheKey uses server id in key', function () {
    $server = new Server;
    $server->id = 999;
    $key = ProxyDashboardCacheService::getCacheKey($server);
    expect($key)->toContain('999');
    expect($key)->toContain('traefik');
    expect($key)->toContain('dashboard_available');
});

// ─── isTraefikDashboardAvailableFromConfiguration() ──────────────────────────

test('stores true in cache when both api.dashboard and api.insecure flags are present', function () {
    Cache::flush();
    $server = new Server;
    $server->id = 1;

    $config = '--api.dashboard=true --api.insecure=true --entrypoints.web.address=:80';
    ProxyDashboardCacheService::isTraefikDashboardAvailableFromConfiguration($server, $config);

    expect(ProxyDashboardCacheService::isTraefikDashboardAvailableFromCache($server))->toBeTrue();
});

test('stores false in cache when api.dashboard flag is missing', function () {
    Cache::flush();
    $server = new Server;
    $server->id = 2;

    $config = '--api.insecure=true --entrypoints.web.address=:80';
    ProxyDashboardCacheService::isTraefikDashboardAvailableFromConfiguration($server, $config);

    expect(ProxyDashboardCacheService::isTraefikDashboardAvailableFromCache($server))->toBeFalse();
});

test('stores false in cache when api.insecure flag is missing', function () {
    Cache::flush();
    $server = new Server;
    $server->id = 3;

    $config = '--api.dashboard=true --entrypoints.web.address=:80';
    ProxyDashboardCacheService::isTraefikDashboardAvailableFromConfiguration($server, $config);

    expect(ProxyDashboardCacheService::isTraefikDashboardAvailableFromCache($server))->toBeFalse();
});

test('stores false in cache when config is empty', function () {
    Cache::flush();
    $server = new Server;
    $server->id = 4;

    ProxyDashboardCacheService::isTraefikDashboardAvailableFromConfiguration($server, '');

    expect(ProxyDashboardCacheService::isTraefikDashboardAvailableFromCache($server))->toBeFalse();
});

// ─── isTraefikDashboardAvailableFromCache() ───────────────────────────────────

test('isTraefikDashboardAvailableFromCache returns false when nothing is cached', function () {
    Cache::flush();
    $server = new Server;
    $server->id = 100;

    expect(ProxyDashboardCacheService::isTraefikDashboardAvailableFromCache($server))->toBeFalse();
});

// ─── clearCache() ─────────────────────────────────────────────────────────────

test('clearCache removes cached value for server', function () {
    Cache::flush();
    $server = new Server;
    $server->id = 5;

    // Populate cache first
    $config = '--api.dashboard=true --api.insecure=true';
    ProxyDashboardCacheService::isTraefikDashboardAvailableFromConfiguration($server, $config);
    expect(ProxyDashboardCacheService::isTraefikDashboardAvailableFromCache($server))->toBeTrue();

    // Now clear
    ProxyDashboardCacheService::clearCache($server);

    expect(ProxyDashboardCacheService::isTraefikDashboardAvailableFromCache($server))->toBeFalse();
});

// ─── clearCacheForServers() ───────────────────────────────────────────────────

test('clearCacheForServers removes cached values for all given server ids', function () {
    Cache::flush();

    // Manually populate cache for two servers
    Cache::forever('server:10:traefik:dashboard_available', true);
    Cache::forever('server:11:traefik:dashboard_available', true);

    expect(Cache::get('server:10:traefik:dashboard_available'))->toBeTrue();
    expect(Cache::get('server:11:traefik:dashboard_available'))->toBeTrue();

    ProxyDashboardCacheService::clearCacheForServers([10, 11]);

    expect(Cache::get('server:10:traefik:dashboard_available'))->toBeNull();
    expect(Cache::get('server:11:traefik:dashboard_available'))->toBeNull();
});

test('clearCacheForServers with empty array does not throw', function () {
    expect(fn () => ProxyDashboardCacheService::clearCacheForServers([]))->not->toThrow(\Exception::class);
});
