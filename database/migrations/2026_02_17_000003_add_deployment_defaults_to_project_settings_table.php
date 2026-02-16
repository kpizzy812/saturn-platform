<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_settings', function (Blueprint $table) {
            $table->string('default_build_pack')->nullable()->after('max_environments');
            $table->string('default_git_branch')->nullable()->after('default_build_pack');
            $table->boolean('default_auto_deploy')->nullable()->after('default_git_branch');
            $table->boolean('default_force_https')->nullable()->after('default_auto_deploy');
            $table->boolean('default_preview_deployments')->nullable()->after('default_force_https');
            $table->boolean('default_auto_rollback')->nullable()->after('default_preview_deployments');
        });
    }

    public function down(): void
    {
        Schema::table('project_settings', function (Blueprint $table) {
            $table->dropColumn([
                'default_build_pack',
                'default_git_branch',
                'default_auto_deploy',
                'default_force_https',
                'default_preview_deployments',
                'default_auto_rollback',
            ]);
        });
    }
};
