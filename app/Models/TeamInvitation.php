<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property-read Team|null $team

 * @property string|null $email
 * @property string|null $invited_by
 * @property array $allowed_projects
 * @property int|null $permission_set_id
 * @property array $custom_permissions
 * @property int|null $team_id
 * @property string|null $role
 * @property \Carbon\Carbon|null $created_at
 * @property string|null $link
 * @property string|null $uuid
 */
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
        'allowed_projects',
        'permission_set_id',
        'custom_permissions',
    ];

    protected function casts(): array
    {
        return [
            'allowed_projects' => 'array',
            'custom_permissions' => 'array',
        ];
    }

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

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Team, $this> */
    public function team(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function inviter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public static function ownedByCurrentTeam()
    {
        return TeamInvitation::whereTeamId(currentTeam()->id);
    }

    public function isValid(): bool
    {
        $diff = $this->created_at->diffInDays(now());

        return $diff <= config('constants.invitation.link.expiration_days');
    }

    /**
     * Delete the invitation if expired and clean up any unverified invited user.
     * Should only be called from scheduled cleanup jobs, not from read-only views.
     */
    public function cleanupIfExpired(): void
    {
        if ($this->isValid()) {
            return;
        }

        $this->delete();

        $user = User::whereEmail($this->email)->first();
        if (filled($user)) {
            $user->deleteIfNotVerifiedAndForcePasswordReset();
        }
    }
}
