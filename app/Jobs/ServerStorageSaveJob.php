<?php

namespace App\Jobs;

use App\Models\LocalFileVolume;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ServerStorageSaveJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public LocalFileVolume $localFileVolume)
    {
        $this->onQueue('high');
    }

    public function handle()
    {
        $this->localFileVolume->saveStorageOnServer();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ServerStorageSaveJob permanently failed', [
            'local_file_volume_id' => $this->localFileVolume->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
