<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Add uuid column to application_previews table with backfill.
 *
 * Defensive: skips gracefully if uuid already exists (e.g. fresh installs
 * that ran the original create migration which already included uuid).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Column may already exist on fresh installs — skip if so
        if (Schema::hasColumn('application_previews', 'uuid')) {
            return;
        }

        // Step 1: add as nullable to allow backfill without default
        Schema::table('application_previews', function (Blueprint $table) {
            $table->string('uuid')->nullable()->after('id');
        });

        // Step 2: backfill existing rows with generated UUIDs
        DB::table('application_previews')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('application_previews')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        // Step 3: enforce NOT NULL and unique constraint
        Schema::table('application_previews', function (Blueprint $table) {
            $table->string('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        // Only drop if this migration was the one that created the column.
        // Skip if the column doesn't exist to stay idempotent.
        if (! Schema::hasColumn('application_previews', 'uuid')) {
            return;
        }

        Schema::table('application_previews', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
