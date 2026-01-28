<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change is_public default to true for all standalone database tables
     * and update existing records to enable public access by default.
     */
    public function up(): void
    {
        $tables = [
            'standalone_postgresqls',
            'standalone_mysqls',
            'standalone_redis',
            'standalone_mongodbs',
            'standalone_mariadbs',
            'standalone_keydbs',
            'standalone_dragonflies',
            'standalone_clickhouses',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('is_public')->default(true)->change();
            });

            // Update existing records to enable public access
            DB::table($table)->where('is_public', false)->update(['is_public' => true]);
        }
    }

    public function down(): void
    {
        $tables = [
            'standalone_postgresqls',
            'standalone_mysqls',
            'standalone_redis',
            'standalone_mongodbs',
            'standalone_mariadbs',
            'standalone_keydbs',
            'standalone_dragonflies',
            'standalone_clickhouses',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('is_public')->default(false)->change();
            });
        }
    }
};
