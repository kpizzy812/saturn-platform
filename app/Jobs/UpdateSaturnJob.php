<?php

namespace App\Jobs;

use App\Actions\Server\UpdateSaturn;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateSaturnJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        try {
            CheckForUpdatesJob::dispatchSync();
            $settings = instanceSettings();
            if (! $settings->new_version_available) {
                Log::info('No new version available. Skipping update.');

                return;
            }

            $server = Server::findOrFail(0);

            Log::info('Starting Saturn Platform update process...');
            UpdateSaturn::run(false); // false means it's not a manual update

            $settings->update(['new_version_available' => false]);
            Log::info('Saturn Platform update completed successfully.');
        } catch (\Throwable $e) {
            Log::error('UpdateSaturnJob failed: '.$e->getMessage());
            // Consider implementing a notification to administrators
        }
    }
}
