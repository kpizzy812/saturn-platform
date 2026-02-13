<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Core hierarchy: Team → Project → Environment → Resources

        Schema::table('servers', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
            $table->foreign('private_key_id')->references('id')->on('private_keys')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->cascadeOnDelete();
        });

        Schema::table('environments', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->foreign('environment_id')->references('id')->on('environments')->cascadeOnDelete();
            $table->foreign('private_key_id')->references('id')->on('private_keys')->nullOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreign('environment_id')->references('id')->on('environments')->cascadeOnDelete();
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
        });

        Schema::table('standalone_dockers', function (Blueprint $table) {
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });

        // Standalone database tables → environments
        $standaloneTables = [
            'standalone_postgresqls',
            'standalone_mysqls',
            'standalone_mariadbs',
            'standalone_mongodbs',
            'standalone_redis',
            'standalone_keydbs',
            'standalone_dragonflies',
            'standalone_clickhouses',
        ];

        foreach ($standaloneTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreign('environment_id')->references('id')->on('environments')->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropForeign(['private_key_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });

        Schema::table('environments', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['environment_id']);
            $table->dropForeign(['private_key_id']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['environment_id']);
            $table->dropForeign(['server_id']);
        });

        Schema::table('standalone_dockers', function (Blueprint $table) {
            $table->dropForeign(['server_id']);
        });

        $standaloneTables = [
            'standalone_postgresqls',
            'standalone_mysqls',
            'standalone_mariadbs',
            'standalone_mongodbs',
            'standalone_redis',
            'standalone_keydbs',
            'standalone_dragonflies',
            'standalone_clickhouses',
        ];

        foreach ($standaloneTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['environment_id']);
                });
            }
        }
    }
};
