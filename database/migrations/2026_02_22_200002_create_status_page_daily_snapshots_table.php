<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_page_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 20);
            $table->unsignedBigInteger('resource_id');
            $table->date('snapshot_date');
            $table->string('status', 20)->default('no_data');
            $table->float('uptime_percent')->default(0);
            $table->unsignedInteger('total_checks')->default(0);
            $table->unsignedInteger('healthy_checks')->default(0);
            $table->unsignedInteger('degraded_checks')->default(0);
            $table->unsignedInteger('down_checks')->default(0);
            $table->timestamps();

            $table->unique(['resource_type', 'resource_id', 'snapshot_date'], 'snapshot_resource_date_unique');
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_page_daily_snapshots');
    }
};
