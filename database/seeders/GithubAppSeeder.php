<?php

namespace Database\Seeders;

use App\Models\GithubApp;
use Illuminate\Database\Seeder;

class GithubAppSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GithubApp::create([
            'id' => 0,
            'uuid' => 'github-public',
            'name' => 'Public GitHub',
            'api_url' => 'https://api.github.com',
            'html_url' => 'https://github.com',
            'is_public' => true,
            'team_id' => 0,
        ]);
        // Dev GitHub App — credentials must be set via environment variables (never hardcoded)
        $appId = env('GITHUB_APP_ID');
        $installationId = env('GITHUB_APP_INSTALLATION_ID');
        $clientId = env('GITHUB_APP_CLIENT_ID');
        $clientSecret = env('GITHUB_APP_CLIENT_SECRET');
        $webhookSecret = env('GITHUB_APP_WEBHOOK_SECRET');

        if ($appId && $clientId && $clientSecret) {
            GithubApp::create([
                'name' => 'saturn-laravel-dev-public',
                'uuid' => 'github-app',
                'organization' => env('GITHUB_APP_ORGANIZATION', 'saturnplatform'),
                'api_url' => 'https://api.github.com',
                'html_url' => 'https://github.com',
                'is_public' => false,
                'app_id' => (int) $appId,
                'installation_id' => $installationId ? (int) $installationId : null,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'webhook_secret' => $webhookSecret,
                'private_key_id' => 2,
                'team_id' => 0,
            ]);
        }
    }
}
