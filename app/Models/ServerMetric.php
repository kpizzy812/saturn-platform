<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerMetric extends Model
{
    protected $fillable = [
        'server_id',
        'disk_usage_percent',
        'recorded_at',
    ];

    protected $casts = [
        'disk_usage_percent' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function scopeForServer(Builder $query, int $serverId): Builder
    {
        return $query->where('server_id', $serverId);
    }

    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderByDesc('recorded_at');
    }
}
