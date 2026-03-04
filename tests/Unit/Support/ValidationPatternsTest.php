<?php

/**
 * Unit tests for ValidationPatterns support class.
 *
 * Tests cover:
 * - nameRules(): required/nullable, custom min/max lengths, regex rule included
 * - descriptionRules(): nullable by default, required override, maxLength, regex rule
 * - NAME_PATTERN: allows valid characters, rejects dangerous chars
 * - DESCRIPTION_PATTERN: more permissive than name, still rejects dangerous chars
 * - nameMessages(): returns correct message keys
 * - descriptionMessages(): returns correct message keys
 * - combinedMessages(): merges name and description messages
 */

use App\Support\ValidationPatterns;

// ─── nameRules: required/nullable ─────────────────────────────────────────────

test('ValidationPatterns nameRules required=true starts with required', function () {
    $rules = ValidationPatterns::nameRules(required: true);
    expect($rules[0])->toBe('required');
});

test('ValidationPatterns nameRules required=false starts with nullable', function () {
    $rules = ValidationPatterns::nameRules(required: false);
    expect($rules[0])->toBe('nullable');
});

test('ValidationPatterns nameRules default is required', function () {
    $rules = ValidationPatterns::nameRules();
    expect($rules[0])->toBe('required');
});

// ─── nameRules: rule contents ─────────────────────────────────────────────────

test('ValidationPatterns nameRules includes string rule', function () {
    $rules = ValidationPatterns::nameRules();
    expect($rules)->toContain('string');
});

test('ValidationPatterns nameRules includes min rule with default 3', function () {
    $rules = ValidationPatterns::nameRules();
    expect($rules)->toContain('min:3');
});

test('ValidationPatterns nameRules includes max rule with default 255', function () {
    $rules = ValidationPatterns::nameRules();
    expect($rules)->toContain('max:255');
});

test('ValidationPatterns nameRules includes regex rule', function () {
    $rules = ValidationPatterns::nameRules();
    $regexRule = collect($rules)->first(fn ($r) => str_starts_with($r, 'regex:'));
    expect($regexRule)->not->toBeNull();
});

test('ValidationPatterns nameRules uses custom minLength', function () {
    $rules = ValidationPatterns::nameRules(minLength: 5);
    expect($rules)->toContain('min:5');
    expect($rules)->not->toContain('min:3');
});

test('ValidationPatterns nameRules uses custom maxLength', function () {
    $rules = ValidationPatterns::nameRules(maxLength: 100);
    expect($rules)->toContain('max:100');
    expect($rules)->not->toContain('max:255');
});

// ─── descriptionRules: required/nullable ─────────────────────────────────────

test('ValidationPatterns descriptionRules default is nullable', function () {
    $rules = ValidationPatterns::descriptionRules();
    expect($rules[0])->toBe('nullable');
});

test('ValidationPatterns descriptionRules required=true starts with required', function () {
    $rules = ValidationPatterns::descriptionRules(required: true);
    expect($rules[0])->toBe('required');
});

test('ValidationPatterns descriptionRules includes max rule with default 255', function () {
    $rules = ValidationPatterns::descriptionRules();
    expect($rules)->toContain('max:255');
});

test('ValidationPatterns descriptionRules includes regex rule', function () {
    $rules = ValidationPatterns::descriptionRules();
    $regexRule = collect($rules)->first(fn ($r) => str_starts_with($r, 'regex:'));
    expect($regexRule)->not->toBeNull();
});

test('ValidationPatterns descriptionRules uses custom maxLength', function () {
    $rules = ValidationPatterns::descriptionRules(maxLength: 500);
    expect($rules)->toContain('max:500');
});

// ─── NAME_PATTERN: allowed characters ────────────────────────────────────────

test('ValidationPatterns NAME_PATTERN allows alphanumeric characters', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'MyApp123'))->toBe(1);
});

test('ValidationPatterns NAME_PATTERN allows spaces', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'My App'))->toBe(1);
});

