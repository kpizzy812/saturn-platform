<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusPageIncidentUpdate extends Model
{
    protected $fillable = [
        'incident_id',
        'status',
        'message',
        'posted_at',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(StatusPageIncident::class, 'incident_id');
    }
}
