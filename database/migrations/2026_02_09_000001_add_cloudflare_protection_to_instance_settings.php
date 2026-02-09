<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->text('cloudflare_api_token')->nullable();
            $table->string('cloudflare_account_id')->nullable();
            $table->string('cloudflare_zone_id')->nullable();
            $table->string('cloudflare_tunnel_id')->nullable();
            $table->text('cloudflare_tunnel_token')->nullable();
            $table->boolean('is_cloudflare_protection_enabled')->default(false);
            $table->timestamp('cloudflare_last_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'cloudflare_api_token',
                'cloudflare_account_id',
                'cloudflare_zone_id',
                'cloudflare_tunnel_id',
                'cloudflare_tunnel_token',
                'is_cloudflare_protection_enabled',
                'cloudflare_last_synced_at',
            ]);
        });
    }
};
