<?php

use App\Models\ApplicationDeploymentQueue;
use Carbon\Carbon;

// Stage Constants Tests
test('stage constants are defined correctly', function () {
    expect(ApplicationDeploymentQueue::STAGE_PREPARE)->toBe('prepare')
        ->and(ApplicationDeploymentQueue::STAGE_CLONE)->toBe('clone')
        ->and(ApplicationDeploymentQueue::STAGE_BUILD)->toBe('build')
        ->and(ApplicationDeploymentQueue::STAGE_PUSH)->toBe('push')
        ->and(ApplicationDeploymentQueue::STAGE_DEPLOY)->toBe('deploy')
        ->and(ApplicationDeploymentQueue::STAGE_HEALTHCHECK)->toBe('healthcheck');
});

// setStage Tests
test('setStage sets currentStage property', function () {
    $deployment = new ApplicationDeploymentQueue;

    $result = $deployment->setStage('build');

    expect($deployment->currentStage)->toBe('build')
        ->and($result)->toBe($deployment); // Check fluent interface
});

test('setStage returns self for method chaining', function () {
    $deployment = new ApplicationDeploymentQueue;

    $result = $deployment->setStage('prepare')->setStage('clone');

    expect($result)->toBe($deployment)
        ->and($deployment->currentStage)->toBe('clone');
});

test('setStage accepts stage constants', function () {
    $deployment = new ApplicationDeploymentQueue;

    $deployment->setStage(ApplicationDeploymentQueue::STAGE_DEPLOY);

    expect($deployment->currentStage)->toBe('deploy');
});

// Note: getOutput() tests require database access because:
// - getOutput() calls $this->logs which triggers getLogsAttribute accessor
// - getLogsAttribute calls $this->logEntries()->get() which queries DB
// - Cannot bypass accessor without reflection/mocking which violates unit test pattern
// These tests should be in Feature tests with database

// commitMessage Tests
test('commitMessage returns null when commit_message is null', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->commit_message = null;

    expect($deployment->commitMessage())->toBeNull();
});

test('commitMessage returns null when commit_message is empty string', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->commit_message = '';

    expect($deployment->commitMessage())->toBeNull();
});

test('commitMessage returns string value when commit_message is set', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->commit_message = 'fix: resolve deployment issue';

    expect($deployment->commitMessage())->toBe('fix: resolve deployment issue');
});

test('commitMessage returns commit message without explicit trimming', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->commit_message = '  feat: add new feature  ';

    // str()->value() doesn't trim - it just returns the string value
    expect($deployment->commitMessage())->toBe('  feat: add new feature  ');
});

// getRawLogsAttribute Tests
test('getRawLogsAttribute returns raw logs value without accessor transformation', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->setRawAttributes(['logs' => '{"test":"data"}'], true);

    expect($deployment->getRawLogsAttribute())->toBe('{"test":"data"}');
});

test('getRawLogsAttribute returns null when logs not set', function () {
    $deployment = new ApplicationDeploymentQueue;

    expect($deployment->getRawLogsAttribute())->toBeNull();
});

// Note: server Attribute test requires database (calls Server::find())
// Cannot be tested in unit tests without DB

// Casts Tests
test('started_at is cast to datetime', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->setRawAttributes([
        'started_at' => '2024-01-15 10:30:00',
    ], true);

    expect($deployment->started_at)->toBeInstanceOf(Carbon::class)
        ->and($deployment->started_at->format('Y-m-d H:i:s'))->toBe('2024-01-15 10:30:00');
});

test('application_id is cast to integer', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->setRawAttributes(['application_id' => '42'], true);

    expect($deployment->application_id)->toBeInt()
        ->and($deployment->application_id)->toBe(42);
});

test('requires_approval is cast to boolean', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->setRawAttributes(['requires_approval' => 1], true);

    expect($deployment->requires_approval)->toBeBool()
        ->and($deployment->requires_approval)->toBeTrue();
});

test('approved_at is cast to datetime', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->setRawAttributes([
        'approved_at' => '2024-01-15 11:00:00',
    ], true);

    expect($deployment->approved_at)->toBeInstanceOf(Carbon::class)
        ->and($deployment->approved_at->format('Y-m-d H:i:s'))->toBe('2024-01-15 11:00:00');
});

