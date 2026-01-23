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
        Schema::table('user_notification_preferences', function (Blueprint $table) {
            // Security-specific notification preferences
            $table->boolean('security_new_login')->default(true)->after('in_app_security');
            $table->boolean('security_failed_login')->default(true)->after('security_new_login');
            $table->boolean('security_api_access')->default(false)->after('security_failed_login');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'security_new_login',
                'security_failed_login',
                'security_api_access',
            ]);
        });
    }
};
