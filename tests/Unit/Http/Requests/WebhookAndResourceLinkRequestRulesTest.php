<?php

/**
 * Unit tests for TeamWebhook and ResourceLink API FormRequest validation rules.
 *
 * Tests cover:
 * - StoreTeamWebhookRequest: required fields, URL validation, valid/invalid event values
 * - UpdateTeamWebhookRequest: all fields optional (sometimes), same event validation
 * - StoreResourceLinkRequest: required fields, valid/invalid target_type
 * - UpdateResourceLinkRequest: all optional, boolean fields
 */

use App\Http\Requests\Api\ResourceLink\StoreResourceLinkRequest;
use App\Http\Requests\Api\ResourceLink\UpdateResourceLinkRequest;
use App\Http\Requests\Api\TeamWebhook\StoreTeamWebhookRequest;
use App\Http\Requests\Api\TeamWebhook\UpdateTeamWebhookRequest;
use Illuminate\Support\Facades\Validator;

// ─── StoreTeamWebhookRequest: valid data ──────────────────────────────────────

test('StoreTeamWebhookRequest valid data passes', function () {
    $validator = Validator::make(
        [
            'name' => 'Deploy Notifier',
            'url' => 'https://api.example.com/webhook',
            'events' => ['deploy.started', 'deploy.finished'],
        ],
        (new StoreTeamWebhookRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

// ─── StoreTeamWebhookRequest: required fields ────────────────────────────────

test('StoreTeamWebhookRequest missing name fails', function () {
    $validator = Validator::make(
        ['url' => 'https://example.com/hook', 'events' => ['deploy.started']],
        (new StoreTeamWebhookRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('StoreTeamWebhookRequest missing URL fails', function () {
    $validator = Validator::make(
        ['name' => 'Hook', 'events' => ['deploy.started']],
        (new StoreTeamWebhookRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('url'))->toBeTrue();
});

test('StoreTeamWebhookRequest missing events fails', function () {
    $validator = Validator::make(
        ['name' => 'Hook', 'url' => 'https://example.com/hook'],
        (new StoreTeamWebhookRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('events'))->toBeTrue();
});

test('StoreTeamWebhookRequest empty events array fails', function () {
    $validator = Validator::make(
        ['name' => 'Hook', 'url' => 'https://example.com/hook', 'events' => []],
        (new StoreTeamWebhookRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('events'))->toBeTrue();
});

// ─── StoreTeamWebhookRequest: URL validation ─────────────────────────────────

test('StoreTeamWebhookRequest invalid URL fails', function () {
    $validator = Validator::make(
        ['name' => 'Hook', 'url' => 'not-a-url', 'events' => ['deploy.started']],
        (new StoreTeamWebhookRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('url'))->toBeTrue();
});

test('StoreTeamWebhookRequest URL max length is 2048', function () {
    $validator = Validator::make(
        ['name' => 'Hook', 'url' => 'https://example.com/'.str_repeat('a', 2048), 'events' => ['deploy.started']],
        (new StoreTeamWebhookRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('url'))->toBeTrue();
});

// ─── StoreTeamWebhookRequest: event validation ───────────────────────────────

test('StoreTeamWebhookRequest accepts all valid event types', function () {
    $validEvents = [
        'deploy.started', 'deploy.finished', 'deploy.failed',
        'service.created', 'service.deleted', 'database.backup',
        'server.reachable',
    ];

    foreach ($validEvents as $event) {
        $validator = Validator::make(
            ['name' => 'Hook', 'url' => 'https://example.com/hook', 'events' => [$event]],
            (new StoreTeamWebhookRequest)->rules()
        );
        expect($validator->passes())->toBeTrue("event '{$event}' should be valid");
    }
});

test('StoreTeamWebhookRequest rejects invalid event type', function () {
    $validator = Validator::make(
        ['name' => 'Hook', 'url' => 'https://example.com/hook', 'events' => ['invalid.event']],
        (new StoreTeamWebhookRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('events.0'))->toBeTrue();
});

// ─── UpdateTeamWebhookRequest: optional fields ───────────────────────────────

test('UpdateTeamWebhookRequest empty data passes', function () {
    $validator = Validator::make([], (new UpdateTeamWebhookRequest)->rules());
    expect($validator->passes())->toBeTrue();
});

test('UpdateTeamWebhookRequest valid partial update passes', function () {
    $validator = Validator::make(
        ['enabled' => false],
        (new UpdateTeamWebhookRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

test('UpdateTeamWebhookRequest rejects invalid URL in update', function () {
    $validator = Validator::make(
        ['url' => 'not-a-url'],
        (new UpdateTeamWebhookRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('url'))->toBeTrue();
});

test('UpdateTeamWebhookRequest rejects invalid event in update', function () {
    $validator = Validator::make(
        ['events' => ['fake.event']],
        (new UpdateTeamWebhookRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('events.0'))->toBeTrue();
});

// ─── StoreResourceLinkRequest: valid data ────────────────────────────────────

test('StoreResourceLinkRequest valid data passes', function () {
    $validator = Validator::make(
        [
            'source_id' => 1,
            'target_type' => 'postgresql',
            'target_id' => 42,
        ],
        (new StoreResourceLinkRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

// ─── StoreResourceLinkRequest: required fields ───────────────────────────────

test('StoreResourceLinkRequest missing source_id fails', function () {
    $validator = Validator::make(
        ['target_type' => 'postgresql', 'target_id' => 1],
        (new StoreResourceLinkRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('source_id'))->toBeTrue();
});

test('StoreResourceLinkRequest missing target_type fails', function () {
    $validator = Validator::make(
        ['source_id' => 1, 'target_id' => 1],
        (new StoreResourceLinkRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('target_type'))->toBeTrue();
});

test('StoreResourceLinkRequest missing target_id fails', function () {
    $validator = Validator::make(
        ['source_id' => 1, 'target_type' => 'redis'],
        (new StoreResourceLinkRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('target_id'))->toBeTrue();
});

// ─── StoreResourceLinkRequest: target_type values ────────────────────────────

test('StoreResourceLinkRequest accepts all valid target types', function () {
    $validTypes = ['postgresql', 'mysql', 'mariadb', 'redis', 'keydb', 'dragonfly', 'mongodb', 'clickhouse', 'application'];

    foreach ($validTypes as $type) {
        $validator = Validator::make(
            ['source_id' => 1, 'target_type' => $type, 'target_id' => 1],
            (new StoreResourceLinkRequest)->rules()
        );
        expect($validator->passes())->toBeTrue("target_type '{$type}' should be valid");
    }
});

test('StoreResourceLinkRequest rejects invalid target type', function () {
    $validator = Validator::make(
        ['source_id' => 1, 'target_type' => 'mssql', 'target_id' => 1],
        (new StoreResourceLinkRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('target_type'))->toBeTrue();
});

// ─── StoreResourceLinkRequest: optional fields ───────────────────────────────

test('StoreResourceLinkRequest with optional inject_as passes', function () {
    $validator = Validator::make(
        [
            'source_id' => 1,
            'target_type' => 'redis',
            'target_id' => 5,
            'inject_as' => 'REDIS_URL',
            'auto_inject' => true,
            'use_external_url' => false,
        ],
        (new StoreResourceLinkRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});

// ─── UpdateResourceLinkRequest: all optional ─────────────────────────────────

test('UpdateResourceLinkRequest empty data passes', function () {
    $validator = Validator::make([], (new UpdateResourceLinkRequest)->rules());
    expect($validator->passes())->toBeTrue();
});

test('UpdateResourceLinkRequest inject_as max 255 chars', function () {
    $validator = Validator::make(
        ['inject_as' => str_repeat('A', 256)],
        (new UpdateResourceLinkRequest)->rules()
    );
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('inject_as'))->toBeTrue();
});

test('UpdateResourceLinkRequest boolean fields accept true and false', function () {
    $validator = Validator::make(
        ['auto_inject' => true, 'use_external_url' => false],
        (new UpdateResourceLinkRequest)->rules()
    );
    expect($validator->passes())->toBeTrue();
});
