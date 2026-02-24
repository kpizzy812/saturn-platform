<?php

namespace App\Jobs;

use App\Events\DatabaseImportProgress;
use App\Models\DatabaseImport;
use App\Services\Database\ConnectionStringParser;
use App\Services\Transfer\TransferStrategyFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DatabaseImportJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 7200;

    public $tries = 1;

    public function __construct(
        public int $importId,
    ) {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        $import = DatabaseImport::find($this->importId);

        if (! $import) {
            Log::error('DatabaseImportJob: Import record not found', ['importId' => $this->importId]);

            return;
        }

        $import->markAsInProgress();
        $this->broadcastProgress($import, 'in_progress', 5, 'Starting import...');

        try {
            match ($import->mode) {
                'remote_pull' => $this->handleRemotePull($import),
                'file_upload' => $this->handleFileUpload($import),
                default => throw new \RuntimeException("Unknown import mode: {$import->mode}"),
            };
        } catch (\Throwable $e) {
            Log::error('DatabaseImportJob failed', [
                'importId' => $import->id,
                'error' => $e->getMessage(),
            ]);

            $import->markAsFailed($e->getMessage());
            $this->broadcastProgress($import, 'failed', $import->progress, 'Import failed', $e->getMessage());
        }
    }

    private function handleRemotePull(DatabaseImport $import): void
    {
        $database = $import->database;
        /** @phpstan-ignore property.notFound */
        $server = $database->destination->server;

        $parser = new ConnectionStringParser;
        $parsed = $parser->parse($import->connection_string);

        $this->broadcastProgress($import, 'in_progress', 10, 'Connection string parsed, preparing dump...');

        // Build dump command
        $dumpUuid = $import->uuid;
        $extension = $parser->getDumpExtension($parsed['type']);
        $dumpPath = "/tmp/saturn-import-{$dumpUuid}.{$extension}";
        $dumpCommand = $parser->buildDumpCommand($parsed, $dumpPath);
        $dockerImage = $parser->getDumpDockerImage($parsed['type']);

        // Run dump via temporary Docker container on target server
        $escapedImage = escapeshellarg($dockerImage);
        $escapedDumpCommand = escapeshellarg($dumpCommand);
        $fullCommand = "docker run --rm --network host {$escapedImage} sh -c {$escapedDumpCommand}";

        $this->broadcastProgress($import, 'in_progress', 20, 'Pulling data from remote database...');

        $escapedDumpPath = escapeshellarg($dumpPath);

        try {
            instant_remote_process([$fullCommand], $server, true, false, 3600, disableMultiplexing: true);
        } catch (\Throwable $e) {
            // Cleanup temp file on failure
            instant_remote_process(["rm -f {$escapedDumpPath}"], $server, false);
            throw new \RuntimeException('Failed to dump remote database: '.$e->getMessage());
        }

        $import->updateProgress(60, 'Dump completed, restoring into local database...');
        $this->broadcastProgress($import, 'in_progress', 60, 'Dump completed, restoring into local database...');

        // Restore dump using the existing transfer strategy
        try {
            $this->restoreDump($database, $server, $dumpPath, $import);
        } finally {
            // Always cleanup temp file
            instant_remote_process(["rm -f {$escapedDumpPath}"], $server, false);
        }

        $import->markAsCompleted('Import from remote database completed successfully.');
        $this->broadcastProgress($import, 'completed', 100, 'Import completed successfully!');
    }

    private function handleFileUpload(DatabaseImport $import): void
    {
        $database = $import->database;
        /** @phpstan-ignore property.notFound */
        $server = $database->destination->server;

        $this->broadcastProgress($import, 'in_progress', 10, 'Preparing file for restore...');

        $filePath = $import->file_path;

        if (! $filePath) {
            throw new \RuntimeException('No file path specified for upload import.');
        }

        // The file was uploaded via UploadController to Saturn's local storage.
        // We need to SCP it to the target server if it's not localhost.
        $localFilePath = storage_path("app/{$filePath}");

        if (! file_exists($localFilePath)) {
            throw new \RuntimeException('Upload file not found at: '.$filePath);
        }

        $dumpUuid = $import->uuid;
        $remoteDumpPath = "/tmp/saturn-import-{$dumpUuid}.dump";

        // Transfer file from Saturn container to target server.
        // The job runs inside the Saturn container, so the file exists at $localFilePath
        // inside this container. instant_remote_process runs on the HOST via SSH,
        // so we must use `docker cp` to extract the file from this container first.
        $saturnContainer = trim((string) gethostname());
        $escapedRemote = escapeshellarg($remoteDumpPath);

        if ($server->is_localhost) {
            // Localhost: use docker cp via SSH to extract file from Saturn container to host
            $escapedContainerPath = escapeshellarg("{$saturnContainer}:{$localFilePath}");
            instant_remote_process(["docker cp {$escapedContainerPath} {$escapedRemote}"], $server, true);
        } else {
            // Remote server: SCP directly from Saturn container to remote server
            instant_scp($localFilePath, $remoteDumpPath, $server);
        }

        $this->broadcastProgress($import, 'in_progress', 40, 'File transferred, restoring database...');
        $import->updateProgress(40, 'File transferred to server');

        // Restore dump
        $escapedRemoteDump = escapeshellarg($remoteDumpPath);
        try {
            $this->restoreDump($database, $server, $remoteDumpPath, $import);
        } finally {
            // Cleanup remote temp file
            instant_remote_process(["rm -f {$escapedRemoteDump}"], $server, false);
            // Cleanup local uploaded file
            @unlink($localFilePath);
        }

        $import->markAsCompleted('Import from uploaded file completed successfully.');
        $this->broadcastProgress($import, 'completed', 100, 'Import completed successfully!');
    }

    private function restoreDump(mixed $database, mixed $server, string $dumpPath, DatabaseImport $import): void
    {
        $strategy = TransferStrategyFactory::getStrategy($database);

        if (! $strategy) {
            throw new \RuntimeException('No transfer strategy available for this database type.');
        }

        $result = $strategy->restoreDump($database, $server, $dumpPath);

        if (! $result['success']) {
            throw new \RuntimeException('Restore failed: '.($result['error'] ?? 'Unknown error'));
        }

        $import->updateProgress(90, 'Restore completed, finalizing...');
        $this->broadcastProgress($import, 'in_progress', 90, 'Restore completed, finalizing...');
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DatabaseImportJob permanently failed', [
            'import_id' => $this->importId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function broadcastProgress(
        DatabaseImport $import,
        string $status,
        int $progress,
        string $message,
        ?string $error = null,
    ): void {
        $database = $import->database;

        DatabaseImportProgress::dispatch(
            $import->team_id,
            /** @phpstan-ignore property.notFound */
            $database->uuid,
            $import->uuid,
            $status,
            $progress,
            $message,
            $error,
        );
    }
}
