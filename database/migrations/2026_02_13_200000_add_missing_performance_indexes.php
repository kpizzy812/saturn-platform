<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_deployment_queues', function (Blueprint $table) {
            $table->index('pull_request_id');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->index('ip');
        });
    }

    public function down(): void
    {
        Schema::table('application_deployment_queues', function (Blueprint $table) {
            $table->dropIndex(['pull_request_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropIndex(['ip']);
        });
    }
};
