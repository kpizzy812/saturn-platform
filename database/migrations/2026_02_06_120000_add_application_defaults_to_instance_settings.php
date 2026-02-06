<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add global application defaults to instance_settings.
     * These values are applied when creating new applications.
     */
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            // Deployment defaults
            $table->boolean('app_default_auto_deploy')->default(true);
            $table->boolean('app_default_force_https')->default(true);
            $table->boolean('app_default_preview_deployments')->default(false);
            $table->boolean('app_default_pr_deployments_public')->default(false);

            // Build defaults
            $table->boolean('app_default_git_submodules')->default(true);
            $table->boolean('app_default_git_lfs')->default(true);
            $table->boolean('app_default_git_shallow_clone')->default(true);
            $table->boolean('app_default_use_build_secrets')->default(false);
            $table->boolean('app_default_inject_build_args')->default(true);
            $table->boolean('app_default_include_commit_in_build')->default(false);

            // Docker
            $table->integer('app_default_docker_images_to_keep')->default(2);

            // Auto-rollback
            $table->boolean('app_default_auto_rollback')->default(false);
            $table->integer('app_default_rollback_validation_sec')->default(300);
            $table->integer('app_default_rollback_max_restarts')->default(3);
            $table->boolean('app_default_rollback_on_health_fail')->default(true);
            $table->boolean('app_default_rollback_on_crash_loop')->default(true);

            // Debug
            $table->boolean('app_default_debug')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'app_default_auto_deploy',
                'app_default_force_https',
                'app_default_preview_deployments',
                'app_default_pr_deployments_public',
                'app_default_git_submodules',
                'app_default_git_lfs',
                'app_default_git_shallow_clone',
                'app_default_use_build_secrets',
                'app_default_inject_build_args',
                'app_default_include_commit_in_build',
                'app_default_docker_images_to_keep',
                'app_default_auto_rollback',
                'app_default_rollback_validation_sec',
                'app_default_rollback_max_restarts',
                'app_default_rollback_on_health_fail',
                'app_default_rollback_on_crash_loop',
                'app_default_debug',
            ]);
        });
    }
};
