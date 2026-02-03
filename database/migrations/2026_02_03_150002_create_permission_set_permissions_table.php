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
        Schema::create('permission_set_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('permission_set_id');
            $table->unsignedBigInteger('permission_id');

            // Environment-level restrictions (JSON)
            // Example: {"production": false, "staging": true}
            // null means permission applies to all environments
            $table->json('environment_restrictions')->nullable();

            $table->timestamps();

            $table->unique(['permission_set_id', 'permission_id'], 'psp_set_permission_unique');

            $table->foreign('permission_set_id')
                ->references('id')
                ->on('permission_sets')
                ->cascadeOnDelete();

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_set_permissions');
    }
};
