<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PullTemplatesFromCDN implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 10;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        // Saturn uses its own curated service templates from git.
        // Do NOT pull from upstream Coolify CDN — it overwrites our
        // fixes (e.g. corrected healthcheck URLs) with upstream bugs.
    }
}
