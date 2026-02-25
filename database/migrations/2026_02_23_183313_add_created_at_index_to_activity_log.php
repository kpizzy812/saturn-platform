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

    public function up(): void
    {
        try {
            // Index for time-based cleanup queries: WHERE created_at < ? ORDER BY created_at DESC
            DB::unprepared('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_log_created_at ON activity_log (created_at DESC)');

            // Composite index for causer queries with time ordering (useful for user activity timelines)
            DB::unprepared('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_log_causer_created_at ON activity_log (causer_type, causer_id, created_at DESC)');
        } catch (\Exception $e) {
            Log::error('Error adding indexes to activity_log: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            DB::unprepared('DROP INDEX CONCURRENTLY IF EXISTS idx_activity_log_created_at');
            DB::unprepared('DROP INDEX CONCURRENTLY IF EXISTS idx_activity_log_causer_created_at');
        } catch (\Exception $e) {
            Log::error('Error dropping indexes from activity_log: '.$e->getMessage());
        }
    }
};
