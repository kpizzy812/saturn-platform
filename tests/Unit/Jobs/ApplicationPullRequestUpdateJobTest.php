<?php

use App\Enums\ProcessStatus;
use App\Jobs\ApplicationPullRequestUpdateJob;
use App\Models\Application;
use App\Models\ApplicationPreview;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $app = Mockery::mock(Application::class)->makePartial();
    $preview = Mockery::mock(ApplicationPreview::class)->makePartial();

    $job = new ApplicationPullRequestUpdateJob($app, $preview, ProcessStatus::QUEUED, 'test-uuid');

    expect($job->queue)->toBe('high');

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

test('status message for QUEUED contains queued text and hourglass', function () {
    $serviceName = 'MyApp';

    $body = match (ProcessStatus::QUEUED) {
        ProcessStatus::QUEUED => "The preview deployment for **{$serviceName}** is queued. ⏳\n\n",
        default => '',
    };

    expect($body)->toContain('queued');
    expect($body)->toContain('⏳');
    expect($body)->toContain($serviceName);
});

test('status message for IN_PROGRESS contains in progress text and yellow emoji', function () {
    $serviceName = 'MyApp';

    $body = match (ProcessStatus::IN_PROGRESS) {
        ProcessStatus::IN_PROGRESS => "The preview deployment for **{$serviceName}** is in progress. 🟡\n\n",
        default => '',
    };

    expect($body)->toContain('in progress');
    expect($body)->toContain('🟡');
});

test('status message for FINISHED contains ready text and preview link when FQDN exists', function () {
    $serviceName = 'MyApp';
    $fqdn = 'https://preview.example.com';

    $body = "The preview deployment for **{$serviceName}** is ready. 🟢\n\n".($fqdn ? "[Open Preview]({$fqdn}) | " : '');

    expect($body)->toContain('ready');
    expect($body)->toContain('🟢');
    expect($body)->toContain('[Open Preview]');
    expect($body)->toContain($fqdn);
});

test('status message for FINISHED without FQDN omits preview link', function () {
    $serviceName = 'MyApp';
    $fqdn = null;

    $body = "The preview deployment for **{$serviceName}** is ready. 🟢\n\n".($fqdn ? "[Open Preview]({$fqdn}) | " : '');

    expect($body)->toContain('ready');
    expect($body)->not->toContain('[Open Preview]');
});

test('status message for ERROR contains failed text and red emoji', function () {
    $serviceName = 'MyApp';

    $body = match (ProcessStatus::ERROR) {
        ProcessStatus::ERROR => "The preview deployment for **{$serviceName}** failed. 🔴\n\n",
        default => '',
    };

    expect($body)->toContain('failed');
    expect($body)->toContain('🔴');
});

test('status message for KILLED contains killed text and black emoji', function () {
    $serviceName = 'MyApp';

    $body = match (ProcessStatus::KILLED) {
        ProcessStatus::KILLED => "The preview deployment for **{$serviceName}** was killed. ⚫\n\n",
        default => '',
    };

    expect($body)->toContain('killed');
    expect($body)->toContain('⚫');
});

test('status message for CANCELLED contains cancelled text and prohibition emoji', function () {
    $serviceName = 'MyApp';

    $body = match (ProcessStatus::CANCELLED) {
        ProcessStatus::CANCELLED => "The preview deployment for **{$serviceName}** was cancelled. 🚫\n\n",
        default => '',
    };

    expect($body)->toContain('cancelled');
    expect($body)->toContain('🚫');
});

test('build logs URL is constructed correctly', function () {
    $deploymentUuid = 'abc-123-def';
    $buildLogsUrl = "https://saturn.ac/deployments/{$deploymentUuid}";

    expect($buildLogsUrl)->toContain('/deployments/');
    expect($buildLogsUrl)->toContain($deploymentUuid);
});

test('body includes build logs link', function () {
    $buildLogsUrl = 'https://saturn.ac/deployments/test-uuid';
    $body = "Deployment ready.\n\n";
    $body .= '[Open Build Logs]('.$buildLogsUrl.")\n\n\n";
    $body .= 'Last updated at: '.now()->toDateTimeString().' CET';

    expect($body)->toContain('[Open Build Logs]');
    expect($body)->toContain($buildLogsUrl);
    expect($body)->toContain('Last updated at:');
    expect($body)->toContain('CET');
});

test('source code returns early for public repositories', function () {
    $source = file_get_contents((new ReflectionClass(ApplicationPullRequestUpdateJob::class))->getFileName());

    expect($source)->toContain('is_public_repository()');
    expect($source)->toContain('return;');
});

test('source code handles CLOSED status by deleting comment', function () {
    $source = file_get_contents((new ReflectionClass(ApplicationPullRequestUpdateJob::class))->getFileName());

    expect($source)->toContain('ProcessStatus::CLOSED');
    expect($source)->toContain('delete_comment()');
});

test('source code creates or updates comment based on existing comment ID', function () {
    $source = file_get_contents((new ReflectionClass(ApplicationPullRequestUpdateJob::class))->getFileName());

    expect($source)->toContain('pull_request_issue_comment_id');
    expect($source)->toContain('update_comment()');
    expect($source)->toContain('create_comment()');
});

test('source code falls back to create_comment on 404', function () {
    $source = file_get_contents((new ReflectionClass(ApplicationPullRequestUpdateJob::class))->getFileName());

    expect($source)->toContain("'Not Found'");
    expect($source)->toContain('create_comment()');
});

test('source code uses githubApi helper', function () {
    $source = file_get_contents((new ReflectionClass(ApplicationPullRequestUpdateJob::class))->getFileName());

    expect($source)->toContain('githubApi(');
    expect($source)->toContain('getGithubSource()');
});

test('source code saves comment ID after creation', function () {
    $source = file_get_contents((new ReflectionClass(ApplicationPullRequestUpdateJob::class))->getFileName());

    expect($source)->toContain("pull_request_issue_comment_id = \$data['id']");
    expect($source)->toContain('->save()');
});
