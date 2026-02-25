<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->integer('max_servers')->nullable()->after('custom_server_limit');
            $table->integer('max_applications')->nullable()->after('max_servers');
            $table->integer('max_databases')->nullable()->after('max_applications');
            $table->integer('max_projects')->nullable()->after('max_databases');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['max_servers', 'max_applications', 'max_databases', 'max_projects']);
        });
    }
};
