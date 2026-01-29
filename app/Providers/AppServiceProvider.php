<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;
use Laravel\Telescope\TelescopeServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (App::isLocal()) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        $this->configureCommands();
        $this->configureModels();
        $this->configurePasswords();
        $this->configureSanctumModel();
        $this->configureGitHubHttp();
        $this->ensureStorageLink();
    }

    /**
     * Ensure storage symlink exists for public file access.
     * Auto-creates the symlink if missing (avatars, logos, etc.)
     */
    private function ensureStorageLink(): void
    {
        $publicStorage = public_path('storage');

        if (! File::exists($publicStorage)) {
            try {
                File::link(storage_path('app/public'), $publicStorage);
            } catch (\Exception $e) {
                // Silently fail - might be running in read-only environment or CLI
            }
        }
    }

    private function configureCommands(): void
    {
        if (App::isProduction()) {
            DB::prohibitDestructiveCommands();
        }
    }

    private function configureModels(): void
    {
        // Disabled because it's causing issues with the application
        // Model::shouldBeStrict();
    }

    private function configurePasswords(): void
    {
        Password::defaults(function () {
            return App::isProduction()
                ? Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : Password::min(8)->letters();
        });
    }

    private function configureSanctumModel(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    private function configureGitHubHttp(): void
    {
        Http::macro('GitHub', function (string $api_url, ?string $github_access_token = null) {
            if ($github_access_token) {
                return Http::withHeaders([
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'Accept' => 'application/vnd.github.v3+json',
                    'Authorization' => "Bearer $github_access_token",
                ])->baseUrl($api_url);
            } else {
                return Http::withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                ])->baseUrl($api_url);
            }
        });
    }
}
