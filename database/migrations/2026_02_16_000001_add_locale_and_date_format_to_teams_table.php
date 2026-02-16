<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('workspace_locale')->nullable()->after('default_environment');
            $table->string('workspace_date_format')->nullable()->after('workspace_locale');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['workspace_locale', 'workspace_date_format']);
        });
    }
};
