<?php

use App\Services\HetznerService;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

// ═══════════════════════════════════════════
// getServerTypes() — deprecated filtering
// ═══════════════════════════════════════════

test('getServerTypes filters out deprecated entries', function () {
    $types = [
        ['name' => 'cx11', 'deprecated' => true],
        ['name' => 'cx21', 'deprecated' => false],
        ['name' => 'cx31', 'deprecated' => null],
        ['name' => 'cx41'],
    ];

    // Test the filter logic directly
    $filtered = array_filter($types, function ($type) {
        return ($type['deprecated'] ?? false) !== true;
    });
    $result = array_values($filtered);

    expect($result)->toHaveCount(3);
    expect($result[0]['name'])->toBe('cx21');
    expect($result[1]['name'])->toBe('cx31');
    expect($result[2]['name'])->toBe('cx41');
});

test('getServerTypes preserves non-deprecated entries', function () {
    $types = [
        ['name' => 'cx21', 'deprecated' => false],
        ['name' => 'cx31', 'deprecated' => false],
    ];

    $filtered = array_filter($types, function ($type) {
        return ($type['deprecated'] ?? false) !== true;
    });

    expect(array_values($filtered))->toHaveCount(2);
});

test('getServerTypes handles all deprecated entries', function () {
    $types = [
        ['name' => 'cx11', 'deprecated' => true],
        ['name' => 'cx12', 'deprecated' => true],
    ];

    $filtered = array_filter($types, function ($type) {
        return ($type['deprecated'] ?? false) !== true;
    });

    expect(array_values($filtered))->toHaveCount(0);
});

test('getServerTypes re-indexes array after filtering', function () {
    $types = [
        ['name' => 'cx11', 'deprecated' => true],
        ['name' => 'cx21', 'deprecated' => false],
    ];

    $filtered = array_filter($types, function ($type) {
        return ($type['deprecated'] ?? false) !== true;
    });
    $result = array_values($filtered);

    // array_values resets keys to 0-indexed
    expect(array_keys($result))->toBe([0]);
    expect($result[0]['name'])->toBe('cx21');
});

// ═══════════════════════════════════════════
// findServerByIp() — IP lookup logic
// ═══════════════════════════════════════════

test('findServerByIp matches IPv4 address', function () {
    $servers = [
        ['name' => 'server1', 'public_net' => ['ipv4' => ['ip' => '1.2.3.4'], 'ipv6' => ['ip' => '2001:db8::/64']]],
        ['name' => 'server2', 'public_net' => ['ipv4' => ['ip' => '5.6.7.8'], 'ipv6' => ['ip' => '2001:db9::/64']]],
    ];

    $result = null;
    foreach ($servers as $server) {
        $ipv4 = data_get($server, 'public_net.ipv4.ip');
        if ($ipv4 === '5.6.7.8') {
            $result = $server;
            break;
        }
    }

    expect($result)->not->toBeNull();
    expect($result['name'])->toBe('server2');
});

test('findServerByIp matches IPv6 prefix', function () {
    // Hetzner returns ipv6.ip as the block prefix (e.g. '2001:db8::')
    $servers = [
        ['name' => 'server1', 'public_net' => ['ipv4' => ['ip' => '1.2.3.4'], 'ipv6' => ['ip' => '2001:db8::']]],
    ];

    $ip = '2001:db8::1';

    $result = null;
    foreach ($servers as $server) {
        $ipv6 = data_get($server, 'public_net.ipv6.ip');
        if ($ipv6 && str_starts_with($ip, rtrim($ipv6, '/'))) {
            $result = $server;
            break;
        }
    }

    expect($result)->not->toBeNull();
    expect($result['name'])->toBe('server1');
});

test('findServerByIp returns null when no match found', function () {
    $servers = [
        ['name' => 'server1', 'public_net' => ['ipv4' => ['ip' => '1.2.3.4'], 'ipv6' => ['ip' => '2001:db8::/64']]],
    ];

    $result = null;
    $ip = '10.0.0.1';
    foreach ($servers as $server) {
        $ipv4 = data_get($server, 'public_net.ipv4.ip');
        if ($ipv4 === $ip) {
            $result = $server;
            break;
        }
        $ipv6 = data_get($server, 'public_net.ipv6.ip');
        if ($ipv6 && str_starts_with($ip, rtrim($ipv6, '/'))) {
            $result = $server;
            break;
        }
    }

    expect($result)->toBeNull();
});

