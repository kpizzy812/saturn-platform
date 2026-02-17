<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Individual log entry for a deployment.
 *
 * PERFORMANCE: Using separate table instead of JSON column eliminates O(N²)
 * complexity when appending logs. Each INSERT is O(1) regardless of log count.
 */
class DeploymentLogEntry extends Model
{
    /**
     * Disable updated_at since logs are append-only.
     */
    public const UPDATED_AT = null;

    protected $table = 'deployment_log_entries';

    protected $fillable = [
        'deployment_id',
        'order',
        'command',
        'output',
        'type',
        'hidden',
        'batch',
        'stage',
    ];

    protected $casts = [
        'hidden' => 'boolean',
        'batch' => 'integer',
        'order' => 'integer',
    ];

    /**
     * Get the deployment this log entry belongs to.
     */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(ApplicationDeploymentQueue::class, 'deployment_id');
    }

    /**
     * Convert to array format compatible with legacy JSON logs.
     */
    public function toLegacyFormat(): array
    {
        return [
            'order' => $this->order,
            'command' => $this->command,
            'output' => $this->output,
            'type' => $this->type,
            'timestamp' => $this->created_at?->toIso8601String(),
            'hidden' => $this->hidden,
            'batch' => $this->batch,
            'stage' => $this->stage,
        ];
    }
}
