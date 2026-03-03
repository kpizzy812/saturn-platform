<?php

/**
 * Unit tests for Bitbucket Webhook Controller (manual method).
 *
 * Tests verify:
 * - Disallowed event keys return "Event not handled"
 * - repo:push without branch returns error
 * - repo:push with valid branch reaches application query (no apps found)
 * - pullrequest:created, pullrequest:rejected, pullrequest:fulfilled are handled
 */

use App\Http\Controllers\Webhook\Bitbucket;
use Illuminate\Http\Request;

uses(Tests\TestCase::class);

// ─── Helper ──────────────────────────────────────────────────────────────────

/**
 * Build an Illuminate Request mimicking a Bitbucket webhook POST.
 *
 * @param  string                $eventKey  Value for x-event-key header
 * @param  array<string, mixed>  $payload   JSON body
 */
function makeBitbucketRequest(string $eventKey, array $payload = []): Request
{
    return Request::create(
        '/webhook/bitbucket',
        'POST',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_EVENT_KEY' => $eventKey,
        ],
        json_encode($payload)
    );
}

// ─── Disallowed event ─────────────────────────────────────────────────────────

test('bitbucket disallowed event returns event not handled message', function () {
    $response = (new Bitbucket)->manual(makeBitbucketRequest('issue:created'));

    $data = json_decode($response->getContent(), true);
    expect($data['message'])->toContain('Event not handled');
});

test('bitbucket empty event key returns event not handled message', function () {
    $response = (new Bitbucket)->manual(makeBitbucketRequest(''));

    $data = json_decode($response->getContent(), true);
    expect($data['message'])->toContain('Event not handled');
});

test('bitbucket repo:deleted event returns event not handled message', function () {
    $response = (new Bitbucket)->manual(makeBitbucketRequest('repo:deleted'));

    $data = json_decode($response->getContent(), true);
    expect($data['message'])->toContain('Event not handled');
});

// ─── repo:push without branch ─────────────────────────────────────────────────

test('bitbucket repo:push without branch name returns no branch message', function () {
    $response = (new Bitbucket)->manual(makeBitbucketRequest('repo:push', [
        'push' => ['changes' => [['new' => null]]],
        'repository' => ['full_name' => 'org/repo'],
    ]));

    $data = json_decode($response->getContent(), true);
    expect($data['message'])->toContain('No branch found in the request');
});

test('bitbucket repo:push with empty changes returns no branch message', function () {
    $response = (new Bitbucket)->manual(makeBitbucketRequest('repo:push', [
        'push' => ['changes' => []],
        'repository' => ['full_name' => 'org/repo'],
    ]));

    $data = json_decode($response->getContent(), true);
    expect($data['message'])->toContain('No branch found in the request');
});

// ─── repo:push with valid branch ──────────────────────────────────────────────

test('bitbucket repo:push with valid branch proceeds to application query', function () {
    $response = (new Bitbucket)->manual(makeBitbucketRequest('repo:push', [
        'push' => [
            'changes' => [[
                'new' => ['name' => 'main', 'target' => ['hash' => 'abc123']],
            ]],
        ],
        'repository' => ['full_name' => 'nonexistent-org/nonexistent-repo'],
    ]));

    $data = json_decode($response->getContent(), true);
    // Should not be "no branch", should be "no applications found"
    expect($data['message'])->not->toContain('No branch found in the request');
    expect($data['message'])->toContain('Nothing to do. No applications found');
});

test('bitbucket repo:push parses correct branch name from payload', function () {
    $response = (new Bitbucket)->manual(makeBitbucketRequest('repo:push', [
        'push' => [
            'changes' => [[
                'new' => ['name' => 'release-v2', 'target' => ['hash' => 'def456']],
            ]],
        ],
        'repository' => ['full_name' => 'nonexistent-org/nonexistent-repo'],
    ]));

    expect($response->getContent())->toContain('release-v2');
});

// ─── pullrequest events ───────────────────────────────────────────────────────

test('bitbucket pullrequest:created event is handled and reaches application query', function () {
    $response = (new Bitbucket)->manual(makeBitbucketRequest('pullrequest:created', [
        'pullrequest' => [
            'id' => 1,
            'destination' => ['branch' => ['name' => 'main']],
            'source' => [
                'branch' => ['name' => 'feature'],
                'commit' => ['hash' => 'abc123'],
            ],
            'links' => ['html' => ['href' => 'https://bitbucket.org/org/repo/pull-requests/1']],
        ],
        'repository' => ['full_name' => 'nonexistent-org/nonexistent-repo'],
    ]));

    $data = json_decode($response->getContent(), true);
    expect($data['message'])->not->toContain('Event not handled');
    expect($data['message'])->toContain('Nothing to do. No applications found');
});

test('bitbucket pullrequest:rejected event is handled', function () {
    $response = (new Bitbucket)->manual(makeBitbucketRequest('pullrequest:rejected', [
        'pullrequest' => [
            'id' => 2,
            'destination' => ['branch' => ['name' => 'main']],
            'source' => [
                'branch' => ['name' => 'hotfix'],
                'commit' => ['hash' => 'xyz789'],
            ],
            'links' => ['html' => ['href' => 'https://bitbucket.org/org/repo/pull-requests/2']],
        ],
        'repository' => ['full_name' => 'nonexistent-org/nonexistent-repo'],
    ]));

    $data = json_decode($response->getContent(), true);
    expect($data['message'])->not->toContain('Event not handled');
});

test('bitbucket pullrequest:fulfilled event is handled', function () {
    $response = (new Bitbucket)->manual(makeBitbucketRequest('pullrequest:fulfilled', [
        'pullrequest' => [
            'id' => 3,
            'destination' => ['branch' => ['name' => 'main']],
            'source' => [
                'branch' => ['name' => 'develop'],
                'commit' => ['hash' => 'aaa111'],
            ],
            'links' => ['html' => ['href' => 'https://bitbucket.org/org/repo/pull-requests/3']],
        ],
        'repository' => ['full_name' => 'nonexistent-org/nonexistent-repo'],
    ]));

    $data = json_decode($response->getContent(), true);
    expect($data['message'])->not->toContain('Event not handled');
});

// ─── Controller existence ─────────────────────────────────────────────────────

test('Bitbucket controller class exists and has manual method', function () {
    expect(class_exists(Bitbucket::class))->toBeTrue();
    expect(method_exists(Bitbucket::class, 'manual'))->toBeTrue();
});
