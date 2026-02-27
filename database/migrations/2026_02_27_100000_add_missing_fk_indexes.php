<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Missing FK indexes identified in the 2026-02-27 audit.
     *
     * Skipped (already indexed by previous migrations):
     *   - applications.environment_id          — 2026_02_23_100000_add_performance_indexes_v2
     *   - environment_variables(resourceable_*) — 2024_12_16_134437
     */
    private array $indexes = [
        // servers — team-scoped server listing
        ['servers', ['team_id'], 'idx_servers_team_id'],

        // projects — team-scoped project listing
        ['projects', ['team_id'], 'idx_projects_team_id'],

        // environments — project-scoped environment listing
        ['environments', ['project_id'], 'idx_environments_project_id'],

        // application_deployment_queues — application history listing
        ['application_deployment_queues', ['application_id'], 'idx_deploy_queues_application_id'],

        // application_deployment_queues — status filter + application lookup (composite)
        ['application_deployment_queues', ['status', 'application_id'], 'idx_deploy_queues_status_application'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns, $indexName]) {
            if (! $this->indexExists($indexName)) {
                $columnList = implode(', ', array_map(fn ($col) => "\"{$col}\"", $columns));
                DB::statement("CREATE INDEX \"{$indexName}\" ON \"{$table}\" ({$columnList})");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [, , $indexName]) {
            DB::statement("DROP INDEX IF EXISTS \"{$indexName}\"");
        }
    }

    private function indexExists(string $indexName): bool
    {
        $result = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE indexname = ?',
            [$indexName]
        );

        return $result !== null;
    }
};
