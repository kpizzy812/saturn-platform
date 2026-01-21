<?php

use App\Events\DeploymentLogEntry;
use Illuminate\Broadcasting\PrivateChannel;

describe('DeploymentLogEntry Event', function () {
    test('creates event with correct properties', function () {
        $event = new DeploymentLogEntry(
            deploymentUuid: 'test-uuid-123',
            message: 'Building application...',
            timestamp: '2026-01-22T10:00:00Z',
            type: 'stdout',
            order: 1
        );

        expect($event->deploymentUuid)->toBe('test-uuid-123');
        expect($event->message)->toBe('Building application...');
        expect($event->timestamp)->toBe('2026-01-22T10:00:00Z');
        expect($event->type)->toBe('stdout');
        expect($event->order)->toBe(1);
    });

    test('broadcasts on correct private channel', function () {
        $event = new DeploymentLogEntry(
            deploymentUuid: 'deploy-abc-123',
            message: 'Test message',
            timestamp: '2026-01-22T10:00:00Z'
        );

        $channels = $event->broadcastOn();

        expect($channels)->toHaveCount(1);
        expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
        expect($channels[0]->name)->toBe('private-deployment.deploy-abc-123.logs');
    });

    test('broadcastWith returns correct data structure', function () {
        $event = new DeploymentLogEntry(
            deploymentUuid: 'test-uuid',
            message: 'Error occurred',
            timestamp: '2026-01-22T10:05:00Z',
            type: 'stderr',
            order: 42
        );

        $data = $event->broadcastWith();

        expect($data)->toBeArray();
        expect($data)->toHaveKey('message');
        expect($data)->toHaveKey('timestamp');
        expect($data)->toHaveKey('type');
        expect($data)->toHaveKey('order');

        expect($data['message'])->toBe('Error occurred');
        expect($data['timestamp'])->toBe('2026-01-22T10:05:00Z');
        expect($data['type'])->toBe('stderr');
        expect($data['order'])->toBe(42);
    });

    test('broadcastWith does not include deploymentUuid in payload', function () {
        $event = new DeploymentLogEntry(
            deploymentUuid: 'sensitive-uuid',
            message: 'Test',
            timestamp: '2026-01-22T10:00:00Z'
        );

        $data = $event->broadcastWith();

        // deploymentUuid should NOT be in the broadcast payload (it's in the channel name)
        expect($data)->not->toHaveKey('deploymentUuid');
        expect($data)->not->toHaveKey('deployment_uuid');
    });

    test('defaults to stdout type', function () {
        $event = new DeploymentLogEntry(
            deploymentUuid: 'test',
            message: 'Default type test',
            timestamp: '2026-01-22T10:00:00Z'
        );

        expect($event->type)->toBe('stdout');
    });

    test('defaults to order 1', function () {
        $event = new DeploymentLogEntry(
            deploymentUuid: 'test',
            message: 'Default order test',
            timestamp: '2026-01-22T10:00:00Z'
        );

        expect($event->order)->toBe(1);
    });

    test('handles stderr type correctly', function () {
        $event = new DeploymentLogEntry(
            deploymentUuid: 'test',
            message: 'npm ERR! Missing script: "build"',
            timestamp: '2026-01-22T10:00:00Z',
            type: 'stderr'
        );

        expect($event->type)->toBe('stderr');
        $data = $event->broadcastWith();
        expect($data['type'])->toBe('stderr');
    });

    test('handles multiline messages', function () {
        $multilineMessage = "Step 1/8 : FROM node:18-alpine\nStep 2/8 : WORKDIR /app";

        $event = new DeploymentLogEntry(
            deploymentUuid: 'test',
            message: $multilineMessage,
            timestamp: '2026-01-22T10:00:00Z'
        );

        expect($event->message)->toBe($multilineMessage);
        $data = $event->broadcastWith();
        expect($data['message'])->toBe($multilineMessage);
    });

    test('handles empty message', function () {
        $event = new DeploymentLogEntry(
            deploymentUuid: 'test',
            message: '',
            timestamp: '2026-01-22T10:00:00Z'
        );

        expect($event->message)->toBe('');
    });

    test('handles special characters in message', function () {
        $specialMessage = '✓ Build completed! <script>alert("xss")</script>';

        $event = new DeploymentLogEntry(
            deploymentUuid: 'test',
            message: $specialMessage,
            timestamp: '2026-01-22T10:00:00Z'
        );

        // Event should preserve the message as-is (XSS prevention is UI responsibility)
        expect($event->message)->toBe($specialMessage);
    });
});
