<?php

namespace App\Services\Transfer;

use App\Models\ResourceTransfer;
use App\Models\Server;

/**
 * Interface for database transfer strategies.
 *
 * Each database type (PostgreSQL, MySQL, MongoDB, Redis, etc.) implements
 * this interface to provide specific backup/restore commands and logic.
 */
interface TransferStrategyInterface
{
    /**
     * Get the database type identifier.
     */
    public function getDatabaseType(): string;

    /**
     * Get the container name for the source database.
     */
    public function getContainerName(mixed $database): string;

    /**
     * Create a dump file on the source server.
     *
     * @param  mixed  $database  The database model
     * @param  Server  $server  The source server
     * @param  string  $dumpPath  Path where dump file should be created
     * @param  array|null  $options  Transfer options (tables, collections, etc.)
     * @return array Returns [success: bool, size: int, error: ?string]
     */
    public function createDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array;

    /**
     * Restore a dump file on the target server.
     *
     * @param  mixed  $database  The target database model
     * @param  Server  $server  The target server
     * @param  string  $dumpPath  Path to the dump file
     * @param  array|null  $options  Restore options
     * @return array Returns [success: bool, error: ?string]
     */
    public function restoreDump(
        mixed $database,
        Server $server,
        string $dumpPath,
        ?array $options = null
    ): array;

    /**
     * Get the database structure (tables, collections, keys).
     *
     * @param  mixed  $database  The database model
     * @param  Server  $server  The server
     * @return array Returns list of items (tables/collections/keys) with metadata
     */
    public function getStructure(mixed $database, Server $server): array;

    /**
     * Estimate the size of data to be transferred.
     *
     * @param  mixed  $database  The database model
     * @param  Server  $server  The server
     * @param  array|null  $options  Transfer options (tables, collections, etc.)
     * @return int Size in bytes
     */
    public function estimateSize(mixed $database, Server $server, ?array $options = null): int;

    /**
     * Validate that the transfer can be performed.
     *
     * @param  mixed  $sourceDatabase  Source database model
     * @param  mixed  $targetDatabase  Target database model (for data_only mode)
     * @param  ResourceTransfer  $transfer  The transfer record
     * @return array Returns [valid: bool, errors: array]
     */
    public function validateTransfer(
        mixed $sourceDatabase,
        mixed $targetDatabase,
        ResourceTransfer $transfer
    ): array;

    /**
     * Clean up temporary files after transfer.
     *
     * @param  Server  $server  The server to clean up
     * @param  string  $dumpPath  Path to the dump file
     */
    public function cleanup(Server $server, string $dumpPath): void;

    /**
     * Get the file extension for dump files.
     */
    public function getDumpExtension(): string;

    /**
     * Check if this strategy supports partial transfers.
     */
    public function supportsPartialTransfer(): bool;
}
