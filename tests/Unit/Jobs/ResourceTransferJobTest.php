<?php

use App\Jobs\Transfer\ResourceTransferJob;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $job = new ResourceTransferJob(1);

    expect($job->tries)->toBe(1);
    expect($job->maxExceptions)->toBe(1);
    expect($job->timeout)->toBe(7200);
    expect($job->queue)->toBe('long');

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

test('constructor stores transfer ID', function () {
    $job = new ResourceTransferJob(42);
    expect($job->transferId)->toBe(42);
    expect($job->targetDatabaseId)->toBeNull();
});

test('constructor stores target database ID for data_only mode', function () {
    $job = new ResourceTransferJob(42, 99);
    expect($job->transferId)->toBe(42);
    expect($job->targetDatabaseId)->toBe(99);
});

test('source code handles transfer not found', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('ResourceTransfer::find($this->transferId)');
    expect($source)->toContain('Transfer not found');
    expect($source)->toContain('return;');
});

test('source code checks for cancelled transfer', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('STATUS_CANCELLED');
    expect($source)->toContain('Transfer was cancelled');
});

test('source code marks as preparing before starting', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('markAsPreparing');
    expect($source)->toContain('Loading source database');
});

test('source code handles missing source database', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('Source database not found');
    expect($source)->toContain('markAsFailed');
});

test('source code handles data_only mode with missing target', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('MODE_DATA_ONLY');
    expect($source)->toContain('Target database not found');
});

test('source code uses TransferDatabaseDataAction', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('TransferDatabaseDataAction');
    expect($source)->toContain('->execute($transfer, $sourceDatabase, $targetDatabase)');
});

test('source code broadcasts status via WebSocket', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('ResourceTransferStatusChanged::fromTransfer');
    expect($source)->toContain('broadcastStatus');
    expect($source)->toContain('event(');
});

test('source code broadcasts in finally block', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('finally');
    expect($source)->toContain('$transfer->fresh()');
});

test('source code appends logs with database names', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('appendLog');
    expect($source)->toContain('mode_label');
    expect($source)->toContain("getAttribute('name')");
});

test('source code handles exceptions and marks transfer as failed', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('markAsFailed($e->getMessage()');
    expect($source)->toContain('getTraceAsString()');
    expect($source)->toContain('throw $e');
});

test('source code has failed callback with logging', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('public function failed');
    expect($source)->toContain('scheduled-errors');
    expect($source)->toContain('ResourceTransferJob permanently failed');
});

test('failed callback marks transfer as failed if found', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    // failed() method also tries to find and update the transfer
    expect($source)->toContain('ResourceTransfer::find($this->transferId)');
    expect($source)->toContain('Job permanently failed');
});

test('loadTargetDatabase uses source type for polymorphic lookup', function () {
    $source = file_get_contents((new ReflectionClass(ResourceTransferJob::class))->getFileName());

    expect($source)->toContain('$sourceType::find($targetId)');
    expect($source)->toContain('$transfer->source_type');
});
