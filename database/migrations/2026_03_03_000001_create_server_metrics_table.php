<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->onDelete('cascade');
            $table->float('disk_usage_percent');
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['server_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