test('ValidationPatterns NAME_PATTERN allows dashes', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'my-app'))->toBe(1);
});

test('ValidationPatterns NAME_PATTERN allows underscores', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'my_app'))->toBe(1);
});

test('ValidationPatterns NAME_PATTERN allows dots', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'app.v1'))->toBe(1);
});

test('ValidationPatterns NAME_PATTERN allows slashes', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'org/repo'))->toBe(1);
});

test('ValidationPatterns NAME_PATTERN allows colons', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'namespace:app'))->toBe(1);
});

test('ValidationPatterns NAME_PATTERN allows parentheses', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'app (v2)'))->toBe(1);
});

test('ValidationPatterns NAME_PATTERN rejects less-than sign', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'app<script'))->toBe(0);
});

test('ValidationPatterns NAME_PATTERN rejects greater-than sign', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'app>script'))->toBe(0);
});

test('ValidationPatterns NAME_PATTERN rejects semicolon', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'app;rm -rf'))->toBe(0);
});

test('ValidationPatterns NAME_PATTERN rejects dollar sign', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'app$var'))->toBe(0);
});

test('ValidationPatterns NAME_PATTERN rejects ampersand', function () {
    expect(preg_match(ValidationPatterns::NAME_PATTERN, 'app&other'))->toBe(0);
});

// ─── DESCRIPTION_PATTERN: more permissive ────────────────────────────────────

test('ValidationPatterns DESCRIPTION_PATTERN allows quotes', function () {
    expect(preg_match(ValidationPatterns::DESCRIPTION_PATTERN, "It's a \"test\""))->toBe(1);
});

test('ValidationPatterns DESCRIPTION_PATTERN allows comma and exclamation', function () {
    expect(preg_match(ValidationPatterns::DESCRIPTION_PATTERN, 'Hello, World!'))->toBe(1);
});

test('ValidationPatterns DESCRIPTION_PATTERN allows at sign', function () {
    expect(preg_match(ValidationPatterns::DESCRIPTION_PATTERN, 'admin@example.com'))->toBe(1);
});

test('ValidationPatterns DESCRIPTION_PATTERN allows square brackets', function () {
    expect(preg_match(ValidationPatterns::DESCRIPTION_PATTERN, 'value [default]'))->toBe(1);
});

test('ValidationPatterns DESCRIPTION_PATTERN rejects less-than sign', function () {
    expect(preg_match(ValidationPatterns::DESCRIPTION_PATTERN, '<script>alert(1)</script>'))->toBe(0);
});

test('ValidationPatterns DESCRIPTION_PATTERN rejects semicolons', function () {
    expect(preg_match(ValidationPatterns::DESCRIPTION_PATTERN, 'cmd; rm -rf /'))->toBe(0);
});

// ─── messages ─────────────────────────────────────────────────────────────────

test('ValidationPatterns nameMessages returns name.regex key', function () {
    $messages = ValidationPatterns::nameMessages();
    expect($messages)->toHaveKey('name.regex');
});

test('ValidationPatterns nameMessages returns name.min key', function () {
    $messages = ValidationPatterns::nameMessages();
    expect($messages)->toHaveKey('name.min');
});

test('ValidationPatterns nameMessages returns name.max key', function () {
    $messages = ValidationPatterns::nameMessages();
    expect($messages)->toHaveKey('name.max');
});

test('ValidationPatterns descriptionMessages returns description.regex key', function () {
    $messages = ValidationPatterns::descriptionMessages();
    expect($messages)->toHaveKey('description.regex');
});

test('ValidationPatterns descriptionMessages returns description.max key', function () {
    $messages = ValidationPatterns::descriptionMessages();
    expect($messages)->toHaveKey('description.max');
});

test('ValidationPatterns combinedMessages merges name and description messages', function () {
    $combined = ValidationPatterns::combinedMessages();
    expect($combined)->toHaveKey('name.regex');
    expect($combined)->toHaveKey('name.min');
    expect($combined)->toHaveKey('description.regex');
    expect($combined)->toHaveKey('description.max');
});
