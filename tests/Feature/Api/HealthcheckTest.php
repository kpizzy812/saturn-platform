<?php

use Illuminate\Support\Facades\Http;

describe('GET /api/health - JSON healthcheck endpoint', function () {
    test('always returns HTTP 200 even when services are degraded', function () {
        // The endpoint must return 200 for liveness probes — consumers check body.status
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
    });

    test('returns correct top-level JSON structure', function () {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'checks' => [
                'database',
                'redis',
                'soketi',
                'queue',
            ],
        ]);
    });

    test('includes soketi in checks', function () {
        Http::fake([
            'http://saturn-realtime:6001/ready' => Http::response('OK', 200),
        ]);

        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        $json = $response->json();
        expect($json['checks'])->toHaveKey('soketi');
    });

    test('reports soketi as ok when /ready returns 200', function () {
        Http::fake([
            'http://saturn-realtime:6001/ready' => Http::response('OK', 200),
        ]);

        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        $response->assertJsonPath('checks.soketi', 'ok');
    });

    test('reports soketi as failing when /ready returns non-200', function () {
        Http::fake([
            'http://saturn-realtime:6001/ready' => Http::response('Service Unavailable', 503),
        ]);

        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        $response->assertJsonPath('checks.soketi', 'failing');
    });

    test('reports soketi as failing when connection is refused', function () {
        Http::fake([
            'http://saturn-realtime:6001/ready' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
        ]);

        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        $response->assertJsonPath('checks.soketi', 'failing');
    });

    test('soketi failure does not mark overall status as degraded', function () {
        // Soketi is not critical — app works without WebSocket
        Http::fake([
            'http://saturn-realtime:6001/ready' => Http::response('Service Unavailable', 503),
        ]);

        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        // Status should still be healthy (database + redis are ok in test env)
        $json = $response->json();
        expect($json['status'])->toBe('healthy');
    });

    test('status is healthy when database and redis are ok', function () {
        Http::fake([
            'http://saturn-realtime:6001/ready' => Http::response('OK', 200),
        ]);

        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        $response->assertJsonPath('status', 'healthy');
        $response->assertJsonPath('checks.database', 'ok');
        $response->assertJsonPath('checks.redis', 'ok');
    });

    test('queue check includes failed count', function () {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'checks' => [
                'queue' => ['status', 'failed'],
            ],
        ]);
    });

    test('endpoint is accessible without authentication', function () {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
    });

    test('versioned endpoint returns same structure', function () {
        Http::fake([
            'http://saturn-realtime:6001/ready' => Http::response('OK', 200),
        ]);

        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'checks' => ['database', 'redis', 'soketi', 'queue'],
        ]);
    });
});
