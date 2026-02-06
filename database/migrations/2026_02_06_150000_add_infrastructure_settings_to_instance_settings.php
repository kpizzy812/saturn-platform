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
            // SSH Configuration
            $table->boolean('ssh_mux_enabled')->default(true);
            $table->integer('ssh_mux_persist_time')->default(1800);    // 30 minutes
            $table->integer('ssh_mux_max_age')->default(3600);         // 1 hour
            $table->integer('ssh_connection_timeout')->default(30);
            $table->integer('ssh_command_timeout')->default(3600);
            $table->integer('ssh_max_retries')->default(3);
            $table->integer('ssh_retry_base_delay')->default(2);
            $table->integer('ssh_retry_max_delay')->default(30);

            // Docker Registry
            $table->string('docker_registry_url')->default('ghcr.io');
            $table->text('docker_registry_username')->nullable(); // encrypted
            $table->text('docker_registry_password')->nullable(); // encrypted

            // Default Proxy Type
            $table->string('default_proxy_type')->default('TRAEFIK');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ssh_mux_enabled',
                'ssh_mux_persist_time',
                'ssh_mux_max_age',
                'ssh_connection_timeout',
                'ssh_command_timeout',
                'ssh_max_retries',
                'ssh_retry_base_delay',
                'ssh_retry_max_delay',
                'docker_registry_url',
                'docker_registry_username',
                'docker_registry_password',
                'default_proxy_type',
            ]);
        });
    }
};
