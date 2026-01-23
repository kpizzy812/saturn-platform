<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Email notification preferences
            $table->boolean('email_deployments')->default(true);
            $table->boolean('email_team')->default(true);
            $table->boolean('email_billing')->default(true);
            $table->boolean('email_security')->default(true);

            // In-app notification preferences
            $table->boolean('in_app_deployments')->default(true);
            $table->boolean('in_app_team')->default(true);
            $table->boolean('in_app_billing')->default(true);
            $table->boolean('in_app_security')->default(true);

            // Email digest frequency: instant, daily, weekly
            $table->string('digest_frequency')->default('instant');

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
