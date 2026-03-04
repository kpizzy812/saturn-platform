<?php

/**
 * Unit tests for AI Chat DTO classes: ChatMessage, CommandResult, ParsedCommand, ParsedIntent.
 *
 * Tests cover:
 * - ChatMessage: factory methods (system/user/assistant), toArray()
 * - CommandResult: success/failed/unauthorized/notFound/needsResource factories, toArray()
 * - ParsedCommand: isActionable(), isDangerous(), hasResource(), toArray(), fromArray()
 * - ParsedIntent: hasCommands(), hasMultipleCommands(), getFirstCommand(), hasActionableCommands(),
 *   hasDangerousCommands(), getActionableCommands(), getDangerousCommands(),
 *   none()/withConfirmation() factories, toArray(), fromAIResponse()
 */

use App\Services\AI\Chat\DTOs\ChatMessage;
use App\Services\AI\Chat\DTOs\CommandResult;
use App\Services\AI\Chat\DTOs\ParsedCommand;
use App\Services\AI\Chat\DTOs\ParsedIntent;

// ─── ChatMessage ──────────────────────────────────────────────────────────────

test('ChatMessage::system creates message with system role', function () {
    $msg = ChatMessage::system('You are a helpful assistant.');
    expect($msg->role)->toBe('system');
    expect($msg->content)->toBe('You are a helpful assistant.');
});

test('ChatMessage::user creates message with user role', function () {
    $msg = ChatMessage::user('Deploy my app');
    expect($msg->role)->toBe('user');
    expect($msg->content)->toBe('Deploy my app');
});

test('ChatMessage::assistant creates message with assistant role', function () {
    $msg = ChatMessage::assistant('Deploying your app now...');
    expect($msg->role)->toBe('assistant');
    expect($msg->content)->toBe('Deploying your app now...');
});

test('ChatMessage toArray returns role and content keys', function () {
    $msg = ChatMessage::user('Hello');
    expect($msg->toArray())->toBe(['role' => 'user', 'content' => 'Hello']);
});

// ─── CommandResult ────────────────────────────────────────────────────────────

test('CommandResult::success creates successful result', function () {
    $result = CommandResult::success('Deployment started', ['deploy_id' => 42]);
    expect($result->success)->toBeTrue();
    expect($result->message)->toBe('Deployment started');
    expect($result->data)->toBe(['deploy_id' => 42]);
    expect($result->error)->toBeNull();
});

test('CommandResult::success works without data', function () {
    $result = CommandResult::success('Done');
    expect($result->success)->toBeTrue();
    expect($result->data)->toBeNull();
});

test('CommandResult::failed creates failed result', function () {
    $result = CommandResult::failed('Deployment failed', 'container error');
    expect($result->success)->toBeFalse();
    expect($result->message)->toBe('Deployment failed');
    expect($result->error)->toBe('container error');
});

test('CommandResult::failed works without error string', function () {
    $result = CommandResult::failed('Something went wrong');
    expect($result->success)->toBeFalse();
    expect($result->error)->toBeNull();
});

test('CommandResult::unauthorized creates unauthorized result', function () {
    $result = CommandResult::unauthorized();
    expect($result->success)->toBeFalse();
    expect($result->error)->toBe('unauthorized');
    expect($result->message)->toContain('not authorized');
});

test('CommandResult::unauthorized accepts custom message', function () {
    $result = CommandResult::unauthorized('Access denied for this resource.');
    expect($result->message)->toBe('Access denied for this resource.');
});

test('CommandResult::notFound creates not found result without similar resources', function () {
    $result = CommandResult::notFound('application');
    expect($result->success)->toBeFalse();
    expect($result->error)->toBe('not_found');
    expect($result->message)->toContain('application');
});

test('CommandResult::notFound creates disambiguation message with similar resources', function () {
    $similar = [
        ['name' => 'my-app', 'status' => 'running', 'environment' => 'production', 'project' => 'myproject'],
        ['name' => 'my-app-v2', 'status' => 'exited', 'environment' => 'staging', 'project' => 'myproject'],
    ];
    $result = CommandResult::notFound('application', $similar);
    expect($result->success)->toBeFalse();
    expect($result->error)->toBe('ambiguous');
    expect($result->message)->toContain('my-app');
    expect($result->data['similar'])->toBe($similar);
});

test('CommandResult toArray returns all keys', function () {
    $result = CommandResult::success('OK', ['key' => 'value']);
    $array = $result->toArray();
    expect($array)->toHaveKeys(['success', 'message', 'data', 'error']);
    expect($array['success'])->toBeTrue();
    expect($array['data'])->toBe(['key' => 'value']);
});

