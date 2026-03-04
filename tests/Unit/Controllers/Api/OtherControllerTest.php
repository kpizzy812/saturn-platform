<?php

/**
 * Unit tests for OtherController.
 *
 * Tests cover:
 * - Class structure (methods exist)
 * - version() returns the configured version string (pure config read, no auth)
 */

use App\Http\Controllers\Api\OtherController;
use Illuminate\Http\Request;

// ─── Class structure ──────────────────────────────────────────────────────────

test('OtherController class exists', function () {
    expect(class_exists(OtherController::class))->toBeTrue();
});

test('OtherController has version method', function () {
    expect(method_exists(OtherController::class, 'version'))->toBeTrue();
});

test('OtherController has healthcheck method', function () {
    expect(method_exists(OtherController::class, 'healthcheck'))->toBeTrue();
});

test('OtherController has feedback method', function () {
    expect(method_exists(OtherController::class, 'feedback'))->toBeTrue();
});

test('OtherController has enable_api method', function () {
    expect(method_exists(OtherController::class, 'enable_api'))->toBeTrue();
});

test('OtherController has disable_api method', function () {
    expect(method_exists(OtherController::class, 'disable_api'))->toBeTrue();
});

// ─── version() — pure config read (no auth required) ─────────────────────────

test('version returns the configured version string', function () {
    config(['constants.saturn.version' => '4.0.0-test']);

    $controller = new OtherController;
    $response = $controller->version(Request::create('/api/v1/version', 'GET'));

    expect($response->getContent())->toBe('4.0.0-test');
    expect($response->getStatusCode())->toBe(200);
});

test('version returns different version when config changes', function () {
    config(['constants.saturn.version' => 'v5.0.0-beta.1']);

    $controller = new OtherController;
    $response = $controller->version(Request::create('/api/v1/version', 'GET'));

    expect($response->getContent())->toBe('v5.0.0-beta.1');
});
