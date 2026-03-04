<?php

/**
 * Unit tests for CodeReview DTO classes: DiffLine, DiffFile, DiffResult, Violation.
 *
 * Tests cover:
 * - DiffLine: isAdded(), isRemoved() for all types
 * - DiffFile: getAddedLines(), getRemovedLines(), isNew(), isDeleted()
 * - DiffResult: getTotalChanges(), isWithinLimits(), isEmpty(), getFilePaths()
 * - Violation: isCritical(), isDeterministic(), withSuggestion(), toArray(), generateFingerprint()
 */

use App\Services\AI\CodeReview\DTOs\DiffFile;
use App\Services\AI\CodeReview\DTOs\DiffLine;
use App\Services\AI\CodeReview\DTOs\DiffResult;
use App\Services\AI\CodeReview\DTOs\Violation;

// ─── DiffLine ─────────────────────────────────────────────────────────────────

test('DiffLine isAdded returns true for added type', function () {
    $line = new DiffLine('src/app.php', 10, '+$x = 1;', 'added');
    expect($line->isAdded())->toBeTrue();
});

test('DiffLine isAdded returns false for removed type', function () {
    $line = new DiffLine('src/app.php', 10, '-$x = 1;', 'removed');
    expect($line->isAdded())->toBeFalse();
});

test('DiffLine isAdded returns false for context type', function () {
    $line = new DiffLine('src/app.php', 10, ' $x = 1;', 'context');
    expect($line->isAdded())->toBeFalse();
});

test('DiffLine isRemoved returns true for removed type', function () {
    $line = new DiffLine('src/app.php', 5, '-$y = 2;', 'removed');
    expect($line->isRemoved())->toBeTrue();
});

test('DiffLine isRemoved returns false for added type', function () {
    $line = new DiffLine('src/app.php', 5, '+$y = 2;', 'added');
    expect($line->isRemoved())->toBeFalse();
});

test('DiffLine isRemoved returns false for context type', function () {
    $line = new DiffLine('src/app.php', 5, ' $y = 2;', 'context');
    expect($line->isRemoved())->toBeFalse();
});

// ─── DiffFile ─────────────────────────────────────────────────────────────────

function makeDiffFile(string $status, array $lineTypes = []): DiffFile
{
    $lines = collect(array_map(
        fn ($type, $i) => new DiffLine('src/app.php', $i + 1, 'code', $type),
        $lineTypes,
        array_keys($lineTypes)
    ));

    return new DiffFile('src/app.php', $status, null, $lines);
}

test('DiffFile getAddedLines returns only added lines', function () {
    $file = makeDiffFile('modified', ['added', 'removed', 'context', 'added']);
    $added = $file->getAddedLines();
    expect($added)->toHaveCount(2);
    expect($added->every(fn ($l) => $l->isAdded()))->toBeTrue();
});

test('DiffFile getRemovedLines returns only removed lines', function () {
    $file = makeDiffFile('modified', ['added', 'removed', 'context', 'removed']);
    $removed = $file->getRemovedLines();
    expect($removed)->toHaveCount(2);
    expect($removed->every(fn ($l) => $l->isRemoved()))->toBeTrue();
});

test('DiffFile getAddedLines returns empty collection when no added lines', function () {
    $file = makeDiffFile('modified', ['removed', 'context']);
    expect($file->getAddedLines())->toHaveCount(0);
});

test('DiffFile isNew returns true for added status', function () {
    $file = makeDiffFile('added');
    expect($file->isNew())->toBeTrue();
});

test('DiffFile isNew returns false for modified status', function () {
    $file = makeDiffFile('modified');
    expect($file->isNew())->toBeFalse();
});

test('DiffFile isNew returns false for deleted status', function () {
    $file = makeDiffFile('deleted');
    expect($file->isNew())->toBeFalse();
});

test('DiffFile isDeleted returns true for deleted status', function () {
    $file = makeDiffFile('deleted');
    expect($file->isDeleted())->toBeTrue();
});

test('DiffFile isDeleted returns false for added status', function () {
    $file = makeDiffFile('added');
    expect($file->isDeleted())->toBeFalse();
});

test('DiffFile isDeleted returns false for modified status', function () {
    $file = makeDiffFile('modified');
    expect($file->isDeleted())->toBeFalse();
});

// ─── DiffResult ───────────────────────────────────────────────────────────────

function makeDiffResult(int $additions, int $deletions, array $filePaths = []): DiffResult
{
    $files = collect($filePaths)->map(
        fn ($path) => new DiffFile($path, 'modified', null, collect())
    );

    return new DiffResult(
        commitSha: 'abc123',
        baseCommitSha: 'def456',
        files: $files,
        addedLines: collect(),
        totalAdditions: $additions,
        totalDeletions: $deletions,
        rawDiff: '',
    );
}

test('DiffResult getTotalChanges sums additions and deletions', function () {
    $result = makeDiffResult(100, 50);
    expect($result->getTotalChanges())->toBe(150);
});

test('DiffResult getTotalChanges returns zero when both are zero', function () {
    $result = makeDiffResult(0, 0);
    expect($result->getTotalChanges())->toBe(0);
});