// ─── ParsedCommand ────────────────────────────────────────────────────────────

function makeCmd(string $action, ?string $resourceName = null, ?int $resourceId = null, ?string $resourceUuid = null): ParsedCommand
{
    return new ParsedCommand(
        action: $action,
        resourceType: 'application',
        resourceName: $resourceName,
        resourceId: $resourceId,
        resourceUuid: $resourceUuid,
    );
}

test('ParsedCommand isActionable returns true for deploy', function () {
    expect(makeCmd('deploy', 'my-app')->isActionable())->toBeTrue();
});

test('ParsedCommand isActionable returns true for all actionable actions', function () {
    $actions = ['deploy', 'restart', 'stop', 'start', 'logs', 'status', 'delete', 'analyze_errors', 'analyze_deployment', 'code_review', 'health_check', 'metrics'];
    foreach ($actions as $action) {
        expect(makeCmd($action)->isActionable())->toBeTrue($action.' should be actionable');
    }
});

test('ParsedCommand isActionable returns false for unknown action', function () {
    expect(makeCmd('unknown_action')->isActionable())->toBeFalse();
});

test('ParsedCommand isDangerous returns true for deploy', function () {
    expect(makeCmd('deploy')->isDangerous())->toBeTrue();
});

test('ParsedCommand isDangerous returns true for stop', function () {
    expect(makeCmd('stop')->isDangerous())->toBeTrue();
});

test('ParsedCommand isDangerous returns true for delete', function () {
    expect(makeCmd('delete')->isDangerous())->toBeTrue();
});

test('ParsedCommand isDangerous returns false for restart', function () {
    expect(makeCmd('restart')->isDangerous())->toBeFalse();
});

test('ParsedCommand isDangerous returns false for logs', function () {
    expect(makeCmd('logs')->isDangerous())->toBeFalse();
});

test('ParsedCommand hasResource returns true when resourceName is set', function () {
    expect(makeCmd('deploy', 'my-app')->hasResource())->toBeTrue();
});

test('ParsedCommand hasResource returns true when resourceId is set', function () {
    expect(makeCmd('deploy', resourceId: 42)->hasResource())->toBeTrue();
});

test('ParsedCommand hasResource returns true when resourceUuid is set', function () {
    expect(makeCmd('deploy', resourceUuid: 'abc-123')->hasResource())->toBeTrue();
});

test('ParsedCommand hasResource returns false when all resource fields are null', function () {
    expect(makeCmd('status')->hasResource())->toBeFalse();
});

test('ParsedCommand toArray includes all keys', function () {
    $cmd = new ParsedCommand('deploy', 'application', 'my-app', 1, 'uuid-123', 'proj', 'prod');
    $array = $cmd->toArray();
    expect($array)->toHaveKeys([
        'action', 'resource_type', 'resource_name', 'resource_id',
        'resource_uuid', 'project_name', 'environment_name',
        'deployment_uuid', 'target_scope', 'resource_names', 'time_period',
    ]);
    expect($array['action'])->toBe('deploy');
    expect($array['resource_name'])->toBe('my-app');
});

test('ParsedCommand::fromArray creates command from array', function () {
    $cmd = ParsedCommand::fromArray([
        'action' => 'deploy',
        'resource_type' => 'application',
        'resource_name' => 'my-app',
        'resource_id' => '42',
    ]);
    expect($cmd->action)->toBe('deploy');
    expect($cmd->resourceName)->toBe('my-app');
    expect($cmd->resourceId)->toBe(42); // cast to int
});

test('ParsedCommand::fromArray defaults action to none when missing', function () {
    $cmd = ParsedCommand::fromArray([]);
    expect($cmd->action)->toBe('none');
});

// ─── ParsedIntent ─────────────────────────────────────────────────────────────

test('ParsedIntent hasCommands returns false for empty commands', function () {
    $intent = ParsedIntent::none('No commands');
    expect($intent->hasCommands())->toBeFalse();
});

test('ParsedIntent hasCommands returns true when commands exist', function () {
    $intent = new ParsedIntent(commands: [makeCmd('deploy', 'my-app')]);
    expect($intent->hasCommands())->toBeTrue();
});

test('ParsedIntent hasMultipleCommands returns false for one command', function () {
    $intent = new ParsedIntent(commands: [makeCmd('deploy', 'my-app')]);
    expect($intent->hasMultipleCommands())->toBeFalse();
});

