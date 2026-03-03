<?php

/**
 * Unit tests for DeployController.
 *
 * Tests cover:
 * - Class structure (methods exist)
 * - by_tags() input validation (empty string → 400, no DB needed)
 * - deploy_resource() structure
 */

use App\Http\Controllers\Api\DeployController;

// ─── Class structure ──────────────────────────────────────────────────────────

test('DeployController class exists', function () {
    expect(class_exists(DeployController::class))->toBeTrue();
});

test('DeployController has deployments method', function () {
    expect(method_exists(DeployController::class, 'deployments'))->toBeTrue();
});

test('DeployController has deploy method', function () {
    expect(method_exists(DeployController::class, 'deploy'))->toBeTrue();
});

test('DeployController has cancel_deployment method', function () {
    expect(method_exists(DeployController::class, 'cancel_deployment'))->toBeTrue();
});

test('DeployController has by_tags method', function () {
    expect(method_exists(DeployController::class, 'by_tags'))->toBeTrue();
});

test('DeployController has deploy_resource method', function () {
    expect(method_exists(DeployController::class, 'deploy_resource'))->toBeTrue();
});

test('DeployController has get_application_deployments method', function () {
    expect(method_exists(DeployController::class, 'get_application_deployments'))->toBeTrue();
});

// ─── by_tags() validation (public method, no auth needed) ────────────────────

test('by_tags returns 400 when empty string provided', function () {
    $controller = new DeployController;
    $response = $controller->by_tags('', 1, false);

    expect($response->getStatusCode())->toBe(400);
    $data = json_decode($response->getContent(), true);
    expect($data['message'])->toBe('No TAGs provided.');
});

test('by_tags returns 400 when only commas provided', function () {
    $controller = new DeployController;
    $response = $controller->by_tags(',,,', 1, false);

    expect($response->getStatusCode())->toBe(400);
    $data = json_decode($response->getContent(), true);
    expect($data['message'])->toBe('No TAGs provided.');
});
