<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_page_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->string('display_name');
            $table->integer('display_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->string('group_name')->nullable();
            $table->timestamps();

            $table->index(['resource_type', 'resource_id']);
            $table->index('team_id');
            $table->index('display_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_page_resources');
    }
};
