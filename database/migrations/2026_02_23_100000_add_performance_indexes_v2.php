<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Index definitions: [table, columns, index_name]
     *
     * Phase 1 (CRITICAL): applications, environment_variables, scheduled_tasks
     * Phase 2 (HIGH): scheduled_database_backups, alerts, resource_links, team_webhooks, environment_migrations
     * Phase 3 (MEDIUM): alert_histories, code_reviews
     */
    private array $indexes = [
        // applications — FK + status for listing/filtering
        ['applications', ['environment_id'], 'idx_applications_environment_id'],
        ['applications', ['status'], 'idx_applications_status'],
        ['applications', ['destination_type', 'destination_id'], 'idx_applications_destination'],

        // environment_variables — FK lookups on every deploy
        ['environment_variables', ['application_id'], 'idx_env_vars_application_id'],
        ['environment_variables', ['service_id'], 'idx_env_vars_service_id'],

        // scheduled_tasks — job scheduling + cleanup
        ['scheduled_tasks', ['enabled'], 'idx_scheduled_tasks_enabled'],
        ['scheduled_tasks', ['application_id'], 'idx_scheduled_tasks_application_id'],
        ['scheduled_tasks', ['service_id'], 'idx_scheduled_tasks_service_id'],

        // scheduled_database_backups — morph + enabled filter
        ['scheduled_database_backups', ['enabled'], 'idx_sched_backups_enabled'],
        ['scheduled_database_backups', ['database_type', 'database_id'], 'idx_sched_backups_database'],

        // alerts — team-scoped queries
        ['alerts', ['team_id'], 'idx_alerts_team_id'],
        ['alerts', ['team_id', 'enabled'], 'idx_alerts_team_enabled'],

        // resource_links — env-scoped + morph lookups
        ['resource_links', ['environment_id'], 'idx_resource_links_environment_id'],
        ['resource_links', ['source_type', 'source_id'], 'idx_resource_links_source'],
        ['resource_links', ['target_type', 'target_id'], 'idx_resource_links_target'],

        // team_webhooks — team listing
        ['team_webhooks', ['team_id', 'created_at'], 'idx_team_webhooks_team_created'],

        // environment_migrations — status filtering
        ['environment_migrations', ['team_id', 'status', 'created_at'], 'idx_env_migrations_team_status_created'],

        // alert_histories — history listing
        ['alert_histories', ['alert_id', 'triggered_at'], 'idx_alert_histories_alert_triggered'],

        // code_reviews — deployment + app lookups
        ['code_reviews', ['deployment_id'], 'idx_code_reviews_deployment_id'],
        ['code_reviews', ['application_id'], 'idx_code_reviews_application_id'],
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
