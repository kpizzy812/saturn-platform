<?php

namespace App\Models;

use App\Events\ServerReachabilityChanged;
use App\Notifications\Channels\SendsDiscord;
use App\Notifications\Channels\SendsEmail;
use App\Notifications\Channels\SendsPushover;
use App\Notifications\Channels\SendsSlack;
use App\Traits\Auditable;
use App\Traits\HasNotificationSettings;
use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[OA\Schema(
    description: 'Team model',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', description: 'The unique identifier of the team.'),
        new OA\Property(property: 'name', type: 'string', description: 'The name of the team.'),
        new OA\Property(property: 'description', type: 'string', description: 'The description of the team.'),
        new OA\Property(property: 'personal_team', type: 'boolean', description: 'Whether the team is personal or not.'),
        new OA\Property(property: 'created_at', type: 'string', description: 'The date and time the team was created.'),
        new OA\Property(property: 'updated_at', type: 'string', description: 'The date and time the team was last updated.'),
        new OA\Property(property: 'show_boarding', type: 'boolean', description: 'Whether to show the boarding screen or not.'),
        new OA\Property(property: 'custom_server_limit', type: 'string', description: 'The custom server limit.'),
        new OA\Property(
            property: 'members',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/User'),
            description: 'The members of the team.'
        ),
    ]
)]
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $personal_team
 * @property bool $show_boarding
 * @property string|null $custom_server_limit
 * @property string|null $logo
 * @property string|null $timezone
 * @property string $default_environment
 * @property string|null $workspace_locale
 * @property string|null $workspace_date_format
 * @property int|null $default_project_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read int $limits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Server> $servers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Project> $projects
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $members
 * @property-read \Illuminate\Database\Eloquent\Collection<int, S3Storage> $s3s
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PrivateKey> $privateKeys
 * @property-read Subscription|null $subscription
 * @property-read \App\Models\TeamUser|null $pivot
 */
class Team extends Model implements SendsDiscord, SendsEmail, SendsPushover, SendsSlack
{
    use Auditable, HasFactory, HasNotificationSettings, HasSafeStringAttribute, LogsActivity, Notifiable;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Critical fields (id, personal_team) are excluded - personal_team should not be changed after creation.
     */
    protected $fillable = [
        'name',
        'description',
        'show_boarding',
        'custom_server_limit',
        'logo',
        'timezone',
        'default_environment',
        'workspace_locale',
        'workspace_date_format',
        'default_project_id',
    ];

