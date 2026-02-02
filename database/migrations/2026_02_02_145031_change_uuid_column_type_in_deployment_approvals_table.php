<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change uuid column from PostgreSQL uuid type to varchar(255)
     * because we use CUID2 which is not a valid UUID format.
     */
    public function up(): void
    {
        // PostgreSQL requires explicit USING clause for type conversion
        DB::statement('ALTER TABLE deployment_approvals ALTER COLUMN uuid TYPE VARCHAR(255) USING uuid::text');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This will fail if any existing values are not valid UUIDs
        DB::statement('ALTER TABLE deployment_approvals ALTER COLUMN uuid TYPE UUID USING uuid::uuid');
    }
};
