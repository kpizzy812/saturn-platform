<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add application_type column to support worker processes (no HTTP port).
     * Values: 'web' (default), 'worker', 'both'
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('application_type')->default('web')->after('build_pack');
            $table->string('ports_exposes')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('application_type');
            $table->string('ports_exposes')->nullable(false)->default(null)->change();
        });
    }
};
