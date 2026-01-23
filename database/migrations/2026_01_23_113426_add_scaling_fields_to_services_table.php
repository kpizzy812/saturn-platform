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
        Schema::table('services', function (Blueprint $table) {
            // Resource limits (applied to all containers in the service)
            $table->string('limits_memory')->default('0')->after('config_hash');
            $table->string('limits_memory_swap')->default('0')->after('limits_memory');
            $table->integer('limits_memory_swappiness')->default(60)->after('limits_memory_swap');
            $table->string('limits_memory_reservation')->default('0')->after('limits_memory_swappiness');
            $table->string('limits_cpus')->default('0')->after('limits_memory_reservation');
            $table->string('limits_cpuset')->nullable()->after('limits_cpus');
            $table->integer('limits_cpu_shares')->default(1024)->after('limits_cpuset');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'limits_memory',
                'limits_memory_swap',
                'limits_memory_swappiness',
                'limits_memory_reservation',
                'limits_cpus',
                'limits_cpuset',
                'limits_cpu_shares',
            ]);
        });
    }
};
