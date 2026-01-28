<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that represent standalone database types.
     */
    private array $tables = [
        'standalone_postgresqls',
        'standalone_mysqls',
        'standalone_mariadbs',
        'standalone_mongodbs',
        'standalone_redis',
        'standalone_keydbs',
        'standalone_dragonflies',
        'standalone_clickhouses',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (! Schema::hasColumn($table, 'allowed_ips')) {
                        $blueprint->text('allowed_ips')->nullable()->after('enable_ssl');
                    }
                    if (! Schema::hasColumn($table, 'storage_limit')) {
                        $blueprint->integer('storage_limit')->default(0)->after('limits_cpus');
                    }
                    if (! Schema::hasColumn($table, 'auto_scaling_enabled')) {
                        $blueprint->boolean('auto_scaling_enabled')->default(false)->after('storage_limit');
                    }
                    if (! Schema::hasColumn($table, 'connection_pool_enabled')) {
                        $blueprint->boolean('connection_pool_enabled')->default(false)->after('auto_scaling_enabled');
                    }
                    if (! Schema::hasColumn($table, 'connection_pool_size')) {
                        $blueprint->integer('connection_pool_size')->default(20)->after('connection_pool_enabled');
                    }
                    if (! Schema::hasColumn($table, 'connection_pool_max')) {
                        $blueprint->integer('connection_pool_max')->default(100)->after('connection_pool_size');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $columns = ['allowed_ips', 'storage_limit', 'auto_scaling_enabled', 'connection_pool_enabled', 'connection_pool_size', 'connection_pool_max'];
                    foreach ($columns as $column) {
                        if (Schema::hasColumn($table, $column)) {
                            $blueprint->dropColumn($column);
                        }
                    }
                });
            }
        }
    }
};
