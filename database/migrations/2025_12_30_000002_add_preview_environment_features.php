<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds Railway-style Preview Environment features.
     */
    public function up(): void
    {
        // Add auto-sleep and auto-delete features to previews
        Schema::table('application_previews', function (Blueprint $table) {
            $table->boolean('auto_sleep_enabled')->default(false);
            $table->integer('auto_sleep_after_minutes')->nullable()->default(60);
            $table->boolean('is_sleeping')->default(false);
            $table->timestamp('slept_at')->nullable();

            $table->boolean('auto_delete_enabled')->default(true);
            $table->integer('auto_delete_after_days')->nullable()->default(7);

            $table->timestamp('last_activity_at')->nullable();
        });

        // Add preview-specific settings to application_settings
        Schema::table('application_settings', function (Blueprint $table) {
            $table->boolean('preview_auto_sleep_enabled')->default(false);
            $table->integer('preview_auto_sleep_minutes')->default(60);
            $table->boolean('preview_auto_delete_enabled')->default(true);
            $table->integer('preview_auto_delete_days')->default(7);
            $table->boolean('preview_separate_database')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_previews', function (Blueprint $table) {
            $table->dropColumn([
                'auto_sleep_enabled',
                'auto_sleep_after_minutes',
                'is_sleeping',
                'slept_at',
                'auto_delete_enabled',
                'auto_delete_after_days',
                'last_activity_at',
            ]);
        });

        Schema::table('application_settings', function (Blueprint $table) {
            $table->dropColumn([
                'preview_auto_sleep_enabled',
                'preview_auto_sleep_minutes',
                'preview_auto_delete_enabled',
                'preview_auto_delete_days',
                'preview_separate_database',
            ]);
        });
    }
};
