<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\ClearsGlobalSearchCache;
use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[OA\Schema(
    description: 'Environment model',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'project_id', type: 'integer'),
        new OA\Property(property: 'created_at', type: 'string'),
        new OA\Property(property: 'updated_at', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
    ]
)]
/**
 * @property-read Project|null $project
 */
class Environment extends BaseModel
{
    use Auditable;
    use ClearsGlobalSearchCache;
    use HasFactory;
    use HasSafeStringAttribute;
    use LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id. Relationship IDs are fillable but validated in Actions/routes.
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'requires_approval',
        'project_id',
        'default_server_id',
        'default_git_branch',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
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
        static::deleting(function ($environment) {
            $shared_variables = $environment->environment_variables();
            foreach ($shared_variables as $shared_variable) {
                $shared_variable->delete();
            }
        });
    }

    public static function ownedByCurrentTeam()
    {
        return Environment::whereRelation('project.team', 'id', currentTeam()->id)->orderBy('name');
    }

    public function isEmpty()
    {
        return $this->applications()->count() == 0 &&
            $this->redis()->count() == 0 &&
            $this->postgresqls()->count() == 0 &&
            $this->mysqls()->count() == 0 &&
            $this->keydbs()->count() == 0 &&
            $this->dragonflies()->count() == 0 &&
            $this->clickhouses()->count() == 0 &&
            $this->mariadbs()->count() == 0 &&
            $this->mongodbs()->count() == 0 &&
            $this->services()->count() == 0;
    }

    /** @return HasMany<SharedEnvironmentVariable, $this> */
    public function environment_variables(): HasMany
    {
        return $this->hasMany(SharedEnvironmentVariable::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @return HasMany<StandalonePostgresql, $this> */
    public function postgresqls(): HasMany
    {
        return $this->hasMany(StandalonePostgresql::class);
    }

    /** @return HasMany<StandaloneRedis, $this> */
    public function redis(): HasMany
    {
        return $this->hasMany(StandaloneRedis::class);
    }

    /** @return HasMany<StandaloneMongodb, $this> */
    public function mongodbs(): HasMany
    {
        return $this->hasMany(StandaloneMongodb::class);
    }

    /** @return HasMany<StandaloneMysql, $this> */
    public function mysqls(): HasMany
    {
        return $this->hasMany(StandaloneMysql::class);
    }

    /** @return HasMany<StandaloneMariadb, $this> */
    public function mariadbs(): HasMany
    {
        return $this->hasMany(StandaloneMariadb::class);
    }

    /** @return HasMany<StandaloneKeydb, $this> */
    public function keydbs(): HasMany
    {
        return $this->hasMany(StandaloneKeydb::class);
    }

    /** @return HasMany<StandaloneDragonfly, $this> */
    public function dragonflies(): HasMany
    {
        return $this->hasMany(StandaloneDragonfly::class);
    }

    /** @return HasMany<StandaloneClickhouse, $this> */
    public function clickhouses(): HasMany
    {
        return $this->hasMany(StandaloneClickhouse::class);
    }

    public function databases()
    {
        $postgresqls = $this->postgresqls;
        $redis = $this->redis;
        $mongodbs = $this->mongodbs;
        $mysqls = $this->mysqls;
        $mariadbs = $this->mariadbs;
        $keydbs = $this->keydbs;
        $dragonflies = $this->dragonflies;
        $clickhouses = $this->clickhouses;

        return $postgresqls->concat($redis)->concat($mongodbs)->concat($mysqls)->concat($mariadbs)->concat($keydbs)->concat($dragonflies)->concat($clickhouses);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function defaultServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'default_server_id');
    }

    /**
     * Get the environment type (development, uat, production).
     */
    public function getEnvironmentType(): string
    {
        return $this->type ?? 'development';
    }

    /**
     * Check if this is a production environment.
     */
    public function isProduction(): bool
    {
        return $this->type === 'production';
    }

    /**
     * Check if this is a UAT/staging environment.
     */
    public function isUat(): bool
    {
        return $this->type === 'uat';
    }

    /**
     * Check if this is a development environment.
     */
    public function isDevelopment(): bool
    {
        return $this->type === 'development' || $this->type === null;
    }

    /**
     * Check if this environment is protected (production by default).
     */
    public function isProtected(): bool
    {
        return $this->isProduction();
    }

    /**
     * Check if deployments to this environment require approval.
     */
    public function requiresDeploymentApproval(): bool
    {
        return $this->requires_approval === true;
    }

    /**
     * Check if a specific user can deploy to this environment.
     */
    public function canUserDeploy(User $user): bool
    {
        return $user->canDeployToEnvironment($this);
    }

    /**
     * Check if a specific user's deployment requires approval.
     */
    public function userRequiresApproval(User $user): bool
    {
        return $user->requiresApprovalForEnvironment($this);
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    protected function customizeName($value)
    {
        return str($value)->lower()->trim()->replace('/', '-')->toString();
    }
}
