<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Default true so existing apps are not affected by auto-detection.
            // New apps will explicitly set this to false on creation.
            $table->boolean('build_pack_explicitly_set')->default(true)->after('build_pack');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('build_pack_explicitly_set');
        });
    }
};
