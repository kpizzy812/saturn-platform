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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('resource', 50); // applications, servers, databases, team, settings, projects, environments
            $table->string('action', 50); // view, create, update, delete, deploy, manage, etc.
            $table->string('category', 50); // resources, team, settings
            $table->boolean('is_sensitive')->default(false); // for env_vars_sensitive and similar
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['resource', 'action']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
