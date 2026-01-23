<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TeamWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'url',
        'secret',
        'events',
        'enabled',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'url' => 'encrypted',
            'secret' => 'encrypted',
            'events' => 'array',
            'enabled' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($webhook) {
            if (empty($webhook->uuid)) {
                $webhook->uuid = Str::uuid()->toString();
            }
            if (empty($webhook->secret)) {
                $webhook->secret = 'whsec_'.Str::random(32);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->orderBy('created_at', 'desc');
    }

    public function recentDeliveries(int $limit = 10): HasMany
    {
        return $this->deliveries()->limit($limit);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function hasEvent(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    /**
     * Get available webhook events.
     */
    public static function availableEvents(): array
    {
        return [
            [
                'value' => 'deploy.started',
                'label' => 'Deploy Started',
                'description' => 'When a deployment begins',
            ],
            [
                'value' => 'deploy.finished',
                'label' => 'Deploy Finished',
                'description' => 'When a deployment completes successfully',
            ],
            [
                'value' => 'deploy.failed',
                'label' => 'Deploy Failed',
                'description' => 'When a deployment fails',
            ],
            [
                'value' => 'service.created',
                'label' => 'Service Created',
                'description' => 'When a new service is created',
            ],
            [
                'value' => 'service.deleted',
                'label' => 'Service Deleted',
                'description' => 'When a service is deleted',
            ],
            [
                'value' => 'database.backup',
                'label' => 'Database Backup',
                'description' => 'When a database backup completes',
            ],
            [
                'value' => 'server.reachable',
                'label' => 'Server Reachable',
                'description' => 'When a server becomes reachable',
            ],
            [
                'value' => 'server.unreachable',
                'label' => 'Server Unreachable',
                'description' => 'When a server becomes unreachable',
            ],
        ];
    }
}
