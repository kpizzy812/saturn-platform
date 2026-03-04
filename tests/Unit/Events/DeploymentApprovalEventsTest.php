<?php

/**
 * Unit tests for DeploymentApprovalRequested and DeploymentApprovalResolved events.
 *
 * Tests cover:
 * - Event construction with scalar properties
 * - broadcastWith() returns correct payload structure
 * - broadcastOn() returns correct channels (with teamId and without)
 * - broadcastAs() returns correct event name
 */

use App\Events\DeploymentApprovalRequested;
use App\Events\DeploymentApprovalResolved;
use Illuminate\Broadcasting\PrivateChannel;

// ─── DeploymentApprovalRequested ─────────────────────────────────────────────

function makeApprovalRequestedEvent(?int $teamId = 42): DeploymentApprovalRequested
{
    return new DeploymentApprovalRequested(
        approvalId: 1,
        approvalUuid: 'approval-uuid-001',
        deploymentId: 10,
        deploymentUuid: 'deploy-uuid-abc',
        applicationId: 100,
        applicationName: 'my-app',
        environmentName: 'production',
        projectName: 'my-project',
        requestedByEmail: 'dev@example.com',
        teamId: $teamId,
    );
}

test('DeploymentApprovalRequested broadcastWith returns all payload keys', function () {
    $event = makeApprovalRequestedEvent();
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys([
        'approvalId', 'approvalUuid', 'deploymentId', 'deploymentUuid',
        'applicationId', 'applicationName', 'environmentName', 'projectName',
        'requestedByEmail',
    ]);
    expect($payload['applicationName'])->toBe('my-app');
    expect($payload['requestedByEmail'])->toBe('dev@example.com');
    expect($payload['environmentName'])->toBe('production');
});

test('DeploymentApprovalRequested broadcastOn returns team private channel', function () {
    $event = makeApprovalRequestedEvent(teamId: 5);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-team.5');
});

test('DeploymentApprovalRequested broadcastOn returns empty when teamId is null', function () {
    $event = makeApprovalRequestedEvent(teamId: null);
    $channels = $event->broadcastOn();

    expect($channels)->toBeEmpty();
});

test('DeploymentApprovalRequested broadcastAs returns correct event name', function () {
    $event = makeApprovalRequestedEvent();
    expect($event->broadcastAs())->toBe('deployment.approval.requested');
});

test('DeploymentApprovalRequested stores all constructor properties', function () {
    $event = makeApprovalRequestedEvent(teamId: 99);

    expect($event->approvalId)->toBe(1);
    expect($event->approvalUuid)->toBe('approval-uuid-001');
    expect($event->deploymentId)->toBe(10);
    expect($event->deploymentUuid)->toBe('deploy-uuid-abc');
    expect($event->applicationId)->toBe(100);
    expect($event->teamId)->toBe(99);
});

// ─── DeploymentApprovalResolved ───────────────────────────────────────────────

function makeApprovalResolvedEvent(?int $teamId = 42): DeploymentApprovalResolved
{
    return new DeploymentApprovalResolved(
        approvalId: 1,
        approvalUuid: 'approval-uuid-001',
        status: 'approved',
        deploymentId: 10,
        deploymentUuid: 'deploy-uuid-abc',
        applicationId: 100,
        applicationName: 'my-app',
        environmentName: 'production',
        projectName: 'my-project',
        resolvedByEmail: 'admin@example.com',
        comment: 'LGTM!',
        requestedById: 7,
        teamId: $teamId,
    );
}

test('DeploymentApprovalResolved broadcastWith returns all payload keys', function () {
    $event = makeApprovalResolvedEvent();
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys([
        'approvalId', 'approvalUuid', 'status', 'deploymentId', 'deploymentUuid',
        'applicationId', 'applicationName', 'environmentName', 'projectName',
        'resolvedByEmail', 'comment',
    ]);
    expect($payload['status'])->toBe('approved');
    expect($payload['comment'])->toBe('LGTM!');
    expect($payload['resolvedByEmail'])->toBe('admin@example.com');
});

test('DeploymentApprovalResolved broadcastOn returns team and user channels', function () {
    $event = makeApprovalResolvedEvent(teamId: 5);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);

    $channelNames = array_map(fn ($ch) => $ch->name, $channels);
    expect($channelNames)->toContain('private-team.5');
    expect($channelNames)->toContain('private-user.7'); // requestedById
});

test('DeploymentApprovalResolved broadcastOn returns empty when teamId is null', function () {
    $event = makeApprovalResolvedEvent(teamId: null);
    $channels = $event->broadcastOn();

    expect($channels)->toBeEmpty();
});

test('DeploymentApprovalResolved broadcastAs returns correct event name', function () {
    $event = makeApprovalResolvedEvent();
    expect($event->broadcastAs())->toBe('deployment.approval.resolved');
});

test('DeploymentApprovalResolved stores all constructor properties', function () {
    $event = makeApprovalResolvedEvent(teamId: 12);

    expect($event->status)->toBe('approved');
    expect($event->comment)->toBe('LGTM!');
    expect($event->requestedById)->toBe(7);
    expect($event->teamId)->toBe(12);
});

test('DeploymentApprovalResolved with null comment is preserved', function () {
    $event = new DeploymentApprovalResolved(
        approvalId: 1, approvalUuid: 'uuid', status: 'rejected',
        deploymentId: 2, deploymentUuid: 'd-uuid', applicationId: 3,
        applicationName: 'app', environmentName: 'prod', projectName: 'proj',
        resolvedByEmail: 'admin@test.com', comment: null, requestedById: 5,
    );
    $payload = $event->broadcastWith();
    expect($payload['comment'])->toBeNull();
});
