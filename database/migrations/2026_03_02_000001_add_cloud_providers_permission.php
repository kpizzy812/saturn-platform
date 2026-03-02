<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->upsert(
            [
                [
                    'key' => 'settings.cloud_providers',
                    'name' => 'Manage Cloud Providers',
                    'description' => 'Add and manage cloud provider API tokens (Hetzner, DigitalOcean) for server provisioning',
                    'resource' => 'settings',
                    'action' => 'cloud_providers',
                    'category' => 'settings',
                    'is_sensitive' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['key'],
            ['name', 'description', 'resource', 'action', 'category', 'is_sensitive', 'updated_at']
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'settings.cloud_providers')->delete();
    }
};
