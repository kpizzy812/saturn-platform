<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds platform_role field to users table for platform-level authorization.
     * Roles: owner (platform owner), admin (platform admin), member (regular user), viewer (read-only)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('platform_role', 20)->default('member')->after('email');
        });

        // Migrate existing data:
        // - Root user (id=0) becomes platform owner
        // - Users with is_superadmin=true become platform admin
        DB::statement("UPDATE users SET platform_role = 'owner' WHERE id = 0");
        DB::statement("UPDATE users SET platform_role = 'admin' WHERE is_superadmin = true AND id != 0");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('platform_role');
        });
    }
};
