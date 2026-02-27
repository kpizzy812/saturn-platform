<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the default auto-rollback monitoring window from 5 to 30 minutes.
     *
     * 5 minutes is too short to catch memory leaks, gradual degradation, or
     * workload-triggered crashes that happen after initial startup.
     *
     * Also adds error_rate rollback fields for future HTTP-log-based detection.
     *
     * Existing records with value = 300 (old default, never changed by user)
     * are updated to 1800 so existing applications benefit immediately.
     */
    public function up(): void
    {
        Schema::table('application_settings', function (Blueprint $table) {
            $table->boolean('rollback_on_error_rate')->default(false)->after('rollback_on_crash_loop');
            $table->unsignedSmallInteger('rollback_error_rate_threshold')->default(10)->after('rollback_on_error_rate');
            $table->unsignedSmallInteger('rollback_consecutive_failures')->default(2)->after('rollback_error_rate_threshold');
        });

        // Migrate existing rows from old 5-min default to new 30-min default.
        // Only update rows that were never changed from the original 300s default.
        DB::table('application_settings')
            ->where('rollback_validation_seconds', 300)
            ->update(['rollback_validation_seconds' => 1800]);

        // Change the column default for new rows going forward
        Schema::table('application_settings', function (Blueprint $table) {
            $table->integer('rollback_validation_seconds')->default(1800)->change();
        });
    }

    public function down(): void
    {
        Schema::table('application_settings', function (Blueprint $table) {
            $table->dropColumn([
                'rollback_on_error_rate',
                'rollback_error_rate_threshold',
                'rollback_consecutive_failures',
            ]);
            $table->integer('rollback_validation_seconds')->default(300)->change();
        });
    }
};
