<?php

namespace App\Jobs;

use App\Services\CloudflareProtectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCloudflareRoutesJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 30, 60];

    public $timeout = 120;

    public function middleware(): array
    {
        return [(new WithoutOverlapping('cloudflare-protection-sync'))->expireAfter(120)->dontRelease()];
    }

    public function handle(CloudflareProtectionService $service): void
    {
        if (! $service->isActive()) {
            Log::debug('Cloudflare protection not active, skipping sync.');

            return;
        }

        $service->syncAllRoutes();
    }
}
