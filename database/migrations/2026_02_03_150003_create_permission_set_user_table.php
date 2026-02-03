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
        Schema::create('permission_set_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('permission_set_id');
            $table->unsignedBigInteger('user_id');

            // Scope where this assignment applies
            $table->string('scope_type', 20); // 'team' or 'project'
            $table->unsignedBigInteger('scope_id'); // team_id or project_id

            // Per-user environment overrides (JSON)
            // Example: {"production": false} - deny production even if set allows it
            $table->json('environment_overrides')->nullable();

            $table->timestamps();

            // User can have only one permission set per scope
            $table->unique(['user_id', 'scope_type', 'scope_id'], 'psu_user_scope_unique');

            $table->index(['permission_set_id']);
            $table->index(['user_id']);
            $table->index(['scope_type', 'scope_id']);

            $table->foreign('permission_set_id')
                ->references('id')
                ->on('permission_sets')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_set_user');
    }
};
