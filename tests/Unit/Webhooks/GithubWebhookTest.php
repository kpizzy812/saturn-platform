<?php

/**
 * Unit tests for GitHub Webhook Controller (manual method).
 *
 * Tests verify early-exit logic: ping handling, event type routing,
 * branch extraction from refs/heads/*, and responses when no applications exist.
 * No database mocking needed — empty DB produces expected "No applications found" responses.
 */

use App\Http\Controllers\Webhook\Github;
use Illuminate\Http\Request;

// ─── Helper ──────────────────────────────────────────────────────────────────

/**
 * Build an Illuminate Request mimicking a GitHub webhook POST.
 *
 * @param  array<string, string>  $serverVars  HTTP server vars (headers become HTTP_* keys)
 * @param  array<string, mixed>  $payload  JSON body
 */
function makeGithubRequest(array $serverVars = [], array $payload = []): Request
{
    return Request::create(
        '/webhook/github',
        'POST',
        [],
        [],
        [],
        array_merge(['CONTENT_TYPE' => 'application/json'], $serverVars),
        json_encode($payload)
    );
}

// ─── Ping ────────────────────────────────────────────────────────────────────

test('github ping event returns pong', function () {
    $request = makeGithubRequest(
        ['HTTP_X_GITHUB_EVENT' => 'ping'],
        ['zen' => 'test', 'hook_id' => 1]
    );

    $response = (new Github)->manual($request);

    expect($response->getContent())->toBe('pong');
    expect($response->getStatusCode())->toBe(200);
});

// ─── Push: no branch ─────────────────────────────────────────────────────────

test('github push event without ref returns no branch message', function () {
    $request = makeGithubRequest(
        ['HTTP_X_GITHUB_EVENT' => 'push'],
        ['repository' => ['full_name' => 'org/repo'], 'commits' => []]
    );

    $response = (new Github)->manual($request);

    expect($response->getContent())->toContain('Nothing to do. No branch found in the request.');
});

// ─── Push: branch parsed from refs/heads/* ───────────────────────────────────

test('github push event parses branch from refs/heads/main', function () {
    $request = makeGithubRequest(
        ['HTTP_X_GITHUB_EVENT' => 'push'],
        [
            'ref' => 'refs/heads/main',
            'repository' => ['full_name' => 'nonexistent-org/nonexistent-repo'],
            'commits' => [],
        ]
    );

    $response = (new Github)->manual($request);

    // Branch must appear as 'main', not 'refs/heads/main'
    expect($response->getContent())->toContain('main');
    expect($response->getContent())->not->toContain('refs/heads/main');
});

test('github push event parses branch from refs/heads/feature-branch', function () {
    $request = makeGithubRequest(
        ['HTTP_X_GITHUB_EVENT' => 'push'],
        [
            'ref' => 'refs/heads/feature-branch',
            'repository' => ['full_name' => 'nonexistent-org/nonexistent-repo'],
            'commits' => [],
        ]
    );

    $response = (new Github)->manual($request);

    expect($response->getContent())->toContain('feature-branch');
});

// ─── Push: no matching applications ──────────────────────────────────────────

test('github push with valid branch but no applications returns no applications message', function () {
    $request = makeGithubRequest(
        ['HTTP_X_GITHUB_EVENT' => 'push'],
        [
            'ref' => 'refs/heads/main',
            'repository' => ['full_name' => 'nonexistent-org/nonexistent-repo'],
            'commits' => [],
        ]
    );

    $response = (new Github)->manual($request);

    expect($response->getContent())->toContain('Nothing to do. No applications found');
});

// ─── Pull request: no branch ─────────────────────────────────────────────────

test('github pull_request event without branch returns no branch message', function () {
    $request = makeGithubRequest(
        ['HTTP_X_GITHUB_EVENT' => 'pull_request'],
        [
            'action' => 'opened',
            'number' => 42,
            'repository' => ['full_name' => 'org/repo'],
            // intentionally omit pull_request.head.ref
        ]
    );

    $response = (new Github)->manual($request);

    expect($response->getContent())->toContain('Nothing to do. No branch found in the request.');
});

// ─── Pull request: no matching applications ───────────────────────────────────

test('github pull_request with branch but no applications returns no applications message', function () {
    $request = makeGithubRequest(
        ['HTTP_X_GITHUB_EVENT' => 'pull_request'],
        [
            'action' => 'opened',
            'number' => 1,
            'repository' => ['full_name' => 'nonexistent-org/nonexistent-repo'],
            'pull_request' => [
                'head' => ['ref' => 'feature-branch'],
                'base' => ['ref' => 'main'],
                'html_url' => 'https://github.com/org/repo/pull/1',
                'author_association' => 'OWNER',
            ],
        ]
    );

    $response = (new Github)->manual($request);

    expect($response->getContent())->toContain('Nothing to do. No applications found');
});

// ─── Unknown / unsupported event ─────────────────────────────────────────────

test('unknown github event without branch returns nothing to do message', function () {
    $request = makeGithubRequest(
        ['HTTP_X_GITHUB_EVENT' => 'release'],
        ['action' => 'published', 'repository' => ['full_name' => 'org/repo']]
    );

    $response = (new Github)->manual($request);

    expect($response->getContent())->toContain('Nothing to do.');
});

// ─── Controller existence ─────────────────────────────────────────────────────

test('Github controller class exists and has manual method', function () {
    expect(class_exists(Github::class))->toBeTrue();
    expect(method_exists(Github::class, 'manual'))->toBeTrue();
});
