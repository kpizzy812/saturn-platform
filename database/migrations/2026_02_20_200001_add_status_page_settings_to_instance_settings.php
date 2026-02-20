<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->boolean('is_status_page_enabled')->default(false)->after('is_registration_enabled');
            $table->string('status_page_title')->nullable()->after('is_status_page_enabled');
            $table->text('status_page_description')->nullable()->after('status_page_title');
        });
    }

    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn(['is_status_page_enabled', 'status_page_title', 'status_page_description']);
        });
    }
};
