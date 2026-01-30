<?php

namespace App\Models;

use App\Notifications\Channels\SendsEmail;
use App\Notifications\TransactionalEmails\ResetPassword as TransactionalEmailsResetPassword;
use App\Traits\DeletesUserSessions;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'User model',
    type: 'object',
    properties: [
        'id' => ['type' => 'integer', 'description' => 'The user identifier in the database.'],
        'name' => ['type' => 'string', 'description' => 'The user name.'],
        'email' => ['type' => 'string', 'description' => 'The user email.'],
        'email_verified_at' => ['type' => 'string', 'description' => 'The date when the user email was verified.'],
        'created_at' => ['type' => 'string', 'description' => 'The date when the user was created.'],
        'updated_at' => ['type' => 'string', 'description' => 'The date when the user was updated.'],
        'two_factor_confirmed_at' => ['type' => 'string', 'description' => 'The date when the user two factor was confirmed.'],
        'force_password_reset' => ['type' => 'boolean', 'description' => 'The flag to force the user to reset the password.'],
        'marketing_emails' => ['type' => 'boolean', 'description' => 'The flag to receive marketing emails.'],
    ],
)]
class User extends Authenticatable implements SendsEmail
{
    use DeletesUserSessions, HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'force_password_reset' => 'boolean',
        'show_boarding' => 'boolean',
        'email_change_code_expires_at' => 'datetime',
        'is_superadmin' => 'boolean',
        'platform_role' => 'string',
        'suspended_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    /**
     * Set the email attribute to lowercase.
     */
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * Set the pending_email attribute to lowercase.
     */
    public function setPendingEmailAttribute($value)
    {
        $this->attributes['pending_email'] = $value ? strtolower($value) : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function (User $user) {
            $team = [
                'name' => $user->name."'s Team",
                'personal_team' => true,
                'show_boarding' => true,
            ];
            if ($user->id === 0) {
                $team['id'] = 0;
                $team['name'] = 'Root Team';
            }
            $new_team = Team::create($team);
            $user->teams()->attach($new_team, ['role' => 'owner']);
        });

