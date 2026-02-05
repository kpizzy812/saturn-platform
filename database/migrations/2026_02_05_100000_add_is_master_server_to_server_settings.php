<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_settings', function (Blueprint $table) {
            $table->boolean('is_master_server')->default(false)->after('is_build_server');
        });

        // Set master flag for the platform host server (id=0)
        DB::table('server_settings')
            ->where('server_id', 0)
            ->update(['is_master_server' => true]);
    }

    public function down(): void
    {
        Schema::table('server_settings', function (Blueprint $table) {
            $table->dropColumn('is_master_server');
        });
    }
};
