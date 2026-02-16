<?php

namespace App\Actions\Transfer;

use App\Models\Server;
use App\Services\Transfer\TransferStrategyFactory;

/**
 * Action to get database structure (tables, collections, keys).
 *
 * Used by the UI to display available items for partial transfer selection.
 */
class GetDatabaseStructureAction
{
    /**
     * Get the structure of a database.
     *
     * @param  mixed  $database  The database model
     * @return array Structure items with metadata
     */
    public function execute(mixed $database): array
    {
        $strategy = TransferStrategyFactory::getStrategy($database);
        if (! $strategy) {
            return [
                'success' => false,
                'error' => 'Unsupported database type',
                'items' => [],
            ];
        }

        $server = $this->getServer($database);
        if (! $server) {
            return [
                'success' => false,
                'error' => 'Could not determine server',
                'items' => [],
            ];
        }

        // Check if database is running
        if (method_exists($database, 'isRunning') && ! $database->isRunning()) {
            return [
                'success' => false,
                'error' => 'Database is not running',
                'items' => [],
            ];
        }

        try {
            $items = $strategy->getStructure($database, $server);

            // Calculate total size
            $totalSize = array_sum(array_column($items, 'size_bytes'));

            return [
                'success' => true,
                'error' => null,
                'database_type' => $strategy->getDatabaseType(),
                'supports_partial' => $strategy->supportsPartialTransfer(),
                'items' => $items,
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'item_label' => $this->getItemLabel($strategy->getDatabaseType()),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'items' => [],
            ];
        }
    }

    /**
     * Get server from database model.
     */
    private function getServer(mixed $database): ?Server
    {
        if (is_object($database) && method_exists($database, 'destination') && $database->destination) {
            $destination = $database->destination;
            if (is_object($destination) && property_exists($destination, 'server')) {
                return $destination->server;
            }
        }

        return null;
    }

    /**
     * Get the appropriate label for items based on database type.
     */
    private function getItemLabel(string $databaseType): string
    {
        return match ($databaseType) {
            'postgresql', 'mysql', 'mariadb', 'clickhouse' => 'tables',
            'mongodb' => 'collections',
            'redis' => 'key_patterns',
            default => 'items',
        };
    }

    /**
     * Format bytes to human-readable string.
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return number_format($bytes / pow(1024, $power), 2).' '.$units[$power];
    }
}
