<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_settings', function (Blueprint $table) {
            // Resource quotas (null = unlimited)
            $table->unsignedInteger('max_applications')->nullable()->after('default_server_id');
            $table->unsignedInteger('max_services')->nullable()->after('max_applications');
            $table->unsignedInteger('max_databases')->nullable()->after('max_services');
            $table->unsignedInteger('max_environments')->nullable()->after('max_databases');
        });
    }

    public function down(): void
    {
        Schema::table('project_settings', function (Blueprint $table) {
            $table->dropColumn(['max_applications', 'max_services', 'max_databases', 'max_environments']);
        });
    }
};
