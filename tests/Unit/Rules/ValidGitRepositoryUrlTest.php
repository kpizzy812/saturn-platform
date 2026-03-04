<?php

/**
 * Unit tests for ValidGitRepositoryUrl validation rule.
 *
 * Tests cover:
 * - Empty value passes
 * - SSH URLs (git@host:path) — allowed/denied by $allowSSH
 * - HTTPS/HTTP URLs
 * - IP addresses in HTTP URLs — denied unless $allowIP=true
 * - Internal hosts (localhost, 127.0.0.1, *.local) rejected
 * - git:// protocol URLs
 * - Dangerous shell metacharacters and patterns rejected
 * - Unknown protocols rejected
 * - Query params / fragments rejected
 */

use App\Rules\ValidGitRepositoryUrl;

function gitUrlValid(string $value, bool $allowSSH = true, bool $allowIP = false): bool
{
    $failed = false;
    (new ValidGitRepositoryUrl($allowSSH, $allowIP))->validate('url', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

function gitUrlInvalid(string $value, bool $allowSSH = true, bool $allowIP = false): bool
{
    return ! gitUrlValid($value, $allowSSH, $allowIP);
}

// ─── Empty value ──────────────────────────────────────────────────────────────

test('accepts empty value', function () {
    expect(gitUrlValid(''))->toBeTrue();
});

// ─── SSH URLs ─────────────────────────────────────────────────────────────────

test('accepts SSH URL when allowSSH is true', function () {
    expect(gitUrlValid('git@github.com:user/repo.git'))->toBeTrue();
});

test('accepts SSH URL with hyphens in repo name', function () {
    expect(gitUrlValid('git@github.com:user/my-repo.git'))->toBeTrue();
});

test('accepts SSH URL with nested path', function () {
    expect(gitUrlValid('git@gitlab.com:org/team/repo.git'))->toBeTrue();
});

test('rejects SSH URL when allowSSH is false', function () {
    expect(gitUrlInvalid('git@github.com:user/repo.git', allowSSH: false))->toBeTrue();
});

test('rejects malformed SSH URL missing colon separator', function () {
    expect(gitUrlInvalid('git@github.com/user/repo.git'))->toBeTrue();
});

test('rejects SSH URL with spaces', function () {
    expect(gitUrlInvalid('git@github.com:user/my repo.git'))->toBeTrue();
});

// ─── HTTPS URLs ───────────────────────────────────────────────────────────────

test('accepts HTTPS GitHub URL', function () {
    expect(gitUrlValid('https://github.com/user/repo.git'))->toBeTrue();
});

test('accepts HTTPS URL without .git extension', function () {
    expect(gitUrlValid('https://github.com/user/repo'))->toBeTrue();
});

test('accepts HTTP URL', function () {
    expect(gitUrlValid('http://github.com/user/repo.git'))->toBeTrue();
});

test('accepts HTTPS GitLab URL', function () {
    expect(gitUrlValid('https://gitlab.com/org/team/project.git'))->toBeTrue();
});

// ─── IP addresses in HTTPS URLs ───────────────────────────────────────────────

test('rejects HTTPS URL with IP host when allowIP is false', function () {
    expect(gitUrlInvalid('https://192.168.1.100/user/repo.git'))->toBeTrue();
});

test('accepts HTTPS URL with IP host when allowIP is true', function () {
    expect(gitUrlValid('https://192.168.1.100/user/repo.git', allowSSH: true, allowIP: true))->toBeTrue();
});

// ─── Internal hosts ───────────────────────────────────────────────────────────

test('rejects HTTPS URL with localhost host', function () {
    expect(gitUrlInvalid('https://localhost/user/repo.git'))->toBeTrue();
});

test('rejects HTTPS URL with 127.0.0.1 host', function () {
    expect(gitUrlInvalid('https://127.0.0.1/user/repo.git'))->toBeTrue();
});

test('rejects HTTPS URL with 0.0.0.0 host', function () {
    expect(gitUrlInvalid('https://0.0.0.0/user/repo.git'))->toBeTrue();
});

test('rejects HTTPS URL with .local domain', function () {
    expect(gitUrlInvalid('https://myserver.local/user/repo.git'))->toBeTrue();
});

// ─── Query params and fragments ───────────────────────────────────────────────

test('rejects HTTPS URL with query parameters', function () {
    expect(gitUrlInvalid('https://github.com/user/repo.git?ref=main'))->toBeTrue();
});

test('rejects HTTPS URL with fragment', function () {
    expect(gitUrlInvalid('https://github.com/user/repo.git#main'))->toBeTrue();
});

// ─── git:// protocol ──────────────────────────────────────────────────────────

test('accepts git:// URL', function () {
    expect(gitUrlValid('git://github.com/user/repo.git'))->toBeTrue();
});

test('accepts git:// URL with port', function () {
    expect(gitUrlValid('git://github.com:9418/user/repo.git'))->toBeTrue();
});

// ─── Unknown protocols ────────────────────────────────────────────────────────

test('rejects ftp:// protocol', function () {
    expect(gitUrlInvalid('ftp://github.com/user/repo.git'))->toBeTrue();
});

test('rejects ssh:// protocol (must use git@ format)', function () {
    expect(gitUrlInvalid('ssh://github.com/user/repo.git'))->toBeTrue();
});

test('rejects plain domain without protocol', function () {
    expect(gitUrlInvalid('github.com/user/repo.git'))->toBeTrue();
});

// ─── Dangerous shell metacharacters ───────────────────────────────────────────

test('rejects URL with semicolon', function () {
    expect(gitUrlInvalid('https://github.com/user/repo;evil'))->toBeTrue();
});

test('rejects URL with pipe', function () {
    expect(gitUrlInvalid('https://github.com/user/repo|cmd'))->toBeTrue();
});

test('rejects URL with backtick', function () {
    expect(gitUrlInvalid('https://github.com/user/repo`cmd`'))->toBeTrue();
});

test('rejects URL with dollar sign', function () {
    expect(gitUrlInvalid('https://github.com/$user/repo'))->toBeTrue();
});
