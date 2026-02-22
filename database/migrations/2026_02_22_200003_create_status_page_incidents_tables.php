<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_page_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('severity', 20)->default('minor');
            $table->string('status', 20)->default('investigating');
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index('status');
            $table->index('started_at');
            $table->index('resolved_at');
        });

        Schema::create('status_page_incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('status_page_incidents')->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('message');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index('incident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_page_incident_updates');
        Schema::dropIfExists('status_page_incidents');
    }
};
