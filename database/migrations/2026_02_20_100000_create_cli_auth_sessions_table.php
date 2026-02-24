<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cli_auth_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 9)->unique();
            $table->string('secret', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->text('token_plain')->nullable();
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('secret');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cli_auth_sessions');
    }
};
