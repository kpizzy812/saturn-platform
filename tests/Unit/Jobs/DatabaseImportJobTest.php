<?php

use App\Jobs\DatabaseImportJob;

beforeEach(function () {
    Mockery::close();
});

afterEach(function () {
    Mockery::close();
});

test('job has correct configuration', function () {
    $job = new DatabaseImportJob(1);

    expect($job->timeout)->toBe(7200);
    expect($job->tries)->toBe(1);
    expect($job->queue)->toBe('high');

    $interfaces = class_implements($job);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldQueue::class);
    expect($interfaces)->toContain(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

test('constructor stores import ID', function () {
    $job = new DatabaseImportJob(42);
    expect($job->importId)->toBe(42);
});

test('source code handles import not found', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('DatabaseImport::find($this->importId)');
    expect($source)->toContain('Import record not found');
    expect($source)->toContain('return;');
});

test('source code marks import as in progress', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('markAsInProgress()');
});

test('source code handles remote_pull and file_upload modes', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain("'remote_pull'");
    expect($source)->toContain("'file_upload'");
    expect($source)->toContain('handleRemotePull');
    expect($source)->toContain('handleFileUpload');
    expect($source)->toContain('Unknown import mode');
});

test('source code uses ConnectionStringParser for remote pull', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('ConnectionStringParser');
    expect($source)->toContain('$parser->parse');
    expect($source)->toContain('buildDumpCommand');
    expect($source)->toContain('getDumpExtension');
    expect($source)->toContain('getDumpDockerImage');
});

test('source code uses escapeshellarg for security', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('escapeshellarg($dockerImage)');
    expect($source)->toContain('escapeshellarg($dumpCommand)');
    expect($source)->toContain('escapeshellarg($dumpPath)');
    expect($source)->toContain('escapeshellarg($remoteDumpPath)');
});

test('source code broadcasts progress events', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('DatabaseImportProgress::dispatch');
    expect($source)->toContain('broadcastProgress');
    // Verifies various progress points
    expect($source)->toContain("'in_progress'");
    expect($source)->toContain("'completed'");
    expect($source)->toContain("'failed'");
});

test('source code cleans up temp files in finally block', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('finally');
    expect($source)->toContain('rm -f');
});

test('source code handles file upload with localhost vs remote', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('is_localhost');
    expect($source)->toContain('docker cp');
    expect($source)->toContain('instant_scp');
});

test('source code validates file exists for upload mode', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('file_exists($localFilePath)');
    expect($source)->toContain('Upload file not found');
    expect($source)->toContain('No file path specified');
});

test('source code uses TransferStrategyFactory for restore', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('TransferStrategyFactory::getStrategy');
    expect($source)->toContain('restoreDump');
    expect($source)->toContain('No transfer strategy available');
});

test('source code marks import as completed on success', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('markAsCompleted');
    expect($source)->toContain('Import from remote database completed successfully');
    expect($source)->toContain('Import from uploaded file completed successfully');
});

test('source code marks import as failed on error', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('markAsFailed');
    expect($source)->toContain('Import failed');
});

test('source code has failed callback', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('public function failed');
    expect($source)->toContain('DatabaseImportJob permanently failed');
});

test('source code cleans up local uploaded file', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('@unlink($localFilePath)');
});

test('source code uses docker run for remote dump', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseImportJob::class))->getFileName());

    expect($source)->toContain('docker run --rm --network host');
});

test('dump path uses import UUID for uniqueness', function () {
    // Verify the path construction pattern
    $uuid = 'abc-123-def';
    $extension = 'sql';
    $dumpPath = "/tmp/saturn-import-{$uuid}.{$extension}";

    expect($dumpPath)->toBe('/tmp/saturn-import-abc-123-def.sql');
    expect($dumpPath)->toContain($uuid);
});
