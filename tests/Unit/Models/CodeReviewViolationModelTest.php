<?php

/**
 * Unit tests for CodeReviewViolation model.
 *
 * Tests cover:
 * - Severity and source constants
 * - isDeterministic() for each source type
 * - shouldBlock() — always false in MVP report-only mode
 * - getSeverityColorAttribute() — badge color per severity
 * - getRuleCategoryAttribute() — category derived from rule_id prefix
 * - getRuleDescriptionAttribute() — human-readable description from rule_id
 * - getLocationAttribute() — "path:line" or just "path"
 */

use App\Models\CodeReviewViolation;

// ─── Constants ────────────────────────────────────────────────────────────────

test('SEVERITY_CRITICAL constant is critical', function () {
    expect(CodeReviewViolation::SEVERITY_CRITICAL)->toBe('critical');
});

test('SEVERITY_HIGH constant is high', function () {
    expect(CodeReviewViolation::SEVERITY_HIGH)->toBe('high');
});

test('SEVERITY_MEDIUM constant is medium', function () {
    expect(CodeReviewViolation::SEVERITY_MEDIUM)->toBe('medium');
});

test('SEVERITY_LOW constant is low', function () {
    expect(CodeReviewViolation::SEVERITY_LOW)->toBe('low');
});

test('SOURCE_REGEX constant is regex', function () {
    expect(CodeReviewViolation::SOURCE_REGEX)->toBe('regex');
});

test('SOURCE_AST constant is ast', function () {
    expect(CodeReviewViolation::SOURCE_AST)->toBe('ast');
});

test('SOURCE_LLM constant is llm', function () {
    expect(CodeReviewViolation::SOURCE_LLM)->toBe('llm');
});

// ─── isDeterministic() ────────────────────────────────────────────────────────

test('isDeterministic returns true for regex source', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['source' => 'regex']);
    expect($v->isDeterministic())->toBeTrue();
});

test('isDeterministic returns true for ast source', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['source' => 'ast']);
    expect($v->isDeterministic())->toBeTrue();
});

test('isDeterministic returns false for llm source', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['source' => 'llm']);
    expect($v->isDeterministic())->toBeFalse();
});

// ─── shouldBlock() — always false (MVP report-only mode) ─────────────────────

test('shouldBlock always returns false for critical violation', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['severity' => 'critical', 'source' => 'regex']);
    expect($v->shouldBlock())->toBeFalse();
});

test('shouldBlock always returns false for high violation', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['severity' => 'high', 'source' => 'ast']);
    expect($v->shouldBlock())->toBeFalse();
});

// ─── getSeverityColorAttribute() ─────────────────────────────────────────────

test('severity_color is red for critical', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['severity' => 'critical']);
    expect($v->severity_color)->toBe('red');
});

test('severity_color is orange for high', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['severity' => 'high']);
    expect($v->severity_color)->toBe('orange');
});

test('severity_color is yellow for medium', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['severity' => 'medium']);
    expect($v->severity_color)->toBe('yellow');
});

test('severity_color is green for low', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['severity' => 'low']);
    expect($v->severity_color)->toBe('green');
});

test('severity_color is gray for unknown severity', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['severity' => 'info']);
    expect($v->severity_color)->toBe('gray');
});

// ─── getRuleCategoryAttribute() ──────────────────────────────────────────────

test('rule_category is Security for SEC-prefixed rule_id', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['rule_id' => 'SEC001']);
    expect($v->rule_category)->toBe('Security');
});

test('rule_category is Performance for PERF-prefixed rule_id', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['rule_id' => 'PERF001']);
    expect($v->rule_category)->toBe('Performance');
});

test('rule_category is Quality for QUAL-prefixed rule_id', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['rule_id' => 'QUAL002']);
    expect($v->rule_category)->toBe('Quality');
});

test('rule_category is Other for unknown rule_id prefix', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['rule_id' => 'MISC001']);
    expect($v->rule_category)->toBe('Other');
});

// ─── getRuleDescriptionAttribute() ───────────────────────────────────────────

test('rule_description is Hardcoded API Key or Secret for SEC001', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['rule_id' => 'SEC001']);
    expect($v->rule_description)->toBe('Hardcoded API Key or Secret');
});

test('rule_description is Hardcoded Password for SEC002', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['rule_id' => 'SEC002']);
    expect($v->rule_description)->toBe('Hardcoded Password');
});

test('rule_description is Shell Command Execution for SEC004', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['rule_id' => 'SEC004']);
    expect($v->rule_description)->toBe('Shell Command Execution');
});

test('rule_description is Unknown Rule for unrecognized rule_id', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['rule_id' => 'CUSTOM999']);
    expect($v->rule_description)->toBe('Unknown Rule');
});

// ─── getLocationAttribute() ───────────────────────────────────────────────────

test('location includes line number when line_number is set', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['file_path' => 'app/Models/User.php', 'line_number' => 42]);
    expect($v->location)->toBe('app/Models/User.php:42');
});

test('location is just file_path when line_number is null', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['file_path' => 'app/Services/HetznerService.php', 'line_number' => null]);
    expect($v->location)->toBe('app/Services/HetznerService.php');
});

test('location is just file_path when line_number is zero', function () {
    $v = new CodeReviewViolation;
    $v->setRawAttributes(['file_path' => 'app/Jobs/SomeJob.php', 'line_number' => 0]);
    expect($v->location)->toBe('app/Jobs/SomeJob.php');
});
