<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\ClearsGlobalSearchCache;
use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'Environment model',
    type: 'object',
    properties: [
        'id' => ['type' => 'integer'],
        'name' => ['type' => 'string'],
        'project_id' => ['type' => 'integer'],
        'created_at' => ['type' => 'string'],
        'updated_at' => ['type' => 'string'],
        'description' => ['type' => 'string'],
    ]
)]
class Environment extends BaseModel
{
    use Auditable;
    use ClearsGlobalSearchCache;
    use HasFactory;
    use HasSafeStringAttribute;

    protected $guarded = [];

    protected $casts = [
        'requires_approval' => 'boolean',
    ];

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

    public function environment_variables()
    {
        return $this->hasMany(SharedEnvironmentVariable::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function postgresqls()
    {
        return $this->hasMany(StandalonePostgresql::class);
    }

    public function redis()
    {
        return $this->hasMany(StandaloneRedis::class);
    }

    public function mongodbs()
    {
        return $this->hasMany(StandaloneMongodb::class);
    }

    public function mysqls()
    {
        return $this->hasMany(StandaloneMysql::class);
    }

    public function mariadbs()
    {
        return $this->hasMany(StandaloneMariadb::class);
    }

    public function keydbs()
    {
        return $this->hasMany(StandaloneKeydb::class);
    }

    public function dragonflies()
    {
        return $this->hasMany(StandaloneDragonfly::class);
    }

    public function clickhouses()
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

    public function project()
    {
        return $this->belongsTo(Project::class);
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

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    protected function customizeName($value)
    {
        return str($value)->lower()->trim()->replace('/', '-')->toString();
    }
}