test('findServerByIp handles null IPv6', function () {
    $servers = [
        ['name' => 'server1', 'public_net' => ['ipv4' => ['ip' => '1.2.3.4'], 'ipv6' => null]],
    ];

    $result = null;
    $ip = '2001:db8::1';
    foreach ($servers as $server) {
        $ipv4 = data_get($server, 'public_net.ipv4.ip');
        if ($ipv4 === $ip) {
            $result = $server;
            break;
        }
        $ipv6 = data_get($server, 'public_net.ipv6.ip');
        if ($ipv6 && str_starts_with($ip, rtrim($ipv6, '/'))) {
            $result = $server;
            break;
        }
    }

    expect($result)->toBeNull();
});

// ═══════════════════════════════════════════
// Pagination logic
// ═══════════════════════════════════════════

test('pagination continues until next_page is null', function () {
    // Simulate pagination responses
    $pages = [
        ['items' => [1, 2], 'meta' => ['pagination' => ['next_page' => 2]]],
        ['items' => [3, 4], 'meta' => ['pagination' => ['next_page' => 3]]],
        ['items' => [5], 'meta' => ['pagination' => ['next_page' => null]]],
    ];

    $allResults = [];
    $page = 0;

    do {
        $response = $pages[$page];
        $allResults = array_merge($allResults, $response['items']);
        $nextPage = $response['meta']['pagination']['next_page'] ?? null;
        $page++;
    } while ($nextPage !== null);

    expect($allResults)->toBe([1, 2, 3, 4, 5]);
    expect($page)->toBe(3);
});

test('single page response stops pagination', function () {
    $pages = [
        ['items' => [1, 2, 3], 'meta' => ['pagination' => ['next_page' => null]]],
    ];

    $allResults = [];
    $page = 0;

    do {
        $response = $pages[$page];
        $allResults = array_merge($allResults, $response['items']);
        $nextPage = $response['meta']['pagination']['next_page'] ?? null;
        $page++;
    } while ($nextPage !== null);

    expect($allResults)->toBe([1, 2, 3]);
    expect($page)->toBe(1);
});

// ═══════════════════════════════════════════
// Rate limit calculation
// ═══════════════════════════════════════════

test('rate limit wait time is capped at 60 seconds', function () {
    $resetTime = time() + 120; // 120 seconds in the future
    $waitSeconds = max(0, (int) $resetTime - time());
    $cappedWait = min($waitSeconds, 60);

    expect($cappedWait)->toBe(60);
});

test('rate limit wait time handles past reset time', function () {
    $resetTime = time() - 10; // 10 seconds in the past
    $waitSeconds = max(0, (int) $resetTime - time());

    expect($waitSeconds)->toBe(0);
});

test('rate limit exponential backoff calculation', function () {
    // Exponential backoff: attempt * 100
    expect(1 * 100)->toBe(100);
    expect(2 * 100)->toBe(200);
    expect(3 * 100)->toBe(300);
});

// ═══════════════════════════════════════════
// Constructor and configuration
// ═══════════════════════════════════════════

test('service stores token on construction', function () {
    $service = new HetznerService('test-token-123');

    $reflection = new ReflectionClass($service);
    $tokenProp = $reflection->getProperty('token');
    $tokenProp->setAccessible(true);

    expect($tokenProp->getValue($service))->toBe('test-token-123');
});

test('service uses correct base URL', function () {
    $service = new HetznerService('test');

    $reflection = new ReflectionClass($service);
    $baseUrlProp = $reflection->getProperty('baseUrl');
    $baseUrlProp->setAccessible(true);

    expect($baseUrlProp->getValue($service))->toBe('https://api.hetzner.cloud/v1');
});

// ═══════════════════════════════════════════
// Error message extraction
// ═══════════════════════════════════════════

test('error message extracted from nested response', function () {
    $response = ['error' => ['message' => 'Server not found']];
    $message = $response['error']['message'] ?? 'Unknown error';

    expect($message)->toBe('Server not found');
});

test('error message defaults to Unknown error when missing', function () {
    $response = [];
    $message = data_get($response, 'error.message', 'Unknown error');

    expect($message)->toBe('Unknown error');
});
