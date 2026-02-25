<?php

use App\Jobs\SendTeamWebhookJob;
use App\Models\TeamWebhook;
use App\Models\WebhookDelivery;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $webhook = Mockery::mock(TeamWebhook::class)->makePartial();
    $delivery = Mockery::mock(WebhookDelivery::class)->makePartial();

    $job = new SendTeamWebhookJob($webhook, $delivery);

    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe(10);
    expect($job->maxExceptions)->toBe(3);
    expect($job->queue)->toBe('high');

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

test('generates correct HMAC-SHA256 signature', function () {
    $webhook = Mockery::mock(TeamWebhook::class)->makePartial();
    $webhook->secret = 'my-webhook-secret';

    $delivery = Mockery::mock(WebhookDelivery::class)->makePartial();

    $job = new SendTeamWebhookJob($webhook, $delivery);

    $method = new ReflectionMethod($job, 'generateSignature');

    $payload = ['event' => 'test', 'data' => ['key' => 'value']];
    $signature = $method->invoke($job, $payload);

    // Verify it matches expected HMAC
    $expected = hash_hmac('sha256', json_encode($payload), 'my-webhook-secret');
    expect($signature)->toBe($expected);
});

test('signature changes with different secrets', function () {
    $delivery = Mockery::mock(WebhookDelivery::class)->makePartial();
    $payload = ['event' => 'deploy'];

    $webhook1 = Mockery::mock(TeamWebhook::class)->makePartial();
    $webhook1->secret = 'secret-1';
    $job1 = new SendTeamWebhookJob($webhook1, $delivery);
    $method = new ReflectionMethod($job1, 'generateSignature');
    $sig1 = $method->invoke($job1, $payload);

    $webhook2 = Mockery::mock(TeamWebhook::class)->makePartial();
    $webhook2->secret = 'secret-2';
    $job2 = new SendTeamWebhookJob($webhook2, $delivery);
    $sig2 = $method->invoke($job2, $payload);

    expect($sig1)->not->toBe($sig2);
});

test('signature changes with different payloads', function () {
    $webhook = Mockery::mock(TeamWebhook::class)->makePartial();
    $webhook->secret = 'same-secret';

    $delivery = Mockery::mock(WebhookDelivery::class)->makePartial();

    $job = new SendTeamWebhookJob($webhook, $delivery);
    $method = new ReflectionMethod($job, 'generateSignature');

    $sig1 = $method->invoke($job, ['event' => 'deploy']);
    $sig2 = $method->invoke($job, ['event' => 'backup']);

    expect($sig1)->not->toBe($sig2);
});

test('source code sends correct headers', function () {
    $source = file_get_contents((new ReflectionClass(SendTeamWebhookJob::class))->getFileName());

    expect($source)->toContain('X-Saturn-Signature');
    expect($source)->toContain('X-Saturn-Event');
    expect($source)->toContain('X-Saturn-Delivery');
    expect($source)->toContain('Content-Type');
    expect($source)->toContain('application/json');
});

test('source code validates webhook URL for SSRF', function () {
    $source = file_get_contents((new ReflectionClass(SendTeamWebhookJob::class))->getFileName());

    expect($source)->toContain('validateWebhookUrl');
    expect($source)->toContain('URL blocked for security reasons');
});

test('source code uses timeout on HTTP requests', function () {
    $source = file_get_contents((new ReflectionClass(SendTeamWebhookJob::class))->getFileName());

    expect($source)->toContain('Http::timeout(10)');
});

test('source code marks delivery as failed on exception', function () {
    $source = file_get_contents((new ReflectionClass(SendTeamWebhookJob::class))->getFileName());

    expect($source)->toContain('markAsFailed');
    expect($source)->toContain('markAsSuccess');
    expect($source)->toContain('throw $e');
});

test('source code updates last_triggered_at on success', function () {
    $source = file_get_contents((new ReflectionClass(SendTeamWebhookJob::class))->getFileName());

    expect($source)->toContain('last_triggered_at');
    expect($source)->toContain('now()');
});

test('source code measures response time in milliseconds', function () {
    $source = file_get_contents((new ReflectionClass(SendTeamWebhookJob::class))->getFileName());

    expect($source)->toContain('microtime(true)');
    expect($source)->toContain('* 1000');
});
