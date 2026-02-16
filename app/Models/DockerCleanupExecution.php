<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $server_id
 * @property string|null $status
 * @property string|null $message
 * @property string|null $cleanup_log
 * @property string|null $finished_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Server $server
 */
class DockerCleanupExecution extends BaseModel
{
    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, uuid (auto-generated)
     */
    protected $fillable = [
        'server_id',
        'status',
        'message',
        'cleanup_log',
        'finished_at',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
