<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates migration_history table for tracking configuration versions during migrations.
     */
    public function up(): void
    {
        Schema::create('migration_history', function (Blueprint $table) {
            $table->id();

            // Resource being tracked (Application, Service, Database)
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->index(['resource_type', 'resource_id']);

            // Link to the migration that created this version
            $table->foreignId('environment_migration_id')->constrained()->cascadeOnDelete();

            // Version hash for quick comparison
            $table->string('version_hash', 64);

            // Full configuration snapshot
            // Stores: all model attributes, env vars, volumes, persistent storage config
            $table->json('config_snapshot');

            // Source environment at time of migration
            $table->string('source_environment_type')->nullable(); // development, uat, production

            $table->timestamps();

            // Index for version lookups
            $table->index(['resource_type', 'resource_id', 'version_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('migration_history');
    }
};
