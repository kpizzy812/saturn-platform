<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PlatformInvite extends Model
{
    protected $fillable = [
        'uuid',
        'email',
        'created_by',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invite) {
            if (empty($invite->uuid)) {
                $invite->uuid = Str::uuid()->toString();
            }
            $invite->email = strtolower($invite->email);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        if ($this->used_at) {
            return false;
        }

        $expirationDays = config('constants.invitation.link.expiration_days', 7);

        return $this->created_at->diffInDays(now()) <= $expirationDays;
    }

    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public function getLink(): string
    {
        return url("/register?platform_invite={$this->uuid}");
    }
}