        static::deleting(function (User $user) {
            DB::transaction(function () use ($user) {
                $teams = $user->teams;
                foreach ($teams as $team) {
                    $user_alone_in_team = $team->members->count() === 1;

                    // Prevent deletion if user is alone in root team
                    if ($team->id === 0 && $user_alone_in_team) {
                        throw new \Exception('User is alone in the root team, cannot delete');
                    }

                    if ($user_alone_in_team) {
                        static::finalizeTeamDeletion($user, $team);
                        // Delete any pending team invitations for this user
                        TeamInvitation::whereEmail($user->email)->delete();

                        continue;
                    }

                    // Load the user's role for this team
                    $userRole = $team->members->where('id', $user->id)->first()?->pivot?->role;

                    if ($userRole === 'owner') {
                        $found_other_owner_or_admin = $team->members->filter(function ($member) use ($user) {
                            return ($member->pivot->role === 'owner' || $member->pivot->role === 'admin') && $member->id !== $user->id;
                        })->first();

                        if ($found_other_owner_or_admin) {
                            $team->members()->detach($user->id);

                            continue;
                        } else {
                            $found_other_member_who_is_not_owner = $team->members->filter(function ($member) {
                                return $member->pivot->role === 'member';
                            })->first();

                            if ($found_other_member_who_is_not_owner) {
                                $found_other_member_who_is_not_owner->pivot->role = 'owner';
                                $found_other_member_who_is_not_owner->pivot->save();
                                $team->members()->detach($user->id);
                            } else {
                                static::finalizeTeamDeletion($user, $team);
                            }

                            continue;
                        }
                    } else {
                        $team->members()->detach($user->id);
                    }
                }
            });
        });
    }

    /**
     * Finalize team deletion by cleaning up all associated resources
     */
    private static function finalizeTeamDeletion(User $user, Team $team)
    {
        $servers = $team->servers;
        foreach ($servers as $server) {
            $resources = $server->definedResources();
            foreach ($resources as $resource) {
                $resource->forceDelete();
            }
            $server->forceDelete();
        }

        $projects = $team->projects;
        foreach ($projects as $project) {
            $project->forceDelete();
        }

        $team->members()->detach($user->id);
        $team->delete();
    }

    /**
     * Delete the user if they are not verified and have a force password reset.
     * This is used to clean up users that have been invited, did not accept the invitation (and did not verify their email and have a force password reset).
     */
    public function deleteIfNotVerifiedAndForcePasswordReset()
    {
        if ($this->hasVerifiedEmail() === false && $this->force_password_reset === true) {
            $this->delete();
        }
    }

    public function recreate_personal_team()
    {
        $team = [
            'name' => $this->name."'s Team",
            'personal_team' => true,
            'show_boarding' => true,
        ];
        if ($this->id === 0) {
            $team['id'] = 0;
            $team['name'] = 'Root Team';
        }
        $new_team = Team::create($team);
        $this->teams()->attach($new_team, ['role' => 'owner']);

        return $new_team;
    }

    public function createToken(string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null)
    {
        $plainTextToken = sprintf(
            '%s%s%s',
            config('sanctum.token_prefix', ''),
            $tokenEntropy = Str::random(40),
            hash('crc32b', $tokenEntropy)
        );

        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
            'team_id' => session('currentTeam')->id,
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)->withPivot('role');
    }

    /**
     * Get projects the user is a member of (via project_user pivot).
     */
    public function projectMemberships()
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->withPivot(['role', 'environment_permissions'])
            ->withTimestamps();
    }

    /**
     * Get the user's platform role.
     */
    public function platformRole(): string
    {
        return $this->platform_role ?? 'member';
    }

    /**
     * Check if user is the platform owner (root user).
     */
    public function isPlatformOwner(): bool
    {
        return $this->id === 0 || $this->platform_role === 'owner';
    }

    /**
     * Check if user is a platform admin (owner or admin).
     */
    public function isPlatformAdmin(): bool
    {
        return in_array($this->platform_role, ['owner', 'admin']);
    }

    /**
     * Get user's role in a specific project.
     */
    public function roleInProject(Project $project): ?string
    {
        $membership = $this->projectMemberships()
            ->where('project_id', $project->id)
            ->first();

        return $membership?->pivot?->role;
    }

    /**
     * Check if user is admin or owner of a project.
     */
    public function isProjectAdmin(Project $project): bool
    {
        $role = $this->roleInProject($project);

        return in_array($role, ['owner', 'admin']);
    }

    /**
     * Check if user can deploy to a specific environment.
     * Takes into account platform role, project role, and environment type.
     */
    public function canDeployToEnvironment(Environment $environment): bool
    {
        // Platform owner/admin can deploy anywhere
        if ($this->isPlatformAdmin() || $this->isSuperAdmin()) {
            return true;
        }

        $project = $environment->project;
        $projectRole = $this->roleInProject($project);

        // If not a project member, check team membership (fallback to team role)
        if (! $projectRole) {
            $teamRole = $this->teams()
                ->where('team_id', $project->team_id)
                ->first()?->pivot?->role;

            // Map team role to project equivalent
            $projectRole = $teamRole;
        }

        if (! $projectRole) {
            return false;
        }

        // Viewers can never deploy
        if ($projectRole === 'viewer') {
            return false;
        }

        // Project owner/admin can deploy anywhere
        if (in_array($projectRole, ['owner', 'admin'])) {
            return true;
        }

        // Check environment type restrictions
        $envType = $environment->type ?? 'development';

        // Development: everyone except viewer can deploy
        if ($envType === 'development') {
            return true;
        }

        // UAT: developer and above can deploy
        if ($envType === 'uat') {
            return in_array($projectRole, ['owner', 'admin', 'developer']);
        }

        // Production: developer can deploy, but may require approval (checked in deployment flow)
        if ($envType === 'production') {
            return in_array($projectRole, ['owner', 'admin', 'developer']);
        }

        return false;
    }

    /**
     * Check if user's deployment requires approval for this environment.
     */
    public function requiresApprovalForEnvironment(Environment $environment): bool
    {
        // Platform admins don't need approval
        if ($this->isPlatformAdmin() || $this->isSuperAdmin()) {
            return false;
        }

        // Environment must require approval
        if (! $environment->requires_approval) {
            return false;
        }

        $project = $environment->project;
        $projectRole = $this->roleInProject($project);

        // Fallback to team role if no project role
        if (! $projectRole) {
            $teamRole = $this->teams()
                ->where('team_id', $project->team_id)
                ->first()?->pivot?->role;
            $projectRole = $teamRole;
        }

        // Owner/admin don't need approval
        if (in_array($projectRole, ['owner', 'admin'])) {
            return false;
        }

        // Developer needs approval for production
        return $projectRole === 'developer' && $environment->type === 'production';
    }

    public function changelogReads()
    {
        return $this->hasMany(UserChangelogRead::class);
    }

    public function getUnreadChangelogCount(): int
    {
        return app(\App\Services\ChangelogService::class)->getUnreadCountForUser($this);
    }

    public function getRecipients(): array
    {
        return [$this->email];
    }

    public function sendVerificationEmail()
    {
        $mail = new MailMessage;
        $url = Url::temporarySignedRoute(
            'verify.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ]
        );
        $mail->view('emails.email-verification', [
            'url' => $url,
        ]);
        $mail->subject('Saturn Platform: Verify your email.');
        send_user_an_email($mail, $this->email);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this?->notify(new TransactionalEmailsResetPassword($token));
    }

    public function isAdmin()
    {
        return $this->role() === 'admin' || $this->role() === 'owner' || $this->role() === 'developer';
    }

    public function isOwner()
    {
        return $this->role() === 'owner';
    }

    public function isMember()
    {
        return $this->role() === 'member';
    }

    public function isDeveloper()
    {
        return $this->role() === 'developer';
    }

    public function isViewer()
    {
        return $this->role() === 'viewer';
    }

    public function isAdminFromSession()
    {
        if (Auth::id() === 0) {
            return true;
        }
        $teams = $this->teams()->get();

        $is_part_of_root_team = $teams->where('id', 0)->first();
        $is_admin_of_root_team = $is_part_of_root_team &&
            ($is_part_of_root_team->pivot->role === 'admin' || $is_part_of_root_team->pivot->role === 'owner');

        if ($is_part_of_root_team && $is_admin_of_root_team) {
            return true;
        }
        $team = $teams->where('id', session('currentTeam')->id)->first();
        $role = data_get($team, 'pivot.role');

        return $role === 'admin' || $role === 'owner';
    }

    public function isInstanceAdmin()
    {
        $found_root_team = Auth::user()->teams->filter(function ($team) {
            if ($team->id == 0) {
                if (! Auth::user()->isAdmin()) {
                    return false;
                }

                return true;
            }

            return false;
        });

        return $found_root_team->count() > 0;
    }

    /**
     * Check if the user is a Super Admin (global admin with is_superadmin flag)
     * This is independent of team roles and grants access to everything
     */
    public function isSuperAdmin(): bool
    {
        if (Auth::id() === 0) {
            return true;
        }

        return $this->is_superadmin === true;
    }

    public function currentTeam()
    {
        return Cache::remember('team:'.Auth::id(), 3600, function () {
            if (is_null(data_get(session('currentTeam'), 'id')) && Auth::user()->teams->count() > 0) {
                return Auth::user()->teams[0];
            }

            return Team::find(session('currentTeam')->id);
        });
    }

    public function otherTeams()
    {
        return Auth::user()->teams->filter(function ($team) {
            return $team->id != currentTeam()->id;
        });
    }

    public function role()
    {
        if (data_get($this, 'pivot')) {
            return $this->pivot->role;
        }
        $user = Auth::user()->teams->where('id', currentTeam()->id)->first();

        return data_get($user, 'pivot.role');
    }

    /**
     * Check if the user is an admin or owner of a specific team
     */
    public function isAdminOfTeam(int $teamId): bool
    {
        $team = $this->teams->where('id', $teamId)->first();

        if (! $team) {
            return false;
        }

        $role = $team->pivot->role ?? null;

        return $role === 'admin' || $role === 'owner';
    }

    /**
     * Check if the user can access system resources (team_id=0)
     * Must be admin/owner of root team
     */
    public function canAccessSystemResources(): bool
    {
        // Check if user is member of root team
        $rootTeam = $this->teams->where('id', 0)->first();

        if (! $rootTeam) {
            return false;
        }

        // Check if user is admin or owner of root team
        return $this->isAdminOfTeam(0);
    }

    /**
     * Check if the user account is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the user account is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Check if the user account is banned
     */
    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    /**
     * Check if the user account is pending (email not verified)
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Suspend the user account
     */
    public function suspend(?string $reason = null, ?int $suspendedBy = null): void
    {
        $this->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspended_by' => $suspendedBy ?? Auth::id(),
            'suspension_reason' => $reason,
        ]);

        // Revoke all active sessions
        $this->deleteOtherSessions();

        // Revoke all API tokens
        $this->tokens()->delete();
    }

    /**
     * Activate/unsuspend the user account
     */
    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ]);
    }

    /**
     * Ban the user account (permanent suspension)
     */
    public function ban(?string $reason = null, ?int $bannedBy = null): void
    {
        $this->update([
            'status' => 'banned',
            'suspended_at' => now(),
            'suspended_by' => $bannedBy ?? Auth::id(),
            'suspension_reason' => $reason,
        ]);

        // Revoke all active sessions
        $this->deleteOtherSessions();

        // Revoke all API tokens
        $this->tokens()->delete();
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->update([
            'last_login_at' => now(),
        ]);
    }

    public function requestEmailChange(string $newEmail): void
    {
        // Generate 6-digit code
        $code = sprintf('%06d', mt_rand(0, 999999));

        // Set expiration using config value
        $expiryMinutes = config('constants.email_change.verification_code_expiry_minutes', 10);
        $expiresAt = Carbon::now()->addMinutes($expiryMinutes);

        $this->update([
            'pending_email' => $newEmail,
            'email_change_code' => $code,
            'email_change_code_expires_at' => $expiresAt,
        ]);

        // Send verification email to new address
        $this->notify(new \App\Notifications\TransactionalEmails\EmailChangeVerification($this, $code, $newEmail, $expiresAt));
    }

    public function isEmailChangeCodeValid(string $code): bool
    {
        return $this->email_change_code === $code
            && $this->email_change_code_expires_at
            && Carbon::now()->lessThan($this->email_change_code_expires_at);
    }

    public function confirmEmailChange(string $code): bool
    {
        if (! $this->isEmailChangeCodeValid($code)) {
            return false;
        }

        $oldEmail = $this->email;
        $newEmail = $this->pending_email;

        // Update email and clear change request fields
        $this->update([
            'email' => $newEmail,
            'pending_email' => null,
            'email_change_code' => null,
            'email_change_code_expires_at' => null,
        ]);

        // For cloud users, dispatch job to update Stripe customer email asynchronously
        if (isCloud() && $this->currentTeam()->subscription) {
            dispatch(new \App\Jobs\UpdateStripeCustomerEmailJob(
                $this->currentTeam(),
                $this->id,
                $newEmail,
                $oldEmail
            ));
        }

        return true;
    }

    public function clearEmailChangeRequest(): void
    {
        $this->update([
            'pending_email' => null,
            'email_change_code' => null,
            'email_change_code_expires_at' => null,
        ]);
    }

    public function hasEmailChangeRequest(): bool
    {
        return ! is_null($this->pending_email)
            && ! is_null($this->email_change_code)
            && $this->email_change_code_expires_at
            && Carbon::now()->lessThan($this->email_change_code_expires_at);
    }

    /**
     * Check if the user has a password set.
     * OAuth users are created without passwords.
     */
    public function hasPassword(): bool
    {
        return ! empty($this->password);
    }
}
