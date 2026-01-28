<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix resource_links morph type columns to use full class names.
     * Old format: 'application', 'postgresql', etc.
     * New format: 'App\Models\Application', 'App\Models\StandalonePostgresql', etc.
     */
    public function up(): void
    {
        $typeMap = [
            'application' => 'App\\Models\\Application',
            'postgresql' => 'App\\Models\\StandalonePostgresql',
            'mysql' => 'App\\Models\\StandaloneMysql',
            'mariadb' => 'App\\Models\\StandaloneMariadb',
            'redis' => 'App\\Models\\StandaloneRedis',
            'keydb' => 'App\\Models\\StandaloneKeydb',
            'dragonfly' => 'App\\Models\\StandaloneDragonfly',
            'mongodb' => 'App\\Models\\StandaloneMongodb',
            'clickhouse' => 'App\\Models\\StandaloneClickhouse',
        ];

        foreach ($typeMap as $shortName => $className) {
            // Fix source_type
            DB::table('resource_links')
                ->where('source_type', $shortName)
                ->update(['source_type' => $className]);

            // Fix target_type
            DB::table('resource_links')
                ->where('target_type', $shortName)
                ->update(['target_type' => $className]);
        }
    }

    /**
     * Reverse the migration (convert back to short names).
     */
    public function down(): void
    {
        $typeMap = [
            'App\\Models\\Application' => 'application',
            'App\\Models\\StandalonePostgresql' => 'postgresql',
            'App\\Models\\StandaloneMysql' => 'mysql',
            'App\\Models\\StandaloneMariadb' => 'mariadb',
            'App\\Models\\StandaloneRedis' => 'redis',
            'App\\Models\\StandaloneKeydb' => 'keydb',
            'App\\Models\\StandaloneDragonfly' => 'dragonfly',
            'App\\Models\\StandaloneMongodb' => 'mongodb',
            'App\\Models\\StandaloneClickhouse' => 'clickhouse',
        ];

        foreach ($typeMap as $className => $shortName) {
            DB::table('resource_links')
                ->where('source_type', $className)
                ->update(['source_type' => $shortName]);

            DB::table('resource_links')
                ->where('target_type', $className)
                ->update(['target_type' => $shortName]);
        }
    }
};
