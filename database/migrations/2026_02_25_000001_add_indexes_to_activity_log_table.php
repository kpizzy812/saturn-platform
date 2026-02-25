<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Disable transactions: CREATE INDEX CONCURRENTLY cannot run inside a transaction in PostgreSQL.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     *
     * Adds missing indexes to optimize common query patterns on the activity_log table:
     * - event column: filtered in team activity API (WHERE event = ?)
     * - subject_type + subject_id + created_at: related activities lookup with time ordering
     */
    public function up(): void
    {
        try {
            // Index on 'event' column — used for filtering by action/event type
            // in TeamController::team_activities() and export_team_activities()
            DB::unprepared('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_log_event ON activity_log (event)');

            // Composite index for subject lookups with time ordering — used in
            // ActivityHelper::getRelatedActivities() and Application::get_deployment()
            // The existing 'subject' morphs index covers (subject_type, subject_id)
            // but does not include created_at for ORDER BY optimization.
            DB::unprepared('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_log_subject_created_at ON activity_log (subject_type, subject_id, created_at DESC)');
        } catch (\Exception $e) {
            Log::error('Error adding indexes to activity_log: '.$e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::unprepared('DROP INDEX CONCURRENTLY IF EXISTS idx_activity_log_event');
            DB::unprepared('DROP INDEX CONCURRENTLY IF EXISTS idx_activity_log_subject_created_at');
        } catch (\Exception $e) {
            Log::error('Error dropping indexes from activity_log: '.$e->getMessage());
        }
    }
};
