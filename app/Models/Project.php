<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\ClearsGlobalSearchCache;
use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Visus\Cuid2\Cuid2;

#[OA\Schema(
    description: 'Project model',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'uuid', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(
            property: 'environments',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Environment'),
            description: 'The environments of the project.'
        ),
    ]
)]
/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Environment> $environments
 * @property-read ProjectSetting|null $settings
 * @property-read Team|null $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SharedEnvironmentVariable> $environment_variables
 */
class Project extends BaseModel
{
    use Auditable;
    use ClearsGlobalSearchCache;
    use HasFactory;
    use HasSafeStringAttribute;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     * SECURITY: Using $fillable to prevent mass assignment vulnerabilities.
     */
    protected $fillable = [
        'uuid',
        'name',
        'description',
        'team_id',
        'is_archived',
        'archived_at',
        'archived_by',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'is_archived'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get query builder for projects owned by current team.
     * Applies project access restrictions for non-admin users.
     * If you need all projects without further query chaining, use ownedByCurrentTeamCached() instead.
     */
    public static function ownedByCurrentTeam()
    {
        $user = auth()->user();
        $team = currentTeam();

        $query = Project::whereTeamId($team->id)->orderByRaw('LOWER(name)');

        // Return all projects if no authenticated user
        if (! $user) {
            return $query;
        }

        // Super admin sees all projects
        if ($user->isSuperAdmin()) {
            return $query;
        }

        // Get team membership
        $teamMembership = $user->teams()->where('team_id', $team->id)->first();

        if (! $teamMembership) {
            // User not in team - return empty result
            return $query->whereRaw('1 = 0');
        }

        // Owner/Admin always see all projects
        if (in_array($teamMembership->pivot->getAttribute('role'), ['owner', 'admin'])) {
            return $query;
        }

        // Check allowed_projects restriction
        $allowedProjects = $teamMembership->pivot->getAttribute('allowed_projects');

        // null means all projects (default behavior)
        if ($allowedProjects === null) {
            return $query;
        }

        // Filter to allowed projects only
        if (empty($allowedProjects)) {
            // Empty array means no access
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $allowedProjects);
    }

    /**
     * Get all projects owned by current team (cached for request duration).
     */
    public static function ownedByCurrentTeamCached()
    {
        return once(function () {
            return Project::ownedByCurrentTeam()->get();
        });
    }

    protected static function booted()
    {
        static::created(function ($project) {
            ProjectSetting::firstOrCreate([
                'project_id' => $project->id,
            ]);

            // Create all three standard environments (use firstOrCreate to avoid duplicates)
            Environment::firstOrCreate(
                ['name' => 'development', 'project_id' => $project->id],
                [
                    'type' => 'development',
                    'uuid' => (string) new Cuid2,
                    'requires_approval' => false,
                ]
            );

            Environment::firstOrCreate(
                ['name' => 'uat', 'project_id' => $project->id],
                [
                    'type' => 'uat',
                    'uuid' => (string) new Cuid2,
                    'requires_approval' => false,
                ]
            );

            Environment::firstOrCreate(
                ['name' => 'production', 'project_id' => $project->id],
                [
                    'type' => 'production',
                    'uuid' => (string) new Cuid2,
                    'requires_approval' => true,
                ]
            );
        });
        static::deleting(function ($project) {
            $project->tags()->detach();
            $project->environments()->delete();
            $project->settings()->delete();
            $shared_variables = $project->environment_variables();
            foreach ($shared_variables as $shared_variable) {
                $shared_variable->delete();
            }
        });
    }

    /** @return HasMany<SharedEnvironmentVariable, $this> */
    public function environment_variables(): HasMany
    {
        return $this->hasMany(SharedEnvironmentVariable::class);
    }

    /** @return HasMany<Environment, $this> */
    public function environments(): HasMany
    {
        return $this->hasMany(Environment::class);
    }

    /** @return HasOne<ProjectSetting, $this> */
    public function settings(): HasOne
    {
        return $this->hasOne(ProjectSetting::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    /** @return BelongsTo<User, $this> */
    public function archivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /** @return MorphToMany<Tag, $this> */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /** @return HasOne<ProjectNotificationOverride, $this> */
    public function notificationOverrides(): HasOne
    {
        return $this->hasOne(ProjectNotificationOverride::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get users who are members of this project (via project_user pivot).
     */
    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot(['role', 'environment_permissions'])
            ->withTimestamps();
    }

    /**
     * Get project admins (owner and admin roles).
     */
    public function admins()
    {
        return $this->members()->wherePivotIn('role', ['owner', 'admin']);
    }

    /**
     * Get project owners.
     */
    public function owners()
    {
        return $this->members()->wherePivot('role', 'owner');
    }

    /**
     * Get all users who can approve deployments for this project.
     * Includes project admins/owners and team admins/owners.
     *
     * @return \Illuminate\Support\Collection<User>
     */
    public function getApprovers(): \Illuminate\Support\Collection
    {
        // Get project admins/owners
        $projectApprovers = $this->admins()->get();

        // Get team admins/owners
        $teamApprovers = $this->team->members()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get();

        // Merge and deduplicate
        return $projectApprovers->merge($teamApprovers)->unique('id');
    }

    /**
     * Add a user as a member of this project.
     */
    public function addMember(User $user, string $role = 'developer', ?array $envPermissions = null): void
    {
        $this->members()->attach($user->id, [
            'role' => $role,
            'environment_permissions' => $envPermissions ? json_encode($envPermissions) : null,
        ]);
    }

    /**
     * Remove a user from this project.
     */
    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }

    /**
     * Update a member's role in this project.
     */
    public function updateMemberRole(User $user, string $role): void
    {
        $this->members()->updateExistingPivot($user->id, ['role' => $role]);
    }

    /**
     * Check if a user is a member of this project (directly or via team).
     */
    public function hasMember(User $user): bool
    {
        // Check direct project membership
        if ($this->members()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Fallback to team membership
        return $this->team->members()->where('user_id', $user->id)->exists();
    }

    /** @return HasManyThrough<Service, Environment, $this> */
    public function services(): HasManyThrough
    {
        return $this->hasManyThrough(Service::class, Environment::class);
    }

    /** @return HasManyThrough<Application, Environment, $this> */
    public function applications(): HasManyThrough
    {
        return $this->hasManyThrough(Application::class, Environment::class);
    }

    /** @return HasManyThrough<StandalonePostgresql, Environment, $this> */
    public function postgresqls(): HasManyThrough
    {
        return $this->hasManyThrough(StandalonePostgresql::class, Environment::class);
    }

    /** @return HasManyThrough<StandaloneRedis, Environment, $this> */
    public function redis(): HasManyThrough
    {
        return $this->hasManyThrough(StandaloneRedis::class, Environment::class);
    }

    /** @return HasManyThrough<StandaloneKeydb, Environment, $this> */
    public function keydbs(): HasManyThrough
    {
        return $this->hasManyThrough(StandaloneKeydb::class, Environment::class);
    }

    /** @return HasManyThrough<StandaloneDragonfly, Environment, $this> */
    public function dragonflies(): HasManyThrough
    {
        return $this->hasManyThrough(StandaloneDragonfly::class, Environment::class);
    }

    /** @return HasManyThrough<StandaloneClickhouse, Environment, $this> */
    public function clickhouses(): HasManyThrough
    {
        return $this->hasManyThrough(StandaloneClickhouse::class, Environment::class);
    }

    /** @return HasManyThrough<StandaloneMongodb, Environment, $this> */
    public function mongodbs(): HasManyThrough
    {
        return $this->hasManyThrough(StandaloneMongodb::class, Environment::class);
    }

    /** @return HasManyThrough<StandaloneMysql, Environment, $this> */
    public function mysqls(): HasManyThrough
    {
        return $this->hasManyThrough(StandaloneMysql::class, Environment::class);
    }

    /** @return HasManyThrough<StandaloneMariadb, Environment, $this> */
    public function mariadbs(): HasManyThrough
    {
        return $this->hasManyThrough(StandaloneMariadb::class, Environment::class);
    }

    /**
     * Check if project has no resources.
     * PERFORMANCE: Using exists() instead of count() - stops at first record found.
     */
    public function isEmpty()
    {
        return ! $this->applications()->exists() &&
            ! $this->redis()->exists() &&
            ! $this->postgresqls()->exists() &&
            ! $this->mysqls()->exists() &&
            ! $this->keydbs()->exists() &&
            ! $this->dragonflies()->exists() &&
            ! $this->clickhouses()->exists() &&
            ! $this->mariadbs()->exists() &&
            ! $this->mongodbs()->exists() &&
            ! $this->services()->exists();
    }

    public function databases()
    {
        return collect()
            ->merge($this->postgresqls()->get())
            ->merge($this->redis()->get())
            ->merge($this->mongodbs()->get())
            ->merge($this->mysqls()->get())
            ->merge($this->mariadbs()->get())
            ->merge($this->keydbs()->get())
            ->merge($this->dragonflies()->get())
            ->merge($this->clickhouses()->get());
    }

    public function navigateTo()
    {
        if ($this->environments->count() === 1) {
            return route('project.resource.index', [
                'project_uuid' => $this->uuid,
                'environment_uuid' => $this->environments->first()->uuid,
            ]);
        }

        return route('project.show', ['project_uuid' => $this->uuid]);
    }
}
