<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->string('app_default_build_pack')->default('nixpacks');
            $table->integer('app_default_build_timeout')->default(3600);
            $table->string('app_default_static_image')->default('nginx:alpine');
            $table->boolean('app_default_requires_approval')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'app_default_build_pack',
                'app_default_build_timeout',
                'app_default_static_image',
                'app_default_requires_approval',
            ]);
        });
    }
};
