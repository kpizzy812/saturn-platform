<?php

/**
 * Unit tests for Alert API FormRequest validation rules.
 *
 * Tests cover:
 * - StoreAlertRequest: required fields, valid/invalid metric/condition/threshold/duration/channels
 * - UpdateAlertRequest: all fields are optional (sometimes), invalid values still fail
 */

use App\Http\Requests\Api\Alert\StoreAlertRequest;
use App\Http\Requests\Api\Alert\UpdateAlertRequest;
use Illuminate\Support\Facades\Validator;

function validateStoreAlert(array $data): \Illuminate\Contracts\Validation\Validator
{
    return Validator::make($data, (new StoreAlertRequest)->rules());
}

function validateUpdateAlert(array $data): \Illuminate\Contracts\Validation\Validator
{
    return Validator::make($data, (new UpdateAlertRequest)->rules());
}

// ─── StoreAlertRequest: valid data ────────────────────────────────────────────

test('StoreAlertRequest valid data passes validation', function () {
    $validator = validateStoreAlert([
        'name' => 'High CPU Alert',
        'metric' => 'cpu',
        'condition' => '>',
        'threshold' => 80,
        'duration' => 5,
    ]);

    expect($validator->passes())->toBeTrue();
});

test('StoreAlertRequest valid data with all optional fields passes', function () {
    $validator = validateStoreAlert([
        'name' => 'Memory Alert',
        'metric' => 'memory',
        'condition' => '>',
        'threshold' => 90.5,
        'duration' => 10,
        'enabled' => true,
        'channels' => ['email', 'slack'],
    ]);

    expect($validator->passes())->toBeTrue();
});

// ─── StoreAlertRequest: required fields ──────────────────────────────────────

test('StoreAlertRequest missing name fails', function () {
    $validator = validateStoreAlert([
        'metric' => 'cpu', 'condition' => '>', 'threshold' => 80, 'duration' => 5,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});

test('StoreAlertRequest missing metric fails', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'condition' => '>', 'threshold' => 80, 'duration' => 5,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('metric'))->toBeTrue();
});

test('StoreAlertRequest missing condition fails', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'threshold' => 80, 'duration' => 5,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('condition'))->toBeTrue();
});

test('StoreAlertRequest missing threshold fails', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'duration' => 5,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('threshold'))->toBeTrue();
});

test('StoreAlertRequest missing duration fails', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'threshold' => 80,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('duration'))->toBeTrue();
});

// ─── StoreAlertRequest: metric values ────────────────────────────────────────

test('StoreAlertRequest accepts all valid metric values', function () {
    foreach (['cpu', 'memory', 'disk', 'error_rate', 'response_time'] as $metric) {
        $validator = validateStoreAlert([
            'name' => 'Alert', 'metric' => $metric, 'condition' => '>', 'threshold' => 50, 'duration' => 5,
        ]);
        expect($validator->passes())->toBeTrue("metric '{$metric}' should be valid");
    }
});

test('StoreAlertRequest rejects invalid metric value', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'bandwidth', 'condition' => '>', 'threshold' => 50, 'duration' => 5,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('metric'))->toBeTrue();
});

// ─── StoreAlertRequest: condition values ─────────────────────────────────────

test('StoreAlertRequest accepts all valid condition values', function () {
    foreach (['>', '<', '='] as $condition) {
        $validator = validateStoreAlert([
            'name' => 'Alert', 'metric' => 'cpu', 'condition' => $condition, 'threshold' => 50, 'duration' => 5,
        ]);
        expect($validator->passes())->toBeTrue("condition '{$condition}' should be valid");
    }
});

test('StoreAlertRequest rejects invalid condition value', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>=', 'threshold' => 50, 'duration' => 5,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('condition'))->toBeTrue();
});

// ─── StoreAlertRequest: threshold values ─────────────────────────────────────

test('StoreAlertRequest accepts zero threshold', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'threshold' => 0, 'duration' => 5,
    ]);
    expect($validator->passes())->toBeTrue();
});

test('StoreAlertRequest rejects negative threshold', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'threshold' => -1, 'duration' => 5,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('threshold'))->toBeTrue();
});

test('StoreAlertRequest rejects non-numeric threshold', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'threshold' => 'high', 'duration' => 5,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('threshold'))->toBeTrue();
});

// ─── StoreAlertRequest: duration values ──────────────────────────────────────

test('StoreAlertRequest accepts duration of 1', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'threshold' => 80, 'duration' => 1,
    ]);
    expect($validator->passes())->toBeTrue();
});

test('StoreAlertRequest accepts duration of 1440', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'threshold' => 80, 'duration' => 1440,
    ]);
    expect($validator->passes())->toBeTrue();
});

test('StoreAlertRequest rejects duration of 0', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'threshold' => 80, 'duration' => 0,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('duration'))->toBeTrue();
});

test('StoreAlertRequest rejects duration exceeding 1440', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'threshold' => 80, 'duration' => 1441,
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('duration'))->toBeTrue();
});

// ─── StoreAlertRequest: channels ─────────────────────────────────────────────

test('StoreAlertRequest accepts valid channel types', function () {
    foreach (['email', 'slack', 'discord', 'telegram', 'pagerduty', 'webhook'] as $channel) {
        $validator = validateStoreAlert([
            'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'threshold' => 80, 'duration' => 5,
            'channels' => [$channel],
        ]);
        expect($validator->passes())->toBeTrue("channel '{$channel}' should be valid");
    }
});

test('StoreAlertRequest rejects invalid channel type', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'threshold' => 80, 'duration' => 5,
        'channels' => ['sms'],
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('channels.0'))->toBeTrue();
});

test('StoreAlertRequest accepts null channels', function () {
    $validator = validateStoreAlert([
        'name' => 'Alert', 'metric' => 'cpu', 'condition' => '>', 'threshold' => 80, 'duration' => 5,
        'channels' => null,
    ]);

    expect($validator->passes())->toBeTrue();
});

// ─── UpdateAlertRequest: optional fields ─────────────────────────────────────

test('UpdateAlertRequest passes with empty data (all fields sometimes)', function () {
    $validator = validateUpdateAlert([]);
    expect($validator->passes())->toBeTrue();
});

test('UpdateAlertRequest valid partial update passes', function () {
    $validator = validateUpdateAlert(['enabled' => false]);
    expect($validator->passes())->toBeTrue();
});

test('UpdateAlertRequest rejects invalid metric in partial update', function () {
    $validator = validateUpdateAlert(['metric' => 'invalid_metric']);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('metric'))->toBeTrue();
});

test('UpdateAlertRequest rejects duration over 1440 in partial update', function () {
    $validator = validateUpdateAlert(['duration' => 9999]);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('duration'))->toBeTrue();
});

test('UpdateAlertRequest name max length is 255', function () {
    $validator = validateUpdateAlert(['name' => str_repeat('a', 255)]);
    expect($validator->passes())->toBeTrue();

    $validator = validateUpdateAlert(['name' => str_repeat('a', 256)]);
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('name'))->toBeTrue();
});
