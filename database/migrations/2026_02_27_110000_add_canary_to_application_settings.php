<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_settings', function (Blueprint $table) {
            $table->boolean('canary_enabled')->default(false)->after('rollback_consecutive_failures');
            $table->json('canary_steps')->nullable()->after('canary_enabled')
                ->comment('Array of traffic percentages for canary steps, e.g. [10,25,50,100]');
            $table->unsignedSmallInteger('canary_step_minutes')->default(5)->after('canary_steps')
                ->comment('Minutes to wait between canary traffic steps');
            $table->boolean('canary_auto_promote')->default(true)->after('canary_step_minutes')
                ->comment('Automatically promote canary to 100% when all steps pass');
            $table->unsignedTinyInteger('canary_error_rate_threshold')->default(5)->after('canary_auto_promote')
                ->comment('HTTP 5xx error percentage that triggers canary rollback');
        });
    }

    public function down(): void
    {
        Schema::table('application_settings', function (Blueprint $table) {
            $table->dropColumn([
                'canary_enabled',
                'canary_steps',
                'canary_step_minutes',
                'canary_auto_promote',
                'canary_error_rate_threshold',
            ]);
        });
    }
};
