<?php

/**
 * Unit tests for GitLab Webhook Controller (manual method).
 *
 * Tests verify:
 * - Rejection of events not in allowed list (push, merge_request)
 * - Missing x-gitlab-token returns "Invalid signature"
 * - push event without ref returns "No branch found"
 * - merge_request event without source_branch returns "No branch found"
 * - push event correctly strips refs/heads/ prefix from branch name
 */

use App\Http\Controllers\Webhook\Gitlab;
use Illuminate\Http\Request;

uses(Tests\TestCase::class);

// ─── Helper ──────────────────────────────────────────────────────────────────

/**
 * Build an Illuminate Request mimicking a GitLab webhook POST.
 *
 * @param  array<string, string>  $serverVars  HTTP server vars
 * @param  array<string, mixed>   $payload     JSON body (object_kind goes here)
 */
function makeGitlabRequest(array $serverVars = [], array $payload = []): Request
{
    return Request::create(
        '/webhook/gitlab',
        'POST',
        [],
        [],
        [],
        array_merge(['CONTENT_TYPE' => 'application/json'], $serverVars),
        json_encode($payload)
    );
}

// ─── Event not allowed ────────────────────────────────────────────────────────

test('gitlab disallowed event returns event not allowed message', function () {
    $request = makeGitlabRequest(
        ['HTTP_X_GITLAB_TOKEN' => 'secret'],
        ['object_kind' => 'issue']
    );

    $response = (new Gitlab)->manual($request);

    $data = json_decode($response->getContent(), true);
    $messages = collect($data)->pluck('message')->implode(' ');
    expect($messages)->toContain('Event not allowed');
});

test('gitlab note event returns event not allowed message', function () {
    $request = makeGitlabRequest(
        ['HTTP_X_GITLAB_TOKEN' => 'secret'],
        ['object_kind' => 'note']
    );

    $response = (new Gitlab)->manual($request);

    $data = json_decode($response->getContent(), true);
    $messages = collect($data)->pluck('message')->implode(' ');
    expect($messages)->toContain('Event not allowed');
});

// ─── Empty token ──────────────────────────────────────────────────────────────

test('gitlab push with empty x-gitlab-token returns invalid signature', function () {
    $request = makeGitlabRequest(
        [],  // no HTTP_X_GITLAB_TOKEN
        ['object_kind' => 'push', 'ref' => 'refs/heads/main']
    );

    $response = (new Gitlab)->manual($request);

    $data = json_decode($response->getContent(), true);
    $messages = collect($data)->pluck('message')->implode(' ');
    expect($messages)->toContain('Invalid signature');
});

test('gitlab merge_request with empty x-gitlab-token returns invalid signature', function () {
    $request = makeGitlabRequest(
        [],
        ['object_kind' => 'merge_request']
    );

    $response = (new Gitlab)->manual($request);

    $data = json_decode($response->getContent(), true);
    $messages = collect($data)->pluck('message')->implode(' ');
    expect($messages)->toContain('Invalid signature');
});

// ─── Push: no branch ──────────────────────────────────────────────────────────

test('gitlab push event without ref returns no branch message', function () {
    $request = makeGitlabRequest(
        ['HTTP_X_GITLAB_TOKEN' => 'secret'],
        [
            'object_kind' => 'push',
            'project' => ['path_with_namespace' => 'org/repo'],
            // intentionally omit 'ref'
        ]
    );

    $response = (new Gitlab)->manual($request);

    $data = json_decode($response->getContent(), true);
    $messages = collect($data)->pluck('message')->implode(' ');
    expect($messages)->toContain('No branch found in the request');
});

// ─── Merge request: no branch ─────────────────────────────────────────────────

test('gitlab merge_request without source_branch returns no branch message', function () {
    $request = makeGitlabRequest(
        ['HTTP_X_GITLAB_TOKEN' => 'secret'],
        [
            'object_kind' => 'merge_request',
            'object_attributes' => [
                'action' => 'open',
                'target_branch' => 'main',
                // intentionally omit 'source_branch'
            ],
            'project' => ['path_with_namespace' => 'org/repo'],
        ]
    );

    $response = (new Gitlab)->manual($request);

    $data = json_decode($response->getContent(), true);
    $messages = collect($data)->pluck('message')->implode(' ');
    expect($messages)->toContain('No branch found in the request');
});

// ─── Push: branch parsed from refs/heads/* ───────────────────────────────────

test('gitlab push event parses branch from refs/heads/feature', function () {
    $request = makeGitlabRequest(
        ['HTTP_X_GITLAB_TOKEN' => 'secret'],
        [
            'object_kind' => 'push',
            'ref' => 'refs/heads/feature',
            'project' => ['path_with_namespace' => 'nonexistent-org/nonexistent-repo'],
        ]
    );

    $response = (new Gitlab)->manual($request);

    $content = $response->getContent();
    // Branch extracted as 'feature', not 'refs/heads/feature'
    expect($content)->toContain('feature');
    expect($content)->not->toContain('refs/heads/feature');
});

test('gitlab push uses plain branch name without refs/heads prefix', function () {
    $request = makeGitlabRequest(
        ['HTTP_X_GITLAB_TOKEN' => 'secret'],
        [
            'object_kind' => 'push',
            'ref' => 'develop',
            'project' => ['path_with_namespace' => 'nonexistent-org/nonexistent-repo'],
        ]
    );

    $response = (new Gitlab)->manual($request);

    expect($response->getContent())->toContain('develop');
});

// ─── Push: no matching applications ──────────────────────────────────────────

test('gitlab push with valid branch but no applications returns no applications message', function () {
    $request = makeGitlabRequest(
        ['HTTP_X_GITLAB_TOKEN' => 'secret'],
        [
            'object_kind' => 'push',
            'ref' => 'refs/heads/main',
            'project' => ['path_with_namespace' => 'nonexistent-org/nonexistent-repo'],
        ]
    );

    $response = (new Gitlab)->manual($request);

    $data = json_decode($response->getContent(), true);
    $messages = collect($data)->pluck('message')->implode(' ');
    expect($messages)->toContain('Nothing to do. No applications found');
});

// ─── Controller existence ─────────────────────────────────────────────────────

test('Gitlab controller class exists and has manual method', function () {
    expect(class_exists(Gitlab::class))->toBeTrue();
    expect(method_exists(Gitlab::class, 'manual'))->toBeTrue();
});
