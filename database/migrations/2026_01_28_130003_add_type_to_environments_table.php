<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds environment type and approval settings for deployment control.
     * Types: development, uat, production
     */
    public function up(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->string('type', 20)->default('development')->after('name');
            $table->boolean('requires_approval')->default(false)->after('type');
        });

        // Migrate existing data: environments named 'production' get type='production' and requires_approval=true
        DB::statement("UPDATE environments SET type = 'production', requires_approval = true WHERE LOWER(name) = 'production'");
        DB::statement("UPDATE environments SET type = 'uat' WHERE LOWER(name) IN ('uat', 'staging', 'stage', 'qa')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->dropColumn(['type', 'requires_approval']);
        });
    }
};
