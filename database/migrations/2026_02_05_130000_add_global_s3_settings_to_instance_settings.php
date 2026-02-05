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
        Schema::table('instance_settings', function (Blueprint $table) {
            // Global S3 storage for platform-level backups
            $table->boolean('s3_enabled')->default(false);
            $table->string('s3_endpoint')->nullable();
            $table->string('s3_bucket')->nullable();
            $table->string('s3_region')->nullable();
            $table->text('s3_key')->nullable(); // encrypted
            $table->text('s3_secret')->nullable(); // encrypted
            $table->string('s3_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                's3_enabled',
                's3_endpoint',
                's3_bucket',
                's3_region',
                's3_key',
                's3_secret',
                's3_path',
            ]);
        });
    }
};
