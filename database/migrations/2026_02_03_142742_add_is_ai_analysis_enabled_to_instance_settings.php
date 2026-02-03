<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            // AI Code Review - security/quality checks during deployment
            $table->boolean('is_ai_code_review_enabled')->default(false)->after('is_wire_navigate_enabled');
            // AI Error Analysis - analyze deployment failures
            $table->boolean('is_ai_error_analysis_enabled')->default(true)->after('is_ai_code_review_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn(['is_ai_code_review_enabled', 'is_ai_error_analysis_enabled']);
        });
    }
};
