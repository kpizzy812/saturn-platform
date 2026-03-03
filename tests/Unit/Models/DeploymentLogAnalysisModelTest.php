<?php

/**
 * Unit tests for DeploymentLogAnalysis model.
 *
 * Tests cover:
 * - isCompleted() / isFailed() / isAnalyzing() — status-check methods
 * - getSeverityColorAttribute() — badge color per severity level
 * - getCategoryLabelAttribute() — human-readable label for error category
 * - Default attribute values
 */

use App\Models\DeploymentLogAnalysis;

// ─── Default attributes ───────────────────────────────────────────────────────

test('default status is pending', function () {
    $analysis = new DeploymentLogAnalysis;
    expect($analysis->status)->toBe('pending');
});

test('default error_category is unknown', function () {
    $analysis = new DeploymentLogAnalysis;
    expect($analysis->error_category)->toBe('unknown');
});

test('default severity is medium', function () {
    $analysis = new DeploymentLogAnalysis;
    expect($analysis->severity)->toBe('medium');
});

test('default confidence is 0.0', function () {
    $analysis = new DeploymentLogAnalysis;
    expect($analysis->confidence)->toBe(0.0);
});

// ─── isCompleted() ────────────────────────────────────────────────────────────

test('isCompleted returns true when status is completed', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['status' => 'completed']);
    expect($analysis->isCompleted())->toBeTrue();
});

test('isCompleted returns false when status is pending', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['status' => 'pending']);
    expect($analysis->isCompleted())->toBeFalse();
});

test('isCompleted returns false when status is failed', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['status' => 'failed']);
    expect($analysis->isCompleted())->toBeFalse();
});

// ─── isFailed() ───────────────────────────────────────────────────────────────

test('isFailed returns true when status is failed', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['status' => 'failed']);
    expect($analysis->isFailed())->toBeTrue();
});

test('isFailed returns false when status is completed', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['status' => 'completed']);
    expect($analysis->isFailed())->toBeFalse();
});

// ─── isAnalyzing() ────────────────────────────────────────────────────────────

test('isAnalyzing returns true when status is analyzing', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['status' => 'analyzing']);
    expect($analysis->isAnalyzing())->toBeTrue();
});

test('isAnalyzing returns false when status is pending', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['status' => 'pending']);
    expect($analysis->isAnalyzing())->toBeFalse();
});

// ─── getSeverityColorAttribute() ─────────────────────────────────────────────

test('severity_color is red for critical severity', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['severity' => 'critical']);
    expect($analysis->severity_color)->toBe('red');
});

test('severity_color is orange for high severity', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['severity' => 'high']);
    expect($analysis->severity_color)->toBe('orange');
});

test('severity_color is yellow for medium severity', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['severity' => 'medium']);
    expect($analysis->severity_color)->toBe('yellow');
});

test('severity_color is green for low severity', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['severity' => 'low']);
    expect($analysis->severity_color)->toBe('green');
});

test('severity_color is gray for unknown severity', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['severity' => 'none']);
    expect($analysis->severity_color)->toBe('gray');
});

// ─── getCategoryLabelAttribute() ─────────────────────────────────────────────

test('category_label is Dockerfile Issue for dockerfile category', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['error_category' => 'dockerfile']);
    expect($analysis->category_label)->toBe('Dockerfile Issue');
});

test('category_label is Dependency Error for dependency category', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['error_category' => 'dependency']);
    expect($analysis->category_label)->toBe('Dependency Error');
});

test('category_label is Build Failure for build category', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['error_category' => 'build']);
    expect($analysis->category_label)->toBe('Build Failure');
});

test('category_label is Runtime Error for runtime category', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['error_category' => 'runtime']);
    expect($analysis->category_label)->toBe('Runtime Error');
});

test('category_label is Network Issue for network category', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['error_category' => 'network']);
    expect($analysis->category_label)->toBe('Network Issue');
});

test('category_label is Resource Limit for resource category', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['error_category' => 'resource']);
    expect($analysis->category_label)->toBe('Resource Limit');
});

test('category_label is Configuration Error for config category', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['error_category' => 'config']);
    expect($analysis->category_label)->toBe('Configuration Error');
});

test('category_label is Unknown for unrecognized category', function () {
    $analysis = new DeploymentLogAnalysis;
    $analysis->setRawAttributes(['error_category' => 'unknown']);
    expect($analysis->category_label)->toBe('Unknown');
});