test('DiffResult isWithinLimits returns true when total changes below default 3000', function () {
    $result = makeDiffResult(1000, 999);
    expect($result->isWithinLimits())->toBeTrue();
});

test('DiffResult isWithinLimits returns true when exactly at limit', function () {
    $result = makeDiffResult(2000, 1000);
    expect($result->isWithinLimits())->toBeTrue();
});

test('DiffResult isWithinLimits returns false when total changes exceeds default 3000', function () {
    $result = makeDiffResult(2000, 1001);
    expect($result->isWithinLimits())->toBeFalse();
});

test('DiffResult isWithinLimits respects custom limit', function () {
    $result = makeDiffResult(400, 200);
    expect($result->isWithinLimits(500))->toBeFalse();
    expect($result->isWithinLimits(600))->toBeTrue();
});

test('DiffResult isEmpty returns true when no files', function () {
    $result = makeDiffResult(0, 0, []);
    expect($result->isEmpty())->toBeTrue();
});

test('DiffResult isEmpty returns false when files exist', function () {
    $result = makeDiffResult(10, 5, ['src/app.php', 'src/routes.php']);
    expect($result->isEmpty())->toBeFalse();
});

test('DiffResult getFilePaths returns all file paths', function () {
    $result = makeDiffResult(10, 5, ['src/app.php', 'src/routes.php', 'README.md']);
    expect($result->getFilePaths())->toBe(['src/app.php', 'src/routes.php', 'README.md']);
});

test('DiffResult getFilePaths returns empty array for empty result', function () {
    $result = makeDiffResult(0, 0, []);
    expect($result->getFilePaths())->toBe([]);
});

// ─── Violation ────────────────────────────────────────────────────────────────

function makeViolation(
    string $severity = 'high',
    string $source = 'regex',
    float $confidence = 1.0,
    ?string $suggestion = null,
    bool $containsSecret = false
): Violation {
    return new Violation(
        ruleId: 'SEC001',
        source: $source,
        severity: $severity,
        confidence: $confidence,
        file: 'src/app.php',
        line: 42,
        message: 'Potential security issue',
        snippet: '$secret = "abc";',
        suggestion: $suggestion,
        containsSecret: $containsSecret,
    );
}

test('Violation isCritical returns true for critical severity', function () {
    expect(makeViolation('critical')->isCritical())->toBeTrue();
});

test('Violation isCritical returns false for high severity', function () {
    expect(makeViolation('high')->isCritical())->toBeFalse();
});

test('Violation isCritical returns false for low severity', function () {
    expect(makeViolation('low')->isCritical())->toBeFalse();
});

test('Violation isDeterministic returns true for regex source with confidence 1.0', function () {
    expect(makeViolation(source: 'regex', confidence: 1.0)->isDeterministic())->toBeTrue();
});

test('Violation isDeterministic returns true for ast source with confidence 1.0', function () {
    expect(makeViolation(source: 'ast', confidence: 1.0)->isDeterministic())->toBeTrue();
});

test('Violation isDeterministic returns false for regex with low confidence', function () {
    expect(makeViolation(source: 'regex', confidence: 0.9)->isDeterministic())->toBeFalse();
});

test('Violation isDeterministic returns false for llm source even with confidence 1.0', function () {
    expect(makeViolation(source: 'llm', confidence: 1.0)->isDeterministic())->toBeFalse();
});

test('Violation withSuggestion creates new instance with updated suggestion', function () {
    $original = makeViolation(suggestion: null);
    $updated = $original->withSuggestion('Use parameterized queries instead');

    expect($updated->suggestion)->toBe('Use parameterized queries instead');
    expect($original->suggestion)->toBeNull();
    expect($updated->ruleId)->toBe($original->ruleId);
    expect($updated->severity)->toBe($original->severity);
});

test('Violation toArray includes all expected keys', function () {
    $violation = makeViolation('critical', 'regex', 1.0, 'Fix this', false);
    $array = $violation->toArray();

    expect($array)->toHaveKeys([
        'rule_id', 'source', 'severity', 'confidence',
        'file_path', 'line_number', 'message', 'snippet',
        'suggestion', 'contains_secret', 'fingerprint',
    ]);
    expect($array['rule_id'])->toBe('SEC001');
    expect($array['severity'])->toBe('critical');
    expect($array['file_path'])->toBe('src/app.php');
    expect($array['line_number'])->toBe(42);
    expect($array['suggestion'])->toBe('Fix this');
    expect($array['contains_secret'])->toBeFalse();
});

test('Violation generateFingerprint produces consistent sha256 hash', function () {
    $violation = makeViolation();
    $fingerprint1 = $violation->generateFingerprint();
    $fingerprint2 = $violation->generateFingerprint();

    expect($fingerprint1)->toBe($fingerprint2);
    expect(strlen($fingerprint1))->toBe(64); // sha256 hex = 64 chars
});

test('Violation generateFingerprint differs for different rule IDs', function () {
    $v1 = new Violation('SEC001', 'regex', 'high', 1.0, 'src/app.php', 42, 'Issue A');
    $v2 = new Violation('SEC002', 'regex', 'high', 1.0, 'src/app.php', 42, 'Issue A');

    expect($v1->generateFingerprint())->not->toBe($v2->generateFingerprint());
});
