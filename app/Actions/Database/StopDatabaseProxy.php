<?php

namespace App\Actions\Database;

use App\Events\DatabaseProxyStopped;
use App\Models\ServiceDatabase;
use App\Models\StandaloneClickhouse;
use App\Models\StandaloneDragonfly;
use App\Models\StandaloneKeydb;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use App\Models\StandaloneRedis;
use Lorisleiva\Actions\Concerns\AsAction;

class StopDatabaseProxy
{
    use AsAction;

    public string $jobQueue = 'high';

    public function handle(StandaloneRedis|StandalonePostgresql|StandaloneMongodb|StandaloneMysql|StandaloneMariadb|StandaloneKeydb|ServiceDatabase|StandaloneDragonfly|StandaloneClickhouse $database)
    {
        $server = data_get($database, 'destination.server');
        $uuid = $database->uuid;
        if ($database->getMorphClass() === \App\Models\ServiceDatabase::class) {
            $uuid = $database->service->uuid;
            $server = data_get($database, 'service.server');
        }
        $configuration_dir = database_proxy_dir($uuid);
        $projectName = "db-proxy-{$uuid}";

        // Try docker compose down first (clean shutdown), fall back to docker rm
        instant_remote_process([
            "docker compose --project-name {$projectName} --project-directory {$configuration_dir} down 2>/dev/null || docker rm -f {$uuid}-proxy",
        ], $server);

        $database->save();

        DatabaseProxyStopped::dispatch();

    }
}
