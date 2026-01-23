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
        Schema::create('resource_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();

            // Source (usually an application)
            $table->morphs('source'); // source_type, source_id

            // Target (usually a database)
            $table->morphs('target'); // target_type, target_id

            // Which env variable to inject (DATABASE_URL, REDIS_URL, etc.)
            $table->string('inject_as')->nullable();

            // Automatically inject on deploy
            $table->boolean('auto_inject')->default(true);

            $table->timestamps();

            // Unique constraint for the link
            $table->unique(['source_type', 'source_id', 'target_type', 'target_id'], 'unique_resource_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_links');
    }
};
