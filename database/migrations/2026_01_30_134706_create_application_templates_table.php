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
        Schema::create('application_templates', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('category')->default('general'); // nodejs, php, python, ruby, go, rust, static, docker
            $table->string('icon')->nullable(); // Icon name or URL
            $table->boolean('is_official')->default(false); // Official Saturn templates
            $table->boolean('is_public')->default(true); // Visible to all users

            // Template configuration (JSON)
            $table->json('config');

            // Metadata
            $table->string('version')->default('1.0.0');
            $table->json('tags')->nullable(); // ['web', 'api', 'fullstack']
            $table->integer('usage_count')->default(0);
            $table->float('rating')->nullable();
            $table->integer('rating_count')->default(0);

            // Ownership (null = system template)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['category', 'is_public']);
            $table->index('is_official');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_templates');
    }
};
