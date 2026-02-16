<?php

namespace App\Models;

use App\Jobs\ConnectProxyToNetworksJob;
use App\Traits\HasSafeStringAttribute;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $network
 * @property int $server_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Server $server
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Application> $applications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StandalonePostgresql> $postgresqls
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StandaloneRedis> $redis
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StandaloneMongodb> $mongodbs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StandaloneMysql> $mysqls
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StandaloneMariadb> $mariadbs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StandaloneKeydb> $keydbs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StandaloneDragonfly> $dragonflies
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StandaloneClickhouse> $clickhouses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Service> $services
 */
class StandaloneDocker extends BaseModel
{
    use HasSafeStringAttribute;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * uuid and server_id are needed for programmatic creation in Server::created boot event.
     */
    protected $fillable = [
        'name',
        'network',
        'uuid',
        'server_id',
    ];

    protected static function boot()
    {
        parent::boot();
        static::created(function ($newStandaloneDocker) {
            $server = $newStandaloneDocker->server;
            instant_remote_process([
                "docker network inspect $newStandaloneDocker->network >/dev/null 2>&1 || docker network create --driver overlay --attachable $newStandaloneDocker->network >/dev/null",
            ], $server, false);
            ConnectProxyToNetworksJob::dispatchSync($server);
        });
    }

    public function applications()
    {
        return $this->morphMany(Application::class, 'destination');
    }

    public function postgresqls()
    {
        return $this->morphMany(StandalonePostgresql::class, 'destination');
    }

    public function redis()
    {
        return $this->morphMany(StandaloneRedis::class, 'destination');
    }

    public function mongodbs()
    {
        return $this->morphMany(StandaloneMongodb::class, 'destination');
    }

    public function mysqls()
    {
        return $this->morphMany(StandaloneMysql::class, 'destination');
    }

    public function mariadbs()
    {
        return $this->morphMany(StandaloneMariadb::class, 'destination');
    }

    public function keydbs()
    {
        return $this->morphMany(StandaloneKeydb::class, 'destination');
    }

    public function dragonflies()
    {
        return $this->morphMany(StandaloneDragonfly::class, 'destination');
    }

    public function clickhouses()
    {
        return $this->morphMany(StandaloneClickhouse::class, 'destination');
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function services()
    {
        return $this->morphMany(Service::class, 'destination');
    }

    public function databases()
    {
        $postgresqls = $this->postgresqls;
        $redis = $this->redis;
        $mongodbs = $this->mongodbs;
        $mysqls = $this->mysqls;
        $mariadbs = $this->mariadbs;

        return $postgresqls->concat($redis)->concat($mongodbs)->concat($mysqls)->concat($mariadbs);
    }

    public function attachedTo()
    {
        return $this->applications->count() > 0 || $this->databases()->count() > 0;
    }
}
