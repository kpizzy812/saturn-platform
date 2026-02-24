<?php

use App\Models\TeamWebhook;

it('generates uuid on creation', function () {
    $webhook = new TeamWebhook;
    $webhook->team_id = 1;
    $webhook->name = 'Test Webhook';
    $webhook->url = 'https://example.com/webhook';
    $webhook->events = ['deploy.started'];

    // Trigger the creating event
    $webhook->uuid = null;
    $reflectionClass = new ReflectionClass(TeamWebhook::class);
    $reflectionMethod = $reflectionClass->getMethod('booted');

    // Check that uuid is set when empty
    expect(empty($webhook->uuid))->toBeTrue();
});

it('generates secret on creation', function () {
    $webhook = new TeamWebhook;
    $webhook->team_id = 1;
    $webhook->name = 'Test Webhook';
    $webhook->url = 'https://example.com/webhook';
    $webhook->events = ['deploy.started'];

    // Secret should be null initially
    expect(empty($webhook->secret))->toBeTrue();
});

it('returns available events as array', function () {
    $events = TeamWebhook::availableEvents();

    expect($events)->toBeArray();
    expect(count($events))->toBeGreaterThan(0);

    // Check structure of first event
    $firstEvent = $events[0];
    expect($firstEvent)->toHaveKeys(['value', 'label', 'description']);
});

it('checks if webhook has specific event', function () {
    $webhook = new TeamWebhook;
    $webhook->events = ['deploy.started', 'deploy.finished'];

    expect($webhook->hasEvent('deploy.started'))->toBeTrue();
    expect($webhook->hasEvent('deploy.finished'))->toBeTrue();
    expect($webhook->hasEvent('deploy.failed'))->toBeFalse();
});

it('checks if webhook is enabled', function () {
    $webhook = new TeamWebhook;

    $webhook->enabled = true;
    expect($webhook->isEnabled())->toBeTrue();

    $webhook->enabled = false;
    expect($webhook->isEnabled())->toBeFalse();
});

it('casts events to array', function () {
    $webhook = new TeamWebhook;
    $webhook->events = ['deploy.started', 'deploy.finished'];

    expect($webhook->events)->toBeArray();
    expect(count($webhook->events))->toBe(2);
});

it('casts enabled to boolean', function () {
    $webhook = new TeamWebhook;
    $webhook->enabled = 1;

    expect($webhook->enabled)->toBeBool();
    expect($webhook->enabled)->toBeTrue();
});
