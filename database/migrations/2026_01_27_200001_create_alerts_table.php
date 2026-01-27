<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('metric'); // cpu, memory, disk, error_rate, response_time
            $table->string('condition')->default('>'); // >, <, =
            $table->float('threshold');
            $table->integer('duration')->default(5); // minutes
            $table->boolean('enabled')->default(true);
            $table->json('channels')->nullable(); // ['email', 'slack', 'discord', ...]
            $table->integer('triggered_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
