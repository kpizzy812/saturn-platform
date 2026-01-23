<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'team_webhook_id',
        'event',
        'status',
        'status_code',
        'payload',
        'response',
        'response_time_ms',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status_code' => 'integer',
            'response_time_ms' => 'integer',
            'attempts' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($delivery) {
            if (empty($delivery->uuid)) {
                $delivery->uuid = Str::uuid()->toString();
            }
        });
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(TeamWebhook::class, 'team_webhook_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markAsSuccess(int $statusCode, ?string $response = null, ?int $responseTimeMs = null): void
    {
        $this->update([
            'status' => 'success',
            'status_code' => $statusCode,
            'response' => $response,
            'response_time_ms' => $responseTimeMs,
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markAsFailed(int $statusCode, ?string $response = null, ?int $responseTimeMs = null): void
    {
        $this->update([
            'status' => 'failed',
            'status_code' => $statusCode,
            'response' => $response,
            'response_time_ms' => $responseTimeMs,
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }
}
