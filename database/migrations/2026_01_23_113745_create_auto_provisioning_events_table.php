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
        // Add additional auto-provisioning settings to instance_settings
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('auto_provision_max_servers_per_day')->default(3);
            $table->string('auto_provision_server_type')->default('cx22'); // Hetzner: 4GB RAM
            $table->string('auto_provision_location')->default('nbg1'); // Nuremberg
            $table->unsignedSmallInteger('auto_provision_cooldown_minutes')->default(60);
        });

        // Create auto_provisioning_events table for tracking
        Schema::create('auto_provisioning_events', function (Blueprint $table) {
            $table->id();

            // The server that triggered the provisioning (overloaded server)
            $table->foreignId('trigger_server_id')->nullable()->constrained('servers')->nullOnDelete();

            // The newly created server (after provisioning)
            $table->foreignId('provisioned_server_id')->nullable()->constrained('servers')->nullOnDelete();

            // Team that owns this event
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // Why provisioning was triggered
            $table->string('trigger_reason'); // cpu_critical, memory_critical, manual

            // Provider used for provisioning
            $table->string('provider')->default('hetzner');

            // Provider-specific server ID (e.g., Hetzner server ID)
            $table->string('provider_server_id')->nullable();

            // Status of the provisioning
            $table->string('status')->default('pending'); // pending, provisioning, installing, ready, failed

            // Error message if failed
            $table->text('error_message')->nullable();

            // Resource metrics at the time of trigger
            $table->json('trigger_metrics')->nullable(); // {cpu: 87, memory: 92, disk: 45}

            // Server configuration used
            $table->json('server_config')->nullable(); // {type: cx22, location: nbg1, image: ubuntu-24.04}

            // Timestamps
            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamp('provisioned_at')->nullable(); // When VPS was created
            $table->timestamp('ready_at')->nullable(); // When Docker was installed and server is ready
            $table->timestamps();

            // Index for common queries
            $table->index(['team_id', 'status']);
            $table->index(['trigger_server_id', 'triggered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_provisioning_events');

        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'auto_provision_max_servers_per_day',
                'auto_provision_server_type',
                'auto_provision_location',
                'auto_provision_cooldown_minutes',
            ]);
        });
    }
};
