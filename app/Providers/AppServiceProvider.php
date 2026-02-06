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
        $this->configureInfrastructureOverrides();
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

    /**
     * Override config/constants.php SSH and Docker values from InstanceSettings DB.
     * This avoids modifying SshMultiplexingHelper, SshRetryHandler, remoteProcess, etc.
     */
    private function configureInfrastructureOverrides(): void
    {
        try {
            $settings = \App\Models\InstanceSettings::get();
        } catch (\Throwable) {
            return; // Table not yet migrated
        }

        $sshMap = [
            'constants.ssh.mux_enabled' => $settings->ssh_mux_enabled,
            'constants.ssh.mux_persist_time' => $settings->ssh_mux_persist_time,
            'constants.ssh.mux_max_age' => $settings->ssh_mux_max_age,
            'constants.ssh.connection_timeout' => $settings->ssh_connection_timeout,
            'constants.ssh.command_timeout' => $settings->ssh_command_timeout,
            'constants.ssh.max_retries' => $settings->ssh_max_retries,
            'constants.ssh.retry_base_delay' => $settings->ssh_retry_base_delay,
            'constants.ssh.retry_max_delay' => $settings->ssh_retry_max_delay,
        ];

        foreach ($sshMap as $key => $value) {
            if ($value !== null) {
                config([$key => $value]);
            }
        }

        if ($settings->docker_registry_url) {
            config([
                'constants.saturn.registry_url' => $settings->docker_registry_url,
            ]);
        }

        // Override API rate limit
        if ($settings->api_rate_limit !== null) {
            config(['api.rate_limit' => $settings->api_rate_limit]);
        }

        // Override Horizon worker defaults
        $horizonDefaults = [
            'horizon.defaults.s6.balance' => $settings->horizon_balance,
            'horizon.defaults.s6.memory' => $settings->horizon_worker_memory,
            'horizon.defaults.s6.timeout' => $settings->horizon_worker_timeout,
            'horizon.defaults.s6.maxTime' => $settings->horizon_worker_timeout,
            'horizon.defaults.s6.maxJobs' => $settings->horizon_max_jobs,
        ];
        foreach ($horizonDefaults as $key => $value) {
            if ($value !== null) {
                config([$key => $value]);
            }
        }

        // Override Horizon environment processes (production, development, local)
        foreach (['production', 'development', 'local'] as $env) {
            if ($settings->horizon_min_processes !== null) {
                config(["horizon.environments.{$env}.s6.minProcesses" => $settings->horizon_min_processes]);
            }
            if ($settings->horizon_max_processes !== null) {
                config(["horizon.environments.{$env}.s6.maxProcesses" => $settings->horizon_max_processes]);
            }
        }

        // Override Horizon retention
        if ($settings->horizon_trim_recent_minutes !== null) {
            config([
                'horizon.trim.recent' => $settings->horizon_trim_recent_minutes,
                'horizon.trim.pending' => $settings->horizon_trim_recent_minutes,
                'horizon.trim.completed' => $settings->horizon_trim_recent_minutes,
            ]);
        }
        if ($settings->horizon_trim_failed_minutes !== null) {
            config([
                'horizon.trim.recent_failed' => $settings->horizon_trim_failed_minutes,
                'horizon.trim.failed' => $settings->horizon_trim_failed_minutes,
                'horizon.trim.monitored' => $settings->horizon_trim_failed_minutes,
            ]);
        }

        // Override queue wait threshold
        if ($settings->horizon_queue_wait_threshold !== null) {
            config(['horizon.waits.redis:default' => $settings->horizon_queue_wait_threshold]);
        }
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
