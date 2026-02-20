<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\ServiceApplication;
use App\Models\ServiceDatabase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ServerFilesFromServerJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ServiceApplication|ServiceDatabase|Application $resource)
    {
        $this->onQueue('high');
    }

    public function handle()
    {
        $this->resource->getFilesFromServer(isInit: true);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ServerFilesFromServerJob permanently failed', [
            'resource_type' => get_class($this->resource),
            'resource_id' => $this->resource->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
