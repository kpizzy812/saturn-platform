<?php

namespace Database\Seeders;

use App\Models\GithubApp;
use App\Models\GitlabApp;
use App\Models\InstanceSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * RailwaySeeder - Minimal seeder for Railway deployment
 *
 * Creates essential records needed for Saturn Platform to run on Railway:
 * - InstanceSettings ID=0
 * - Root User ID=0 (required by HorizonServiceProvider, TelescopeServiceProvider)
 * - Root Team ID=0
 * - team_user pivot linking User 0 to Team 0
 * - Public GitHub/GitLab apps
 */
class RailwaySeeder extends Seeder
{
    public function run(): void
    {
        echo "=== Railway Seeder ===\n";

        $this->createInstanceSettings();
        $this->createRootUser();
        $this->createRootTeam();
        $this->linkUserToTeam();
        $this->createPublicGitApps();

        echo "=== Railway Seeder Complete ===\n";
    }

    private function createInstanceSettings(): void
    {
        try {
            if (InstanceSettings::find(0) === null) {
                InstanceSettings::create([
                    'id' => 0,
                    'is_registration_enabled' => true,
                    'is_api_enabled' => false,
                ]);
                echo "  Created InstanceSettings ID=0\n";
            } else {
                echo "  InstanceSettings ID=0 already exists\n";
            }
        } catch (\Exception $e) {
            echo "  InstanceSettings error: {$e->getMessage()}\n";
        }
    }

    private function createRootUser(): void
    {
        try {
            if (User::find(0) !== null) {
                echo "  Root User ID=0 already exists\n";

                return;
            }

            $email = env('ROOT_USER_EMAIL', 'admin@example.com');
            $password = env('ROOT_USER_PASSWORD', 'SaturnRailway123!');
            $name = env('ROOT_USERNAME', 'Root User');

            User::create([
                'id' => 0,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            echo "  Created Root User ID=0 ({$email})\n";
        } catch (\Exception $e) {
            echo "  Root User error: {$e->getMessage()}\n";
        }
    }

    private function createRootTeam(): void
    {
        try {
            if (Team::find(0) !== null) {
                echo "  Root Team ID=0 already exists\n";

                return;
            }

            // Disable the saving check temporarily by creating directly
            DB::table('teams')->insert([
                'id' => 0,
                'name' => 'Root Team',
                'personal_team' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "  Created Root Team ID=0\n";
        } catch (\Exception $e) {
            echo "  Root Team error: {$e->getMessage()}\n";
        }
    }

    private function linkUserToTeam(): void
    {
        try {
            $exists = DB::table('team_user')
                ->where('user_id', 0)
                ->where('team_id', 0)
                ->first();

            if ($exists !== null) {
                echo "  User-Team link already exists\n";

                return;
            }

            DB::table('team_user')->insert([
                'user_id' => 0,
                'team_id' => 0,
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Set current_team_id for root user
            User::where('id', 0)->update(['current_team_id' => 0]);

            echo "  Linked User ID=0 to Team ID=0 as owner\n";
        } catch (\Exception $e) {
            echo "  User-Team link error: {$e->getMessage()}\n";
        }
    }

    private function createPublicGitApps(): void
    {
        try {
            if (GithubApp::find(0) === null) {
                GithubApp::create([
                    'id' => 0,
                    'name' => 'Public GitHub',
                    'api_url' => 'https://api.github.com',
                    'html_url' => 'https://github.com',
                    'is_public' => true,
                    'team_id' => 0,
                ]);
                echo "  Created Public GitHub App\n";
            }
        } catch (\Exception $e) {
            echo "  GithubApp error: {$e->getMessage()}\n";
        }

        try {
            if (GitlabApp::find(0) === null) {
                GitlabApp::create([
                    'id' => 0,
                    'name' => 'Public GitLab',
                    'api_url' => 'https://gitlab.com/api/v4',
                    'html_url' => 'https://gitlab.com',
                    'is_public' => true,
                    'team_id' => 0,
                ]);
                echo "  Created Public GitLab App\n";
            }
        } catch (\Exception $e) {
            echo "  GitlabApp error: {$e->getMessage()}\n";
        }
    }
}
