<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            // Resource monitoring thresholds (percentage)
            $table->unsignedTinyInteger('resource_warning_cpu_threshold')->default(70);
            $table->unsignedTinyInteger('resource_critical_cpu_threshold')->default(85);
            $table->unsignedTinyInteger('resource_warning_memory_threshold')->default(75);
            $table->unsignedTinyInteger('resource_critical_memory_threshold')->default(90);
            $table->unsignedTinyInteger('resource_warning_disk_threshold')->default(80);
            $table->unsignedTinyInteger('resource_critical_disk_threshold')->default(95);

            // Resource monitoring settings
            $table->boolean('resource_monitoring_enabled')->default(true);
            $table->unsignedSmallInteger('resource_check_interval_minutes')->default(5);

            // Auto-provisioning settings (for Phase 6)
            $table->boolean('auto_provision_enabled')->default(false);
            $table->string('auto_provision_provider')->nullable(); // hetzner, digitalocean, etc.
            $table->text('auto_provision_api_key')->nullable(); // encrypted
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'resource_warning_cpu_threshold',
                'resource_critical_cpu_threshold',
                'resource_warning_memory_threshold',
                'resource_critical_memory_threshold',
                'resource_warning_disk_threshold',
                'resource_critical_disk_threshold',
                'resource_monitoring_enabled',
                'resource_check_interval_minutes',
                'auto_provision_enabled',
                'auto_provision_provider',
                'auto_provision_api_key',
            ]);
        });
    }
};
