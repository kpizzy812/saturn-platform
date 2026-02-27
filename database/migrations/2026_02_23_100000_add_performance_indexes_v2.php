<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Disable transaction wrapping: CREATE INDEX CONCURRENTLY cannot run inside a transaction in PostgreSQL.
     * Without CONCURRENTLY, CREATE INDEX takes an AccessExclusiveLock and blocks all reads/writes
     * for the entire duration — this caused an 11-minute deploy freeze on production.
     */
    public $withinTransaction = false;

    /**
     * Index definitions: [table, columns, index_name]
     *
     * Phase 1 (CRITICAL): applications, environment_variables, scheduled_tasks
     * Phase 2 (HIGH): scheduled_database_backups, alerts, resource_links, team_webhooks, environment_migrations
     * Phase 3 (MEDIUM): alert_histories, code_reviews
     */
    private array $indexes = [
        // applications — FK for listing
        // (status and destination already indexed by Laravel migrations)
        ['applications', ['environment_id'], 'idx_applications_environment_id'],

        // environment_variables uses polymorphic resourceable_type/resourceable_id
        // (already indexed by Laravel migration)

        // scheduled_tasks — job scheduling + cleanup
        ['scheduled_tasks', ['enabled'], 'idx_scheduled_tasks_enabled'],
        ['scheduled_tasks', ['application_id'], 'idx_scheduled_tasks_application_id'],
        ['scheduled_tasks', ['service_id'], 'idx_scheduled_tasks_service_id'],

        // scheduled_database_backups — enabled filter
        // (database_type/database_id already indexed by Laravel migration)
        ['scheduled_database_backups', ['enabled'], 'idx_sched_backups_enabled'],

        // alerts — team-scoped queries
        ['alerts', ['team_id'], 'idx_alerts_team_id'],
        ['alerts', ['team_id', 'enabled'], 'idx_alerts_team_enabled'],

        // resource_links — env-scoped lookups
        // (source/target morph already indexed by Laravel migrations)
        ['resource_links', ['environment_id'], 'idx_resource_links_environment_id'],

        // team_webhooks — team listing
        ['team_webhooks', ['team_id', 'created_at'], 'idx_team_webhooks_team_created'],

        // environment_migrations — status filtering
        // (team_id+status already indexed, adding created_at for ordered listing)
        ['environment_migrations', ['team_id', 'status', 'created_at'], 'idx_env_migrations_team_status_created'],

        // alert_histories — history listing
        ['alert_histories', ['alert_id', 'triggered_at'], 'idx_alert_histories_alert_triggered'],

        // code_reviews — deployment lookups
        // (application_id already covered by unique constraint)
        ['code_reviews', ['deployment_id'], 'idx_code_reviews_deployment_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $columns, $indexName]) {
            if (! $this->indexExists($indexName)) {
                $columnList = implode(', ', array_map(fn ($col) => "\"{$col}\"", $columns));
                // CONCURRENTLY: no AccessExclusiveLock — reads/writes continue during index build.
                DB::unprepared("CREATE INDEX CONCURRENTLY \"{$indexName}\" ON \"{$table}\" ({$columnList})");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [, , $indexName]) {
            DB::unprepared("DROP INDEX CONCURRENTLY IF EXISTS \"{$indexName}\"");
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
