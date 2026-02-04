<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\ClearsGlobalSearchCache;
use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Visus\Cuid2\Cuid2;

#[OA\Schema(
    description: 'Project model',
    type: 'object',
    properties: [
        'id' => ['type' => 'integer'],
        'uuid' => ['type' => 'string'],
        'name' => ['type' => 'string'],
        'description' => ['type' => 'string'],
        'environments' => new OA\Property(
            property: 'environments',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Environment'),
            description: 'The environments of the project.'
        ),
    ]
)]
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
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description'])
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
        if (in_array($teamMembership->pivot->role, ['owner', 'admin'])) {
            return $query;
        }

        // Check allowed_projects restriction
        $allowedProjects = $teamMembership->pivot->allowed_projects;

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
            $project->environments()->delete();
            $project->settings()->delete();
            $shared_variables = $project->environment_variables();
            foreach ($shared_variables as $shared_variable) {
                $shared_variable->delete();
            }
        });
    }

    public function environment_variables()
    {
        return $this->hasMany(SharedEnvironmentVariable::class);
    }

    public function environments()
    {
        return $this->hasMany(Environment::class);
    }

    public function settings()
    {
        return $this->hasOne(ProjectSetting::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get users who are members of this project (via project_user pivot).
     */
    public function members()
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

    public function services()
    {
        return $this->hasManyThrough(Service::class, Environment::class);
    }

    public function applications()
    {
        return $this->hasManyThrough(Application::class, Environment::class);
    }

    public function postgresqls()
    {
        return $this->hasManyThrough(StandalonePostgresql::class, Environment::class);
    }

    public function redis()
    {
        return $this->hasManyThrough(StandaloneRedis::class, Environment::class);
    }

    public function keydbs()
    {
        return $this->hasManyThrough(StandaloneKeydb::class, Environment::class);
    }

    public function dragonflies()
    {
        return $this->hasManyThrough(StandaloneDragonfly::class, Environment::class);
    }

    public function clickhouses()
    {
        return $this->hasManyThrough(StandaloneClickhouse::class, Environment::class);
    }

    public function mongodbs()
    {
        return $this->hasManyThrough(StandaloneMongodb::class, Environment::class);
    }

    public function mysqls()
    {
        return $this->hasManyThrough(StandaloneMysql::class, Environment::class);
    }

    public function mariadbs()
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
        return $this->postgresqls()->get()->merge($this->redis()->get())->merge($this->mongodbs()->get())->merge($this->mysqls()->get())->merge($this->mariadbs()->get())->merge($this->keydbs()->get())->merge($this->dragonflies()->get())->merge($this->clickhouses()->get());
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
