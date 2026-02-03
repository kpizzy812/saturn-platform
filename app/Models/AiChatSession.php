<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $team_id
 * @property string|null $context_type
 * @property int|null $context_id
 * @property string|null $context_name
 * @property string|null $title
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $user
 * @property-read Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection|AiChatMessage[] $messages
 * @property-read Model|null $context
 */
class AiChatSession extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',
        'context_type',
        'context_id',
        'context_name',
        'title',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiChatSession $session): void {
            if (empty($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * User who owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Team the session belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Messages in this session.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'session_id')->orderBy('created_at');
    }

    /**
     * Get the context resource (polymorphic).
     */
    public function context(): MorphTo
    {
        return $this->morphTo('context', 'context_type', 'context_id');
    }

    /**
     * Resolve context model class from type.
     */
    public function getContextModelClass(): ?string
    {
        return match ($this->context_type) {
            'application' => Application::class,
            'server' => Server::class,
            'database' => StandalonePostgresql::class, // Or use polymorphic
            'service' => Service::class,
            'project' => Project::class,
            'environment' => Environment::class,
            default => null,
        };
    }

    /**
     * Load the context model.
     */
    public function loadContext(): ?Model
    {
        if (! $this->context_type || ! $this->context_id) {
            return null;
        }

        $modelClass = $this->getContextModelClass();
        if (! $modelClass) {
            return null;
        }

        return $modelClass::find($this->context_id);
    }

    /**
     * Check if session is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if session is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Archive the session.
     */
    public function archive(): bool
    {
        return $this->update(['status' => 'archived']);
    }

    /**
     * Get the last message.
     */
    public function getLastMessageAttribute(): ?AiChatMessage
    {
        return $this->messages()->latest()->first();
    }

    /**
     * Generate title from first user message.
     */
    public function generateTitle(): void
    {
        if ($this->title) {
            return;
        }

        $firstMessage = $this->messages()->where('role', 'user')->first();
        if ($firstMessage) {
            $this->update([
                'title' => Str::limit($firstMessage->content, 50),
            ]);
        }
    }

    /**
     * Scope to active sessions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to user's sessions.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to team's sessions.
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope to sessions with specific context.
     */
    public function scopeForContext($query, string $type, int $id)
    {
        return $query->where('context_type', $type)->where('context_id', $id);
    }
}
