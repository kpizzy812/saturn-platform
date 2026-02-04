<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTaskExecution extends BaseModel
{
    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, scheduled_task_id (relationship)
     */
    protected $fillable = [
        'status',
        'output',
        'error_output',
        'started_at',
        'finished_at',
        'retry_count',
        'duration',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'retry_count' => 'integer',
            'duration' => 'decimal:2',
        ];
    }

    public function scheduledTask(): BelongsTo
    {
        return $this->belongsTo(ScheduledTask::class);
    }
}
