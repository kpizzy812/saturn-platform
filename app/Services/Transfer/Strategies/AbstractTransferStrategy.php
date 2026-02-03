<?php

namespace App\Services\Transfer\Strategies;

use App\Models\ResourceTransfer;
use App\Models\Server;
use App\Services\Transfer\TransferStrategyInterface;

/**
 * Abstract base class for transfer strategies.
 *
 * Provides common functionality used by all database transfer strategies.
 */
abstract class AbstractTransferStrategy implements TransferStrategyInterface
{
    /**
     * Execute a command on a server via SSH.
     *
     * @param  array  $commands  Commands to execute
     * @param  Server  $server  The server to execute on
     * @param  bool  $throwError  Whether to throw on error
     * @param  int|null  $timeout  Command timeout in seconds
     * @return string Command output
     */
    protected function executeCommand(
        array $commands,
        Server $server,
        bool $throwError = true,
        ?int $timeout = null
    ): string {
        return instant_remote_process(
            $commands,
            $server,
            $throwError,
            false,
            $timeout,
            disableMultiplexing: true
        );
    }

    /**
     * Create a directory on the server if it doesn't exist.
     */
    protected function ensureDirectory(Server $server, string $path): void
    {
        $this->executeCommand(["mkdir -p {$path}"], $server, false);
    }

    /**
     * Get file size on the server.
     */
    protected function getFileSize(Server $server, string $path): int
    {
        $output = $this->executeCommand(
            ["stat -c%s {$path} 2>/dev/null || echo 0"],
            $server,
            false
        );

        return (int) trim($output);
    }

    /**
     * Check if file exists on the server.
     */
    protected function fileExists(Server $server, string $path): bool
    {
        $output = $this->executeCommand(
            ["test -f {$path} && echo 'exists' || echo 'not_found'"],
            $server,
            false
        );

        return trim($output) === 'exists';
    }

    /**
     * Delete a file on the server.
     */
    protected function deleteFile(Server $server, string $path): void
    {
        $this->executeCommand(["rm -f {$path}"], $server, false);
    }

    /**
     * Get escaped container name for docker commands.
     */
    protected function escapeContainerName(string $containerName): string
    {
        return escapeshellarg($containerName);
    }

    /**
     * Validate shell-safe path.
     *
     * @throws \Exception if path contains unsafe characters
     */
    protected function validatePath(string $path, string $description = 'path'): void
    {
        validateShellSafePath($path, $description);
    }

    /**
     * Clean up temporary files after transfer.
     */
    public function cleanup(Server $server, string $dumpPath): void
    {
        $this->deleteFile($server, $dumpPath);
    }

    /**
     * Default validation implementation.
     */
    public function validateTransfer(
        mixed $sourceDatabase,
        mixed $targetDatabase,
        ResourceTransfer $transfer
    ): array {
        $errors = [];

        // Check if source database is running
        if (method_exists($sourceDatabase, 'isRunning') && ! $sourceDatabase->isRunning()) {
            $errors[] = 'Source database is not running';
        }

        // For data_only mode, check target database
        if ($transfer->transfer_mode === ResourceTransfer::MODE_DATA_ONLY && $targetDatabase) {
            if (method_exists($targetDatabase, 'isRunning') && ! $targetDatabase->isRunning()) {
                $errors[] = 'Target database is not running';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Default supports partial transfer.
     */
    public function supportsPartialTransfer(): bool
    {
        return true;
    }
}
