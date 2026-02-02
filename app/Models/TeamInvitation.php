<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TeamInvitation extends Model
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'team_id',
        'uuid',
        'email',
        'role',
        'link',
        'via',
        'invited_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['email', 'role', 'via'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Set the email attribute to lowercase.
     */
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public static function ownedByCurrentTeam()
    {
        return TeamInvitation::whereTeamId(currentTeam()->id);
    }

    public function isValid()
    {
        $createdAt = $this->created_at;
        $diff = $createdAt->diffInDays(now());
        if ($diff <= config('constants.invitation.link.expiration_days')) {
            return true;
        } else {
            $this->delete();
            $user = User::whereEmail($this->email)->first();
            if (filled($user)) {
                $user->deleteIfNotVerifiedAndForcePasswordReset();
            }

            return false;
        }
    }
}
