<?php

/**
 * Unit tests for ValidGitBranch validation rule.
 *
 * Tests cover:
 * - Valid branch names pass
 * - Dangerous shell metacharacters are rejected
 * - Invalid patterns (.., //, @{) are rejected
 * - Leading/trailing / or . are rejected
 * - HEAD is rejected
 * - .lock suffix is rejected
 */

use App\Rules\ValidGitBranch;

function gitBranchValid(string $value): bool
{
    $failed = false;
    (new ValidGitBranch)->validate('branch', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

function gitBranchInvalid(string $value): bool
{
    return ! gitBranchValid($value);
}

// ─── Valid branch names ───────────────────────────────────────────────────────

test('accepts simple branch name', function () {
    expect(gitBranchValid('main'))->toBeTrue();
});

test('accepts branch name with hyphens', function () {
    expect(gitBranchValid('feature-my-branch'))->toBeTrue();
});

test('accepts branch name with slashes for namespacing', function () {
    expect(gitBranchValid('feature/my-feature'))->toBeTrue();
});

test('accepts branch with version number', function () {
    expect(gitBranchValid('release/v1.2.3'))->toBeTrue();
});

test('accepts empty value without failure', function () {
    expect(gitBranchValid(''))->toBeTrue();
});

test('accepts branch with underscores', function () {
    expect(gitBranchValid('my_branch_name'))->toBeTrue();
});

// ─── Dangerous characters ─────────────────────────────────────────────────────

test('rejects branch with semicolon', function () {
    expect(gitBranchInvalid('branch;evil'))->toBeTrue();
});

test('rejects branch with pipe', function () {
    expect(gitBranchInvalid('branch|evil'))->toBeTrue();
});

test('rejects branch with ampersand', function () {
    expect(gitBranchInvalid('branch&evil'))->toBeTrue();
});

test('rejects branch with backtick', function () {
    expect(gitBranchInvalid('branch`cmd`'))->toBeTrue();
});

test('rejects branch with dollar sign', function () {
    expect(gitBranchInvalid('$branch'))->toBeTrue();
});

test('rejects branch with space', function () {
    expect(gitBranchInvalid('my branch'))->toBeTrue();
});

// ─── Invalid patterns ────────────────────────────────────────────────────────

test('rejects branch with double dot', function () {
    expect(gitBranchInvalid('branch..invalid'))->toBeTrue();
});

test('rejects branch with double slash', function () {
    expect(gitBranchInvalid('branch//invalid'))->toBeTrue();
});

test('rejects branch with @{ sequence', function () {
    expect(gitBranchInvalid('branch@{upstream}'))->toBeTrue();
});

// ─── Leading/trailing slashes and dots ────────────────────────────────────────

test('rejects branch starting with slash', function () {
    expect(gitBranchInvalid('/branch'))->toBeTrue();
});

test('rejects branch ending with slash', function () {
    expect(gitBranchInvalid('branch/'))->toBeTrue();
});

test('rejects branch starting with dot', function () {
    expect(gitBranchInvalid('.hidden-branch'))->toBeTrue();
});

test('rejects branch ending with dot', function () {
    expect(gitBranchInvalid('branch.'))->toBeTrue();
});

// ─── Git reserved names ───────────────────────────────────────────────────────

test('rejects HEAD as branch name', function () {
    expect(gitBranchInvalid('HEAD'))->toBeTrue();
});

test('rejects branch ending with .lock', function () {
    expect(gitBranchInvalid('my-branch.lock'))->toBeTrue();
});
