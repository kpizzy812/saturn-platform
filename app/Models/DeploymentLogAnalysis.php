<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $deployment_id
 * @property string|null $root_cause
 * @property string|null $root_cause_details
 * @property array|null $solution
 * @property array|null $prevention
 * @property string $error_category
 * @property string $severity
 * @property float $confidence
 * @property string|null $provider
 * @property string|null $model
 * @property int|null $tokens_used
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string $error_hash
 * @property string $status
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read ApplicationDeploymentQueue $deployment
 */
class DeploymentLogAnalysis extends Model
{
    protected $fillable = [
        'deployment_id',
        'root_cause',
        'root_cause_details',
        'solution',
        'prevention',
        'error_category',
        'severity',
        'confidence',
        'provider',
        'model',
        'tokens_used',
        'input_tokens',
        'output_tokens',
        'error_hash',
        'status',
        'error_message',
    ];

    protected $casts = [
        'solution' => 'array',
        'prevention' => 'array',
        'confidence' => 'float',
        'tokens_used' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
    ];

    protected $attributes = [
        'status' => 'pending',
        'error_category' => 'unknown',
        'severity' => 'medium',
        'confidence' => 0.0,
    ];

    /**
     * Get the deployment this analysis belongs to.
     */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(ApplicationDeploymentQueue::class, 'deployment_id');
    }

    /**
     * Check if analysis is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if analysis failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if analysis is in progress.
     */
    public function isAnalyzing(): bool
    {
        return $this->status === 'analyzing';
    }

    /**
     * Get severity badge color.
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray',
        };
    }

    /**
     * Get error category label.
     */
    public function getCategoryLabelAttribute(): string
    {
        return match ($this->error_category) {
            'dockerfile' => 'Dockerfile Issue',
            'dependency' => 'Dependency Error',
            'build' => 'Build Failure',
            'runtime' => 'Runtime Error',
            'network' => 'Network Issue',
            'resource' => 'Resource Limit',
            'config' => 'Configuration Error',
            default => 'Unknown',
        };
    }
}
