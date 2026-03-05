<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add depends_on to applications (JSON array of resource UUIDs)
        Schema::table('applications', function (Blueprint $table) {
            $table->json('depends_on')->nullable()->after('watch_paths');
        });

        // Add watch_paths and depends_on to services
        Schema::table('services', function (Blueprint $table) {
            $table->text('watch_paths')->nullable()->after('compose_parsing_version');
            $table->json('depends_on')->nullable()->after('watch_paths');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('depends_on');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['watch_paths', 'depends_on']);
        });
    }
};
