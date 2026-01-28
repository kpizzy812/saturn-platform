<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove duplicate server_settings entries, keeping the one with is_reachable=true
        // or the oldest one if none are reachable
        DB::statement('
            DELETE FROM server_settings ss1
            WHERE EXISTS (
                SELECT 1 FROM server_settings ss2
                WHERE ss2.server_id = ss1.server_id
                AND ss2.id != ss1.id
                AND (
                    (ss2.is_reachable = true AND ss1.is_reachable = false)
                    OR (ss2.is_reachable = ss1.is_reachable AND ss2.id < ss1.id)
                )
            )
        ');

        // Add unique constraint if it doesn't exist
        $indexExists = DB::select("
            SELECT 1 FROM pg_indexes
            WHERE tablename = 'server_settings'
            AND indexname = 'server_settings_server_id_unique'
        ");

        if (empty($indexExists)) {
            Schema::table('server_settings', function (Blueprint $table) {
                $table->unique('server_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_settings', function (Blueprint $table) {
            $table->dropUnique(['server_id']);
        });
    }
};
