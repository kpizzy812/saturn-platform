<?php

namespace App\Jobs;

use App\Models\ScheduledDatabaseBackupExecution;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupVerificationJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $timeout = 600;

    public function __construct(
        public ScheduledDatabaseBackupExecution $execution,
        public string $checksumAlgorithm = 'md5'
    ) {
        $this->onQueue('high');
    }

    public function backoff(): array
    {
        return [30, 60];
    }

    public function handle(): void
    {
        try {
            $this->execution->update([
                'verification_status' => 'pending',
            ]);

            /** @var \App\Models\ScheduledDatabaseBackup|null $backup */
            $backup = $this->execution->scheduledDatabaseBackup;
            if (! $backup) {
                $this->markFailed('Backup configuration not found');

                return;
            }

            $database = $backup->database()->first();
            if (! $database) {
                $this->markFailed('Database not found');

                return;
            }

            $destination = $database->getAttribute('destination');
            $server = $destination->server ?? $database->getAttribute('server') ?? null;
            if (! $server) {
                $this->markFailed('Server not found');

                return;
            }

            // Verify local backup if exists
            if (! $this->execution->local_storage_deleted && $this->execution->filename) {
                $this->verifyLocalBackup($server);
            }

            // Verify S3 backup if uploaded
            if ($this->execution->s3_uploaded && ! $this->execution->s3_storage_deleted) {
                $this->verifyS3Backup($backup);
            }

            // If we got here without failing, mark as verified
            if ($this->execution->verification_status !== 'failed') {
                $this->execution->update([
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'verification_message' => 'Backup verified successfully',
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Backup verification failed', [
                'execution_id' => $this->execution->id,
                'error' => $e->getMessage(),
            ]);
            $this->markFailed($e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BackupVerificationJob permanently failed', [
            'execution_id' => $this->execution->id,
            'error' => $exception->getMessage(),
        ]);
    }

    private function verifyLocalBackup(Server $server): void
    {
        $filename = $this->execution->filename;

        // Check file exists
        $checkExists = instant_remote_process(
            ["test -f {$filename} && echo 'exists' || echo 'not_found'"],
            $server
        );

        if (trim($checkExists) !== 'exists') {
            $this->markFailed("Local backup file not found: {$filename}");

            return;
        }

        // Get file size
        $sizeOutput = instant_remote_process(
            ["stat -c%s {$filename} 2>/dev/null || stat -f%z {$filename}"],
            $server
        );
        $size = (int) trim($sizeOutput);

        if ($size === 0) {
            $this->markFailed('Backup file is empty (0 bytes)');

            return;
        }

        // Verify backup is not corrupted based on file type
        $isValid = $this->verifyBackupIntegrity($server, $filename);
        if (! $isValid) {
            return;
        }

        // Calculate checksum
        $checksumCommand = $this->checksumAlgorithm === 'sha256'
            ? "sha256sum {$filename} | cut -d' ' -f1"
            : "md5sum {$filename} | cut -d' ' -f1";

        $checksum = instant_remote_process([$checksumCommand], $server);
        $checksum = trim($checksum);

        if (! empty($checksum) && strlen($checksum) >= 32) {
            $this->execution->update([
                'checksum' => $checksum,
                'checksum_algorithm' => $this->checksumAlgorithm,
            ]);
        }
    }

    private function verifyBackupIntegrity(Server $server, string $filename): bool
    {
        // Check based on file extension
        if (str_ends_with($filename, '.gz')) {
            // Test gzip integrity
            $result = instant_remote_process(
                ["gzip -t {$filename} 2>&1 && echo 'valid' || echo 'invalid'"],
                $server
            );

            if (! str_contains($result, 'valid')) {
                $this->markFailed('Gzip archive is corrupted: '.trim($result));

                return false;
            }
        } elseif (str_ends_with($filename, '.tar.gz') || str_ends_with($filename, '.tgz')) {
            // Test tar.gz integrity
            $result = instant_remote_process(
                ["tar -tzf {$filename} > /dev/null 2>&1 && echo 'valid' || echo 'invalid'"],
                $server
            );

            if (! str_contains($result, 'valid')) {
                $this->markFailed('Tar archive is corrupted');

                return false;
            }
        } elseif (str_ends_with($filename, '.dmp')) {
            // For PostgreSQL custom format, check header
            $result = instant_remote_process(
                ["head -c 5 {$filename} | od -A n -t x1 | tr -d ' \\n'"],
                $server
            );

            // PostgreSQL custom format starts with PGDMP
            $header = strtoupper(trim($result));
            if (! str_starts_with($header, '50474') && ! str_starts_with($header, '1f8b')) {
                // Check for plain SQL (starts with --)
                $textCheck = instant_remote_process(
                    ["head -c 2 {$filename}"],
                    $server
                );
                if (trim($textCheck) !== '--') {
                    $this->markFailed('Backup file format not recognized');

                    return false;
                }
            }
        }

        return true;
    }

    private function verifyS3Backup($backup): void
    {
        $s3 = $backup->s3;
        if (! $s3) {
            return;
        }

        try {
            $s3Path = $this->getS3Path($backup);

            // Configure S3 client
            $disk = \Illuminate\Support\Facades\Storage::build([
                'driver' => 's3',
                'key' => $s3->key,
                'secret' => $s3->secret,
                'region' => $s3->region,
                'bucket' => $s3->bucket,
                'endpoint' => $s3->endpoint,
                'use_path_style_endpoint' => true,
            ]);

            // Check file exists
            if (! $disk->exists($s3Path)) {
                $this->execution->update([
                    's3_integrity_status' => 'failed',
                    's3_integrity_checked_at' => now(),
                ]);

                return;
            }

            // Get file size and metadata
            $size = $disk->size($s3Path);

            $this->execution->update([
                's3_integrity_status' => 'verified',
                's3_integrity_checked_at' => now(),
                's3_file_size' => $size,
            ]);
        } catch (Throwable $e) {
            Log::error('S3 integrity check failed', [
                'execution_id' => $this->execution->id,
                'error' => $e->getMessage(),
            ]);
            $this->execution->update([
                's3_integrity_status' => 'failed',
                's3_integrity_checked_at' => now(),
            ]);
        }
    }

    private function getS3Path($backup): string
    {
        $database = $backup->database;
        $team = $database->getAttribute('team');

        $teamSlug = \Illuminate\Support\Str::slug($team->name);
        $dbSlug = \Illuminate\Support\Str::slug($database->getAttribute('name'));
        $basePath = $backup->s3->path ?? '';
        $filename = basename($this->execution->filename);

        return trim($basePath, '/')
            ."/databases/{$teamSlug}-{$team->id}/{$dbSlug}-{$database->getAttribute('uuid')}/{$filename}";
    }

    private function markFailed(string $message): void
    {
        $this->execution->update([
            'verification_status' => 'failed',
            'verified_at' => now(),
            'verification_message' => $message,
        ]);
    }
}
