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
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->json('allowed_projects')->nullable()->after('invited_by');
            $table->foreignId('permission_set_id')->nullable()->after('allowed_projects')
                ->constrained('permission_sets')->nullOnDelete();
            $table->json('custom_permissions')->nullable()->after('permission_set_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropForeign(['permission_set_id']);
            $table->dropColumn(['allowed_projects', 'permission_set_id', 'custom_permissions']);
        });
    }
};
