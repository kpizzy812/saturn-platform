<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Changes application_id from varchar to bigint for proper foreign key compatibility.
     */
    public function up(): void
    {
        // PostgreSQL requires explicit type casting
        if (config('database.default') === 'pgsql') {
            DB::statement('ALTER TABLE application_deployment_queues ALTER COLUMN application_id TYPE bigint USING application_id::bigint');
        } else {
            Schema::table('application_deployment_queues', function (Blueprint $table) {
                $table->bigInteger('application_id')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_deployment_queues', function (Blueprint $table) {
            $table->string('application_id')->change();
        });
    }
};
