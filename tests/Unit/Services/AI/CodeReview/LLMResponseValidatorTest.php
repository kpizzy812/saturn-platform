<?php

/**
 * Unit tests for LLMResponseValidator service.
 *
 * Tests cover:
 * - Valid JSON with violations array → valid
 * - Invalid JSON → invalid with error message
 * - JSON embedded in markdown code block → extracted and validated
 * - Missing violations key → invalid
 * - Each violation must have rule_id and suggestion
 * - Invalid violations are filtered out, valid ones pass
 * - String sanitization: control characters stripped, length truncated
 */

use App\Services\AI\CodeReview\LLMResponseValidator;

function makeValidator(): LLMResponseValidator
{
    return new LLMResponseValidator;
}

// ─── Valid responses ──────────────────────────────────────────────────────────

test('validate returns valid=true for well-formed violations JSON', function () {
    $json = json_encode([
        'violations' => [
            ['rule_id' => 'SEC001', 'suggestion' => 'Use parameterized queries'],
        ],
    ]);
    $result = makeValidator()->validate($json);

    expect($result['valid'])->toBeTrue();
    expect($result['error'])->toBeNull();
    expect($result['data']['violations'])->toHaveCount(1);
});

test('validate returns valid=true for empty violations array', function () {
    $json = json_encode(['violations' => []]);
    $result = makeValidator()->validate($json);

    expect($result['valid'])->toBeTrue();
    expect($result['data']['violations'])->toBe([]);
});

test('validate extracts JSON from markdown code block', function () {
    $json = json_encode([
        'violations' => [
            ['rule_id' => 'SEC002', 'suggestion' => 'Escape output'],
        ],
    ]);
    $response = "Here is my analysis:\n```json\n{$json}\n```";
    $result = makeValidator()->validate($response);

    expect($result['valid'])->toBeTrue();
    expect($result['data']['violations'])->toHaveCount(1);
    expect($result['data']['violations'][0]['rule_id'])->toBe('SEC002');
});

test('validate extracts JSON from markdown block without json tag', function () {
    $json = json_encode(['violations' => []]);
    $response = "```\n{$json}\n```";
    $result = makeValidator()->validate($response);

    expect($result['valid'])->toBeTrue();
});

// ─── Invalid responses ────────────────────────────────────────────────────────

test('validate returns valid=false for non-JSON response', function () {
    $result = makeValidator()->validate('This is just plain text, not JSON.');

    expect($result['valid'])->toBeFalse();
    expect($result['data'])->toBeNull();
    expect($result['error'])->toContain('JSON parse failed');
});

test('validate returns valid=false for malformed JSON', function () {
    $result = makeValidator()->validate('{invalid json here}');

    expect($result['valid'])->toBeFalse();
    expect($result['error'])->not->toBeNull();
});

test('validate returns valid=false when violations key is missing', function () {
    $json = json_encode(['issues' => []]); // wrong key
    $result = makeValidator()->validate($json);

    expect($result['valid'])->toBeFalse();
    expect($result['error'])->toContain('Missing or invalid violations array');
});

test('validate returns valid=false when violations is not an array', function () {
    $json = json_encode(['violations' => 'not-an-array']);
    $result = makeValidator()->validate($json);

    expect($result['valid'])->toBeFalse();
});

test('validate returns valid=false for JSON null value', function () {
    $result = makeValidator()->validate('null');

    expect($result['valid'])->toBeFalse();
    expect($result['error'])->toContain('not an object');
});

// ─── Violation filtering ──────────────────────────────────────────────────────

test('validate filters out violations missing rule_id', function () {
    $json = json_encode([
        'violations' => [
            ['suggestion' => 'No rule_id here'],          // invalid
            ['rule_id' => 'SEC001', 'suggestion' => 'Valid violation'],  // valid
        ],
    ]);
    $result = makeValidator()->validate($json);

    expect($result['valid'])->toBeTrue();
    expect($result['data']['violations'])->toHaveCount(1);
    expect($result['data']['violations'][0]['rule_id'])->toBe('SEC001');
});

test('validate filters out violations missing suggestion', function () {
    $json = json_encode([
        'violations' => [
            ['rule_id' => 'SEC001'],  // missing suggestion
            ['rule_id' => 'SEC002', 'suggestion' => 'Fix the issue'],
        ],
    ]);
    $result = makeValidator()->validate($json);

    expect($result['valid'])->toBeTrue();
    expect($result['data']['violations'])->toHaveCount(1);
});

test('validate filters out non-array violation entries', function () {
    $json = json_encode([
        'violations' => [
            'this is a string, not an array',
            ['rule_id' => 'SEC001', 'suggestion' => 'Valid'],
        ],
    ]);
    $result = makeValidator()->validate($json);

    expect($result['valid'])->toBeTrue();
    expect($result['data']['violations'])->toHaveCount(1);
});

// ─── String sanitization (tested through validate) ────────────────────────────

test('validate sanitizes rule_id to max 20 chars with ellipsis', function () {
    $longRuleId = str_repeat('X', 25); // 25 chars, over 20 limit
    $json = json_encode([
        'violations' => [
            ['rule_id' => $longRuleId, 'suggestion' => 'Fix this'],
        ],
    ]);
    $result = makeValidator()->validate($json);

    expect($result['valid'])->toBeTrue();
    $ruleId = $result['data']['violations'][0]['rule_id'];
    expect(strlen($ruleId))->toBe(23); // 20 + '...'
    expect($ruleId)->toEndWith('...');
});

test('validate sanitizes suggestion to max 2000 chars with ellipsis', function () {
    $longSuggestion = str_repeat('a', 2005); // over 2000 limit
    $json = json_encode([
        'violations' => [
            ['rule_id' => 'SEC001', 'suggestion' => $longSuggestion],
        ],
    ]);
    $result = makeValidator()->validate($json);

    expect($result['valid'])->toBeTrue();
    $suggestion = $result['data']['violations'][0]['suggestion'];
    expect(strlen($suggestion))->toBe(2003); // 2000 + '...'
});

test('validate removes control characters from suggestion', function () {
    $suggestion = "Fix\x00this\x01issue\x7F";
    $json = json_encode([
        'violations' => [
            ['rule_id' => 'SEC001', 'suggestion' => $suggestion],
        ],
    ]);
    $result = makeValidator()->validate($json);

    expect($result['valid'])->toBeTrue();
    $sanitized = $result['data']['violations'][0]['suggestion'];
    expect($sanitized)->not->toContain("\x00");
    expect($sanitized)->not->toContain("\x01");
    expect($sanitized)->toBe('Fixthisissue');
});

test('validate preserves newlines (\\n is allowed) in suggestion', function () {
    $suggestion = "Line one\nLine two\nLine three";
    $json = json_encode([
        'violations' => [
            ['rule_id' => 'SEC001', 'suggestion' => $suggestion],
        ],
    ]);
    $result = makeValidator()->validate($json);

    expect($result['valid'])->toBeTrue();
    expect($result['data']['violations'][0]['suggestion'])->toBe($suggestion);
});
