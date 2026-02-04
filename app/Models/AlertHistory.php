<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertHistory extends Model
{
    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, alert_id (relationship)
     */
    protected $fillable = [
        'triggered_at',
        'resolved_at',
        'value',
        'message',
    ];

    protected $table = 'alert_histories';

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
            'value' => 'float',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }
}
