<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_deployment_queues', function (Blueprint $table) {
            $table->boolean('is_promotion')->default(false)->after('rollback');
            $table->string('promoted_from_image')->nullable()->after('is_promotion');
        });
    }

    public function down(): void
    {
        Schema::table('application_deployment_queues', function (Blueprint $table) {
            $table->dropColumn(['is_promotion', 'promoted_from_image']);
        });
    }
};
