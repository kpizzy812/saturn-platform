<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates deployment_approvals table for production deployment approval workflow.
     */
    public function up(): void
    {
        Schema::create('deployment_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique(); // Using string for CUID2 (not standard UUID format)
            $table->foreignId('application_deployment_queue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['application_deployment_queue_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployment_approvals');
    }
};
