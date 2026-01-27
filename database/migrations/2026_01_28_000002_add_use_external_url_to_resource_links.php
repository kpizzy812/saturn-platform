<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_links', function (Blueprint $table) {
            $table->boolean('use_external_url')->default(false)->after('auto_inject');
        });
    }

    public function down(): void
    {
        Schema::table('resource_links', function (Blueprint $table) {
            $table->dropColumn('use_external_url');
        });
    }
};
