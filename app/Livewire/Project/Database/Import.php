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
