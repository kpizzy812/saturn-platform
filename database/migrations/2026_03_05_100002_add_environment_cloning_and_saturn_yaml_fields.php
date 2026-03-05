<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add cloned_from tracking to environments
        Schema::table('environments', function (Blueprint $table) {
            $table->foreignId('cloned_from_id')->nullable()->after('default_git_branch')
                ->constrained('environments')->nullOnDelete();
        });

        // Add saturn.yaml tracking to environments
        Schema::table('environments', function (Blueprint $table) {
            $table->string('saturn_yaml_hash', 64)->nullable()->after('cloned_from_id');
            $table->timestamp('saturn_yaml_last_synced_at')->nullable()->after('saturn_yaml_hash');
        });

        // Add managed_by_yaml flag to applications
        Schema::table('applications', function (Blueprint $table) {
            $table->boolean('managed_by_yaml')->default(false)->after('depends_on');
            $table->string('yaml_resource_name')->nullable()->after('managed_by_yaml');
        });

        // Add managed_by_yaml flag to services
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('managed_by_yaml')->default(false)->after('depends_on');
            $table->string('yaml_resource_name')->nullable()->after('managed_by_yaml');
        });

        // Add managed_by_yaml to all standalone database types
        $databaseTables = [
            'standalone_postgresqls',
            'standalone_mysqls',
            'standalone_mariadbs',
            'standalone_mongodbs',
            'standalone_redis',
            'standalone_keydbs',
            'standalone_dragonflies',
            'standalone_clickhouses',
        ];

        foreach ($databaseTables as $dbTable) {
            if (Schema::hasTable($dbTable)) {
                Schema::table($dbTable, function (Blueprint $table) {
                    $table->boolean('managed_by_yaml')->default(false)->after('status');
                    $table->string('yaml_resource_name')->nullable()->after('managed_by_yaml');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('environments', function (Blueprint $table) {
            $table->dropForeign(['cloned_from_id']);
            $table->dropColumn(['cloned_from_id', 'saturn_yaml_hash', 'saturn_yaml_last_synced_at']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['managed_by_yaml', 'yaml_resource_name']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['managed_by_yaml', 'yaml_resource_name']);
        });

        $databaseTables = [
            'standalone_postgresqls',
            'standalone_mysqls',
            'standalone_mariadbs',
            'standalone_mongodbs',
            'standalone_redis',
            'standalone_keydbs',
            'standalone_dragonflies',
            'standalone_clickhouses',
        ];

        foreach ($databaseTables as $dbTable) {
            if (Schema::hasTable($dbTable)) {
                Schema::table($dbTable, function (Blueprint $table) {
                    $table->dropColumn(['managed_by_yaml', 'yaml_resource_name']);
                });
            }
        }
    }
};
