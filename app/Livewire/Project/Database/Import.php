<?php

namespace App\Livewire\Project\Database;

use Livewire\Component;

class Import extends Component
{
    public bool $dumpAll = false;

    public ?string $postgresqlRestoreCommand = null;

    public ?string $mysqlRestoreCommand = null;

    public ?string $mariadbRestoreCommand = null;

    public ?string $mongodbRestoreCommand = null;

    public $resource = null;

    public $server = null;

    public ?string $customLocation = null;

    public ?string $filename = null;

    /**
     * Check if file exists at customLocation on server.
     */
    public function checkFile(): void
    {
        if (empty($this->customLocation)) {
            return;
        }

        // Validate the path before checking
        if (! $this->validateServerPath($this->customLocation)) {
            $this->filename = null;

            return;
        }

        // File check would be performed here via SSH
        $this->filename = basename($this->customLocation);
    }

    /**
     * Validate bucket name for S3 operations.
     */
    protected function validateBucketName(string $name): bool
    {
        // Bucket names can only contain alphanumeric, hyphens, underscores, and dots
        // Must not contain shell metacharacters
        if (preg_match('/[;&|`$()\'"\\\\\s\n\r]/', $name)) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z0-9._-]+$/', $name);
    }

    /**
     * Validate S3 path for security.
     */
    protected function validateS3Path(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        // Reject directory traversal
        if (str_contains($path, '..')) {
            return false;
        }

        // Reject shell metacharacters and dangerous characters
        if (preg_match('/[;&|`$()\'"\\\\\n\r\x00]/', $path)) {
            return false;
        }

        return true;
    }

    /**
     * Validate server path for security.
     */
    protected function validateServerPath(string $path): bool
    {
        // Must be absolute path
        if (! str_starts_with($path, '/')) {
            return false;
        }

        // Reject directory traversal
        if (str_contains($path, '..')) {
            return false;
        }

        // Reject shell metacharacters and dangerous characters
        if (preg_match('/[;&|`$()\'"\\\\\n\r\x00]/', $path)) {
            return false;
        }

        return true;
    }

    /**
     * Build the restore command for the given database dump file.
     */
    public function buildRestoreCommand(string $filePath): string
    {
        $morphClass = $this->resource?->getMorphClass() ?? '';

        // PostgreSQL
        if (str_contains($morphClass, 'Postgresql')) {
            if ($this->dumpAll) {
                // For dump-all, we need to gunzip and pipe to psql
                return "gunzip -cf {$filePath} | psql -U \$POSTGRES_USER postgres";
            }

            return "{$this->postgresqlRestoreCommand} {$filePath}";
        }

        // MySQL
        if (str_contains($morphClass, 'Mysql')) {
            return "{$this->mysqlRestoreCommand} < {$filePath}";
        }

        // MariaDB
        if (str_contains($morphClass, 'Mariadb')) {
            return "{$this->mariadbRestoreCommand} < {$filePath}";
        }

        // MongoDB
        if (str_contains($morphClass, 'Mongodb')) {
            return "{$this->mongodbRestoreCommand}{$filePath}";
        }

        return '';
    }

    public function render()
    {
        return view('livewire.project.database.import');
    }
}