test('ParsedIntent hasMultipleCommands returns true for two commands', function () {
    $intent = new ParsedIntent(commands: [makeCmd('deploy', 'app1'), makeCmd('restart', 'app2')]);
    expect($intent->hasMultipleCommands())->toBeTrue();
});

test('ParsedIntent getFirstCommand returns first command', function () {
    $first = makeCmd('deploy', 'first-app');
    $second = makeCmd('restart', 'second-app');
    $intent = new ParsedIntent(commands: [$first, $second]);
    expect($intent->getFirstCommand()->resourceName)->toBe('first-app');
});

test('ParsedIntent getFirstCommand returns null for empty intent', function () {
    $intent = ParsedIntent::none();
    expect($intent->getFirstCommand())->toBeNull();
});

test('ParsedIntent hasActionableCommands returns true when any command is actionable', function () {
    $intent = new ParsedIntent(commands: [makeCmd('deploy', 'my-app')]);
    expect($intent->hasActionableCommands())->toBeTrue();
});

test('ParsedIntent hasActionableCommands returns false when no actionable commands', function () {
    $intent = new ParsedIntent(commands: [makeCmd('unknown_action')]);
    expect($intent->hasActionableCommands())->toBeFalse();
});

test('ParsedIntent hasDangerousCommands returns true when any command is dangerous', function () {
    $intent = new ParsedIntent(commands: [makeCmd('restart', 'safe-app'), makeCmd('delete', 'dangerous-app')]);
    expect($intent->hasDangerousCommands())->toBeTrue();
});

test('ParsedIntent hasDangerousCommands returns false when no dangerous commands', function () {
    $intent = new ParsedIntent(commands: [makeCmd('logs', 'my-app'), makeCmd('status', 'my-app')]);
    expect($intent->hasDangerousCommands())->toBeFalse();
});

test('ParsedIntent getActionableCommands filters to actionable only', function () {
    $intent = new ParsedIntent(commands: [
        makeCmd('deploy', 'app1'),
        makeCmd('unknown', 'app2'),
        makeCmd('restart', 'app3'),
    ]);
    $actionable = $intent->getActionableCommands();
    expect(count($actionable))->toBe(2);
});

test('ParsedIntent getDangerousCommands filters to dangerous only', function () {
    $intent = new ParsedIntent(commands: [
        makeCmd('deploy', 'app1'),
        makeCmd('restart', 'app2'),
        makeCmd('delete', 'app3'),
    ]);
    $dangerous = $intent->getDangerousCommands();
    expect(count($dangerous))->toBe(2); // deploy + delete
});

test('ParsedIntent::none creates empty intent with response text', function () {
    $intent = ParsedIntent::none('I cannot help with that.');
    expect($intent->hasCommands())->toBeFalse();
    expect($intent->responseText)->toBe('I cannot help with that.');
});

test('ParsedIntent::withConfirmation sets requiresConfirmation true', function () {
    $intent = ParsedIntent::withConfirmation(
        [makeCmd('stop', 'prod-app')],
        'Are you sure you want to stop?',
    );
    expect($intent->requiresConfirmation)->toBeTrue();
    expect($intent->confirmationMessage)->toBe('Are you sure you want to stop?');
    expect($intent->hasCommands())->toBeTrue();
});

test('ParsedIntent::fromAIResponse parses commands array', function () {
    $intent = ParsedIntent::fromAIResponse([
        'commands' => [
            ['action' => 'logs', 'resource_type' => 'application', 'resource_name' => 'my-app'],
        ],
        'confidence' => 0.9,
        'response_text' => 'Fetching logs...',
    ]);
    expect($intent->hasCommands())->toBeTrue();
    expect($intent->getFirstCommand()->action)->toBe('logs');
    expect($intent->confidence)->toBe(0.9);
    expect($intent->responseText)->toBe('Fetching logs...');
});

test('ParsedIntent::fromAIResponse sets requiresConfirmation for dangerous commands', function () {
    $intent = ParsedIntent::fromAIResponse([
        'commands' => [
            ['action' => 'deploy', 'resource_name' => 'prod-app'],
        ],
        'confidence' => 1.0,
    ]);
    expect($intent->requiresConfirmation)->toBeTrue();
    expect($intent->confirmationMessage)->not->toBeNull();
});

test('ParsedIntent::fromAIResponse skips commands with action=none', function () {
    $intent = ParsedIntent::fromAIResponse([
        'commands' => [
            ['action' => 'none'],
            ['action' => 'logs', 'resource_name' => 'my-app'],
        ],
    ]);
    expect(count($intent->commands))->toBe(1);
    expect($intent->getFirstCommand()->action)->toBe('logs');
});
