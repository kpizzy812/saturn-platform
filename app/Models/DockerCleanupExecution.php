<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DockerCleanupExecution extends BaseModel
{
    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, server_id (relationship)
     */
    protected $fillable = [
        'status',
        'message',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
