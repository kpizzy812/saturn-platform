<?php

namespace App\Actions\Database;

use App\Events\DatabaseStatusChanged;
use App\Jobs\ServerCheckJob;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Lorisleiva\Actions\Concerns\AsAction;

class StartDatabase
{
    use AsAction;

    public string $jobQueue = 'high';

    public function handle(StandaloneRedis|StandalonePostgresql|StandaloneMongodb|StandaloneMysql|StandaloneMariadb|StandaloneKeydb|StandaloneDragonfly|StandaloneClickhouse $database)
    {
        $server = $database->destination->server;
        if (! $server->isFunctional()) {
            return 'Server is not functional';
        }
        switch ($database->getMorphClass()) {
            case \App\Models\StandalonePostgresql::class:
                $activity = StartPostgresql::run($database);
                break;
            case \App\Models\StandaloneRedis::class:
                $activity = StartRedis::run($database);
                break;
            case \App\Models\StandaloneMongodb::class:
                $activity = StartMongodb::run($database);
                break;
            case \App\Models\StandaloneMysql::class:
                $activity = StartMysql::run($database);
                break;
            case \App\Models\StandaloneMariadb::class:
                $activity = StartMariadb::run($database);
                break;
            case \App\Models\StandaloneKeydb::class:
                $activity = StartKeydb::run($database);
                break;
            case \App\Models\StandaloneDragonfly::class:
                $activity = StartDragonfly::run($database);
                break;
            case \App\Models\StandaloneClickhouse::class:
                $activity = StartClickhouse::run($database);
                break;
        }

        // Dispatch WebSocket event immediately so UI shows 'starting' status
        $teamId = $database->environment?->project?->team?->id;
        if ($teamId) {
            DatabaseStatusChanged::dispatch($database->id, 'starting', $teamId);
        }

        if ($database->is_public && $database->public_port) {
            // Delay proxy start to give the database container time to be created
            // by the SaturnTask (remote_process is async — dispatches SSH commands to queue).
            // The proxy's nginx needs the DB container to exist for DNS resolution.
            StartDatabaseProxy::dispatch($database)->delay(now()->addSeconds(15));
        }

        // Schedule a delayed status check to update the database status in real-time
        // after the container has started and passed its healthcheck
        ServerCheckJob::dispatch($server)->delay(now()->addSeconds(20));

        return $activity;
    }
}
