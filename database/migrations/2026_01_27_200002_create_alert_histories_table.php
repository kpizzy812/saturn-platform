<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->float('value');
            $table->string('status')->default('triggered'); // triggered, resolved
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_histories');
    }
};