    protected $casts = [
        'personal_team' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted()
    {
        static::created(function ($team) {
            $team->emailNotificationSettings()->create([
                'use_instance_email_settings' => isDev(),
            ]);
            $team->discordNotificationSettings()->create();
            $team->slackNotificationSettings()->create();
            $team->telegramNotificationSettings()->create();
            $team->pushoverNotificationSettings()->create();
            $team->webhookNotificationSettings()->create();
        });

        static::saving(function ($team) {
            if (auth()->user()?->isMember()) {
                throw new \Exception('You are not allowed to update this team.');
            }
        });

        static::deleting(function ($team) {
            $keys = $team->privateKeys;
            foreach ($keys as $key) {
                $key->delete();
            }
            $sources = $team->sources();
            foreach ($sources as $source) {
                $source->delete();
            }
            $tags = Tag::whereTeamId($team->id)->get();
            foreach ($tags as $tag) {
                $tag->delete();
            }
            $shared_variables = $team->environment_variables();
            foreach ($shared_variables as $shared_variable) {
                $shared_variable->delete();
            }
            $s3s = $team->s3s;
            foreach ($s3s as $s3) {
                $s3->delete();
            }
        });
    }

    public static function serverLimitReached()
    {
        $serverLimit = Team::serverLimit();
        $team = currentTeam();
        $servers = $team->servers->count();

        return $servers >= $serverLimit;
    }

    public function subscriptionPastOverDue()
    {
        if (isCloud()) {
            return $this->subscription?->stripe_past_due;
        }

        return false;
    }

    public function serverOverflow()
    {
        if ($this->serverLimit() < $this->servers->count()) {
            return true;
        }

        return false;
    }

    public static function serverLimit()
    {
        if (currentTeam()->id === 0 && isDev()) {
            return 9999999;
        }
        $team = Team::find(currentTeam()->id);
        if (! $team) {
            return 0;
        }

        return data_get($team, 'limits', 0);
    }

    public function limits(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (config('constants.saturn.self_hosted') || $this->id === 0) {
                    return 999999999999;
                }

                return $this->custom_server_limit ?? 2;
            }
        );
    }

    public function routeNotificationForDiscord()
    {
        return data_get($this, 'discord_webhook_url', null);
    }

    public function routeNotificationForTelegram()
    {
        return [
            'token' => data_get($this, 'telegram_token', null),
            'chat_id' => data_get($this, 'telegram_chat_id', null),
        ];
    }

    public function routeNotificationForSlack()
    {
        return data_get($this, 'slack_webhook_url', null);
    }

    public function routeNotificationForPushover()
    {
        return [
            'user' => data_get($this, 'pushover_user_key', null),
            'token' => data_get($this, 'pushover_api_token', null),
        ];
    }

    public function getRecipients(): array
    {
        $recipients = $this->members()->pluck('email')->toArray();
        $validatedEmails = array_filter($recipients, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        return array_values($validatedEmails);
    }

    public function isAnyNotificationEnabled()
    {
        if (isCloud()) {
            return true;
        }

        return $this->getNotificationSettings('email')?->isEnabled() ||
            $this->getNotificationSettings('discord')?->isEnabled() ||
            $this->getNotificationSettings('slack')?->isEnabled() ||
            $this->getNotificationSettings('telegram')?->isEnabled() ||
            $this->getNotificationSettings('pushover')?->isEnabled();
    }

    public function subscriptionEnded()
    {
        $this->subscription->update([
            'stripe_subscription_id' => null,
            'stripe_cancel_at_period_end' => false,
            'stripe_invoice_paid' => false,
            'stripe_trial_already_ended' => false,
            'stripe_past_due' => false,
        ]);
        foreach ($this->servers as $server) {
            $server->settings()->update([
                'is_usable' => false,
                'is_reachable' => false,
            ]);
            ServerReachabilityChanged::dispatch($server);
        }
    }

    /** @return HasMany<SharedEnvironmentVariable, $this> */
    public function environment_variables(): HasMany
    {
        return $this->hasMany(SharedEnvironmentVariable::class)->whereNull('project_id')->whereNull('environment_id');
    }

    /** @return BelongsToMany<User, $this, TeamUser, 'pivot'> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user', 'team_id', 'user_id')
            ->using(TeamUser::class)
            ->withPivot('role', 'allowed_projects', 'permission_set_id')
            ->withTimestamps();
    }

    /**
     * Get allowed project IDs for a user in this team.
     * Returns null if user has access to all projects.
     *
     * @return array<int>|null
     */
    public function getAllowedProjectsForUser(User $user): ?array
    {
        $membership = $this->members()
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            return [];
        }

        return $membership->pivot?->getAttribute('allowed_projects');
    }

    /**
     * Update allowed projects for a user in this team.
     *
     * @param  array<int>|null  $projectIds  null=all projects
     */
    public function setAllowedProjectsForUser(User $user, ?array $projectIds): void
    {
        $this->members()->updateExistingPivot($user->id, [
            'allowed_projects' => $projectIds,
        ]);
    }

    /**
     * Check if user has restricted project access (not all projects).
     */
    public function userHasRestrictedAccess(User $user): bool
    {
        $allowedProjects = $this->getAllowedProjectsForUser($user);

        return $allowedProjects !== null;
    }

    /** @return HasOne<Subscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /** @return HasManyThrough<Application, Project, $this> */
    public function applications(): HasManyThrough
    {
        return $this->hasManyThrough(Application::class, Project::class);
    }

    /** @return HasMany<TeamInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function isEmpty()
    {
        if ($this->projects()->count() === 0 && $this->servers()->count() === 0 && $this->privateKeys()->count() === 0 && $this->sources()->count() === 0) {
            return true;
        }

        return false;
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Server, $this> */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    /** @return HasMany<PrivateKey, $this> */
    public function privateKeys(): HasMany
    {
        return $this->hasMany(PrivateKey::class);
    }

    /** @return HasMany<CloudProviderToken, $this> */
    public function cloudProviderTokens(): HasMany
    {
        return $this->hasMany(CloudProviderToken::class);
    }

    public function sources()
    {
        $sources = collect([]);
        $github_apps = GithubApp::where(function ($query) {
            $query->where(function ($q) {
                $q->where('team_id', $this->id)
                    ->orWhere('is_system_wide', true);
            })->where('is_public', false);
        })->get();

        $gitlab_apps = GitlabApp::where(function ($query) {
            $query->where(function ($q) {
                $q->where('team_id', $this->id)
                    ->orWhere('is_system_wide', true);
            })->where('is_public', false);
        })->get();

        return $sources->merge($github_apps)->merge($gitlab_apps);
    }

    /** @return HasMany<S3Storage, $this> */
    public function s3s(): HasMany
    {
        return $this->hasMany(S3Storage::class)->where('is_usable', true);
    }

    /** @return HasOne<EmailNotificationSettings, $this> */
    public function emailNotificationSettings(): HasOne
    {
        return $this->hasOne(EmailNotificationSettings::class);
    }

    /** @return HasOne<DiscordNotificationSettings, $this> */
    public function discordNotificationSettings(): HasOne
    {
        return $this->hasOne(DiscordNotificationSettings::class);
    }

    /** @return HasOne<TelegramNotificationSettings, $this> */
    public function telegramNotificationSettings(): HasOne
    {
        return $this->hasOne(TelegramNotificationSettings::class);
    }

    /** @return HasOne<SlackNotificationSettings, $this> */
    public function slackNotificationSettings(): HasOne
    {
        return $this->hasOne(SlackNotificationSettings::class);
    }

    /** @return HasOne<PushoverNotificationSettings, $this> */
    public function pushoverNotificationSettings(): HasOne
    {
        return $this->hasOne(PushoverNotificationSettings::class);
    }

    /** @return HasOne<WebhookNotificationSettings, $this> */
    public function webhookNotificationSettings(): HasOne
    {
        return $this->hasOne(WebhookNotificationSettings::class);
    }

    /** @return HasMany<UserNotification, $this> */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    /** @return HasMany<TeamWebhook, $this> */
    public function webhooks(): HasMany
    {
        return $this->hasMany(TeamWebhook::class);
    }
}
