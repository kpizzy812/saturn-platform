<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add smoke test fields to applications table.
     *
     * Smoke test performs an HTTP check from the deployment server to the container
     * after docker healthcheck (or stability check) passes. Unlike docker healthcheck
     * which runs inside the container, smoke test validates the app is reachable
     * from outside via the server's network stack.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->boolean('smoke_test_enabled')->default(false)->after('health_check_start_period');
            $table->string('smoke_test_path')->default('/')->after('smoke_test_enabled');
            $table->unsignedSmallInteger('smoke_test_timeout')->default(30)->after('smoke_test_path');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['smoke_test_enabled', 'smoke_test_path', 'smoke_test_timeout']);
        });
    }
};