// Fillable Attributes Tests
test('fillable includes all necessary deployment fields', function () {
    $deployment = new ApplicationDeploymentQueue;
    $fillable = $deployment->getFillable();

    expect($fillable)->toContain('application_id')
        ->toContain('deployment_uuid')
        ->toContain('pull_request_id')
        ->toContain('force_rebuild')
        ->toContain('commit')
        ->toContain('status')
        ->toContain('is_webhook')
        ->toContain('is_api')
        ->toContain('logs')
        ->toContain('current_process_id')
        ->toContain('restart_only')
        ->toContain('git_type')
        ->toContain('server_id')
        ->toContain('application_name')
        ->toContain('server_name')
        ->toContain('deployment_url')
        ->toContain('destination_id')
        ->toContain('only_this_server')
        ->toContain('rollback')
        ->toContain('commit_message')
        ->toContain('horizon_job_id')
        ->toContain('started_at')
        ->toContain('requires_approval')
        ->toContain('approval_status')
        ->toContain('approved_by')
        ->toContain('approved_at')
        ->toContain('rejection_reason')
        ->toContain('user_id');
});

// Status Flag Tests
test('deployment has status attribute', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->status = 'in_progress';

    expect($deployment->status)->toBe('in_progress');
});

test('deployment has is_webhook flag', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->is_webhook = true;

    expect($deployment->is_webhook)->toBeTrue();
});

test('deployment has is_api flag', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->is_api = true;

    expect($deployment->is_api)->toBeTrue();
});

test('deployment has force_rebuild flag', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->force_rebuild = true;

    expect($deployment->force_rebuild)->toBeTrue();
});

test('deployment has restart_only flag', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->restart_only = false;

    expect($deployment->restart_only)->toBeFalse();
});

test('deployment has rollback flag', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->rollback = true;

    expect($deployment->rollback)->toBeTrue();
});

test('deployment has only_this_server flag', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->only_this_server = false;

    expect($deployment->only_this_server)->toBeFalse();
});

// UUID and Identification Tests
test('deployment has deployment_uuid', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->deployment_uuid = 'abc-123-def-456';

    expect($deployment->deployment_uuid)->toBe('abc-123-def-456');
});

test('deployment has commit hash', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->commit = 'a1b2c3d4e5f6';

    expect($deployment->commit)->toBe('a1b2c3d4e5f6');
});

// Pull Request Tests
test('deployment has pull_request_id', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->pull_request_id = 42;

    expect($deployment->pull_request_id)->toBe(42);
});

test('deployment pull_request_id can be zero', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->pull_request_id = 0;

    expect($deployment->pull_request_id)->toBe(0);
});

// Approval Workflow Tests
test('deployment has requires_approval flag', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->requires_approval = true;

    expect($deployment->requires_approval)->toBeTrue();
});

test('deployment has approval_status', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->approval_status = 'approved';

    expect($deployment->approval_status)->toBe('approved');
});

test('deployment has approved_by field', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->approved_by = 5;

    expect($deployment->approved_by)->toBe(5);
});

test('deployment has rejection_reason field', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->rejection_reason = 'Code review failed';

    expect($deployment->rejection_reason)->toBe('Code review failed');
});

// Git and Source Control Tests
test('deployment has git_type field', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->git_type = 'github';

    expect($deployment->git_type)->toBe('github');
});

// Server and Deployment Target Tests
test('deployment has server_id', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->server_id = 10;

    expect($deployment->server_id)->toBe(10);
});

test('deployment has server_name', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->server_name = 'production-01';

    expect($deployment->server_name)->toBe('production-01');
});

test('deployment has application_name', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->application_name = 'my-app';

    expect($deployment->application_name)->toBe('my-app');
});

test('deployment has destination_id', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->destination_id = 'dest-123';

    expect($deployment->destination_id)->toBe('dest-123');
});

// URL and Metadata Tests
test('deployment has deployment_url', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->deployment_url = 'https://app.example.com';

    expect($deployment->deployment_url)->toBe('https://app.example.com');
});

test('deployment has horizon_job_id', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->horizon_job_id = 'job-abc-123';

    expect($deployment->horizon_job_id)->toBe('job-abc-123');
});

test('deployment has user_id', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->user_id = 7;

    expect($deployment->user_id)->toBe(7);
});

// currentStage Property Tests
test('currentStage defaults to null', function () {
    $deployment = new ApplicationDeploymentQueue;

    expect($deployment->currentStage)->toBeNull();
});

test('currentStage is not persisted to database', function () {
    $deployment = new ApplicationDeploymentQueue;
    $deployment->currentStage = 'build';

    // currentStage should not be in attributes array
    expect($deployment->getAttributes())->not->toHaveKey('currentStage');
});

// Mass Assignment Security Test
test('model uses fillable array for mass assignment protection', function () {
    $deployment = new ApplicationDeploymentQueue;

    // Verify $fillable is set (not using $guarded = [])
    expect($deployment->getFillable())->not->toBeEmpty();
});
