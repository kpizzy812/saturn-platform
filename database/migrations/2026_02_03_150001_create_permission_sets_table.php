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
        Schema::create('permission_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();

            // Scope: team or project
            $table->string('scope_type', 20); // 'team' or 'project'
            $table->unsignedBigInteger('scope_id'); // team_id or project_id

            $table->boolean('is_system')->default(false); // built-in roles cannot be deleted
            $table->unsignedBigInteger('parent_id')->nullable(); // for inheritance

            // Visual
            $table->string('color', 50)->nullable();
            $table->string('icon', 50)->nullable();

            $table->timestamps();

            // Unique name within scope
            $table->unique(['scope_type', 'scope_id', 'slug']);

            $table->index(['scope_type', 'scope_id']);
            $table->index('is_system');

            $table->foreign('parent_id')
                ->references('id')
                ->on('permission_sets')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_sets');
    }
};
