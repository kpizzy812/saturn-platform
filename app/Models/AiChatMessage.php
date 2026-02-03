<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $session_id
 * @property string $role
 * @property string $content
 * @property string|null $intent
 * @property array|null $intent_params
 * @property string|null $command_status
 * @property string|null $command_result
 * @property int|null $rating
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read AiChatSession $session
 * @property-read AiUsageLog|null $usageLog
 */
class AiChatMessage extends Model
{
    protected $fillable = [
        'uuid',
        'session_id',
        'role',
        'content',
        'intent',
        'intent_params',
        'command_status',
        'command_result',
        'rating',
    ];

    protected $casts = [
        'intent_params' => 'array',
        'rating' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiChatMessage $message): void {
            if (empty($message->uuid)) {
                $message->uuid = (string) Str::uuid();
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
     * Session this message belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }

    /**
     * Usage log for this message.
     */
    public function usageLog(): HasOne
    {
        return $this->hasOne(AiUsageLog::class, 'message_id');
    }

    /**
     * Check if message is from user.
     */
    public function isFromUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Check if message is from assistant.
     */
    public function isFromAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Check if message is a system message.
     */
    public function isSystem(): bool
    {
        return $this->role === 'system';
    }

    /**
     * Check if message has a command.
     */
    public function hasCommand(): bool
    {
        return ! empty($this->intent);
    }

    /**
     * Check if command is pending.
     */
    public function isCommandPending(): bool
    {
        return $this->command_status === 'pending';
    }

    /**
     * Check if command is executing.
     */
    public function isCommandExecuting(): bool
    {
        return $this->command_status === 'executing';
    }

    /**
     * Check if command completed.
     */
    public function isCommandCompleted(): bool
    {
        return $this->command_status === 'completed';
    }

    /**
     * Check if command failed.
     */
    public function isCommandFailed(): bool
    {
        return $this->command_status === 'failed';
    }

    /**
     * Update command status.
     */
    public function updateCommandStatus(string $status, ?string $result = null): bool
    {
        $data = ['command_status' => $status];
        if ($result !== null) {
            $data['command_result'] = $result;
        }

        return $this->update($data);
    }

    /**
     * Rate this message.
     */
    public function rate(int $rating): bool
    {
        if ($rating < 1 || $rating > 5) {
            return false;
        }

        return $this->update(['rating' => $rating]);
    }

    /**
     * Get intent label.
     */
    public function getIntentLabelAttribute(): ?string
    {
        return match ($this->intent) {
            'deploy' => 'Deploy',
            'restart' => 'Restart',
            'stop' => 'Stop',
            'start' => 'Start',
            'logs' => 'View Logs',
            'status' => 'Check Status',
            'help' => 'Help',
            default => $this->intent ? ucfirst($this->intent) : null,
        };
    }

    /**
     * Get command status color.
     */
    public function getCommandStatusColorAttribute(): string
    {
        return match ($this->command_status) {
            'pending' => 'yellow',
            'executing' => 'blue',
            'completed' => 'green',
            'failed' => 'red',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Scope to messages with commands.
     */
    public function scopeWithCommands($query)
    {
        return $query->whereNotNull('intent');
    }

    /**
     * Scope to rated messages.
     */
    public function scopeRated($query)
    {
        return $query->whereNotNull('rating');
    }

    /**
     * Scope to messages by role.
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}
