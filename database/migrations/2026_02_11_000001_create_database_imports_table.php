<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('database');
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 20); // remote_pull, file_upload
            $table->string('status', 20)->default('pending'); // pending, in_progress, completed, failed
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('connection_string')->nullable(); // encrypted at model level
            $table->string('source_type', 50)->nullable(); // postgresql, mysql, etc.
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['database_type', 'database_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_imports');
    }
};
