<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('email');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invites');
    }
};
