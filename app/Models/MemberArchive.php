<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Visus\Cuid2\Cuid2;

/**
 * @property string $uuid
 */
class MemberArchive extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'team_id',
        'user_id',
        'member_name',
        'member_email',
        'member_role',
        'member_joined_at',
        'kicked_by',
        'kicked_by_name',
        'kick_reason',
        'contribution_summary',
        'access_snapshot',
        'transfer_ids',
        'status',
        'notes',
    ];

    protected $casts = [
        'contribution_summary' => 'array',
        'access_snapshot' => 'array',
        'transfer_ids' => 'array',
        'member_joined_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (! $model->uuid) {
                $model->uuid = (string) new Cuid2;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kickedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kicked_by');
    }

    /**
     * Get related TeamResourceTransfer records by stored IDs.
     */
    public function getTransfers(): \Illuminate\Database\Eloquent\Collection
    {
        $ids = $this->transfer_ids ?? [];

        if (empty($ids)) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return TeamResourceTransfer::whereIn('id', $ids)->get();
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('member_email', $email);
    }
}
