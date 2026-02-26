<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Simple cache-based circuit breaker for external API calls.
 *
 * States:
 *   CLOSED — normal operation, requests pass through.
 *   OPEN   — threshold of failures reached; requests are rejected immediately
 *            without calling the API, until the cooldown expires.
 *
 * After the cooldown period the circuit resets to CLOSED automatically
 * (the cache key expires). A successful call also resets the counter.
 */
class CircuitBreaker
{
    /** Per-service configuration. */
    private const CONFIGS = [
        'hetzner' => [
            'threshold' => 5,  // failures within window before opening
            'window_seconds' => 60, // sliding window for counting failures
            'cooldown_seconds' => 60, // how long the circuit stays open
        ],
        'github' => [
            'threshold' => 5,
            'window_seconds' => 60,
            'cooldown_seconds' => 30,
        ],
    ];

    private const DEFAULT_CONFIG = [
        'threshold' => 5,
        'window_seconds' => 60,
        'cooldown_seconds' => 60,
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns true when the circuit is open and the service should NOT be called.
     */
    public static function isOpen(string $service): bool
    {
        return Cache::has(self::openKey($service));
    }

    /**
     * Record a failed request. Opens the circuit when the failure threshold is reached.
     */
    public static function recordFailure(string $service): void
    {
        $config = self::config($service);
        $failKey = self::failKey($service);

        $failures = (int) Cache::get($failKey, 0) + 1;
        Cache::put($failKey, $failures, now()->addSeconds($config['window_seconds']));

        if ($failures >= $config['threshold'] && ! self::isOpen($service)) {
            Cache::put(self::openKey($service), true, now()->addSeconds($config['cooldown_seconds']));
            Log::warning('Circuit breaker OPEN', [
                'service' => $service,
                'failures' => $failures,
                'cooldown_seconds' => $config['cooldown_seconds'],
            ]);
        }
    }

    /**
     * Record a successful request. Clears the failure counter and closes the circuit.
     */
    public static function recordSuccess(string $service): void
    {
        $wasOpen = self::isOpen($service);

        Cache::forget(self::failKey($service));
        Cache::forget(self::openKey($service));

        if ($wasOpen) {
            Log::info('Circuit breaker CLOSED (service recovered)', ['service' => $service]);
        }
    }

    /**
     * Manually reset the circuit (useful in admin commands and tests).
     */
    public static function reset(string $service): void
    {
        Cache::forget(self::failKey($service));
        Cache::forget(self::openKey($service));
    }

    /**
     * Return the current failure count within the active window (0 if no failures).
     */
    public static function failureCount(string $service): int
    {
        return (int) Cache::get(self::failKey($service), 0);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return array{threshold: int, window_seconds: int, cooldown_seconds: int} */
    private static function config(string $service): array
    {
        return self::CONFIGS[$service] ?? self::DEFAULT_CONFIG;
    }

    private static function openKey(string $service): string
    {
        return "circuit:open:{$service}";
    }

    private static function failKey(string $service): string
    {
        return "circuit:fail:{$service}";
    }
}
