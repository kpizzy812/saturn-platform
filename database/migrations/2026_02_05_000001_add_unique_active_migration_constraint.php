<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add a partial unique index to prevent multiple active migrations for the same resource.
     * Only one migration per resource can be in pending, approved, or in_progress state.
     */
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX unique_active_migration_per_source
            ON environment_migrations (source_type, source_id)
            WHERE status IN ('pending', 'approved', 'in_progress')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_active_migration_per_source');
    }
};
