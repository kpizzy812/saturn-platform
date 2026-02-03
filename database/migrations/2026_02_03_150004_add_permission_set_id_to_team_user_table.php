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
        Schema::table('team_user', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_set_id')->nullable()->after('allowed_projects');

            $table->foreign('permission_set_id')
                ->references('id')
                ->on('permission_sets')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_user', function (Blueprint $table) {
            $table->dropForeign(['permission_set_id']);
            $table->dropColumn('permission_set_id');
        });
    }
};
