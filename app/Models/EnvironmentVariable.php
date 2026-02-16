<?php

namespace App\Models;

use App\Models\EnvironmentVariable as ModelsEnvironmentVariable;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property string|null $value
 * @property string $resourceable_type
 * @property int $resourceable_id
 * @property bool $is_literal
 * @property bool $is_multiline
 * @property bool $is_preview
 * @property bool $is_runtime
 * @property bool $is_buildtime
 * @property bool $is_shared
 * @property bool $is_shown_once
 * @property bool $is_required
 * @property string|null $description
 * @property string|null $source_template
 * @property string $version
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string|null $real_value
 * @property-read bool $is_really_required
 * @property-read bool $is_nixpacks
 * @property-read bool $is_saturn
 * @property-read \Illuminate\Database\Eloquent\Model|null $resourceable
 */
#[OA\Schema(
    description: 'Environment Variable model',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'uuid', type: 'string'),
        new OA\Property(property: 'resourceable_type', type: 'string'),
        new OA\Property(property: 'resourceable_id', type: 'integer'),
        new OA\Property(property: 'is_literal', type: 'boolean'),
        new OA\Property(property: 'is_multiline', type: 'boolean'),
        new OA\Property(property: 'is_preview', type: 'boolean'),
        new OA\Property(property: 'is_runtime', type: 'boolean'),
        new OA\Property(property: 'is_buildtime', type: 'boolean'),
        new OA\Property(property: 'is_shared', type: 'boolean'),
        new OA\Property(property: 'is_shown_once', type: 'boolean'),
        new OA\Property(property: 'key', type: 'string'),
        new OA\Property(property: 'value', type: 'string'),
        new OA\Property(property: 'real_value', type: 'string'),
        new OA\Property(property: 'source_template', type: 'string', nullable: true, description: 'Source template file (env_example, env_sample, env_template)'),
        new OA\Property(property: 'version', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string'),
        new OA\Property(property: 'updated_at', type: 'string'),
    ]
)]
class EnvironmentVariable extends BaseModel
{
    use Auditable, HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     * SECURITY: Using $fillable to prevent mass assignment vulnerabilities.
     */
    protected $fillable = [
        'uuid',
        'key',
        'value',
        'is_literal',
        'is_multiline',
        'is_preview',
        'is_runtime',
        'is_buildtime',
        'is_shown_once',
        'is_required',
        'description',
        'source_template',
        'version',
        'resourceable_type',
        'resourceable_id',
    ];

    protected $casts = [
        'key' => 'string',
        'value' => 'encrypted',
        'is_multiline' => 'boolean',
        'is_preview' => 'boolean',
        'is_runtime' => 'boolean',
        'is_buildtime' => 'boolean',
        'version' => 'string',
        'resourceable_type' => 'string',
        'resourceable_id' => 'integer',
    ];

    protected $appends = ['real_value', 'is_shared', 'is_really_required', 'is_nixpacks', 'is_saturn'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'is_preview', 'is_runtime', 'is_buildtime'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted()
    {
        static::created(function (EnvironmentVariable $environment_variable) {
            if ($environment_variable->resourceable_type === Application::class && ! $environment_variable->is_preview) {
                $found = ModelsEnvironmentVariable::where('key', $environment_variable->key)
                    ->where('resourceable_type', Application::class)
                    ->where('resourceable_id', $environment_variable->resourceable_id)
                    ->where('is_preview', true)
                    ->first();

                if (! $found) {
                    $application = Application::find($environment_variable->resourceable_id);
                    if ($application) {
                        ModelsEnvironmentVariable::create([
                            'key' => $environment_variable->key,
                            'value' => $environment_variable->value,
                            'is_multiline' => $environment_variable->is_multiline ?? false,
                            'is_literal' => $environment_variable->is_literal ?? false,
                            'is_runtime' => $environment_variable->is_runtime ?? false,
                            'is_buildtime' => $environment_variable->is_buildtime ?? false,
                            'resourceable_type' => Application::class,
                            'resourceable_id' => $environment_variable->resourceable_id,
                            'is_preview' => true,
                        ]);
                    }
                }
            }
            $environment_variable->update([
                'version' => config('constants.saturn.version'),
            ]);
        });

        static::saving(function (EnvironmentVariable $environmentVariable) {
            $environmentVariable->updateIsShared();
        });
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value = null) => $this->get_environment_variables($value),
            set: fn (?string $value = null) => $this->set_environment_variables($value),
        );
    }

    /**
     * Get the parent resourceable model.
     */
    public function resourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function resource()
    {
        return $this->resourceable;
    }

    protected function realValue(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->relationLoaded('resourceable')) {
                    $this->load('resourceable');
                }
                $resource = $this->resourceable;
                if (! $resource) {
                    return null;
                }

                $real_value = $this->get_real_environment_variables($this->value, $resource);
                if ($this->is_literal || $this->is_multiline) {
                    $real_value = '\''.$real_value.'\'';
                } else {
                    $real_value = escapeEnvVariables($real_value);
                }

                return $real_value;
            }
        );
    }

    protected function isReallyRequired(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->is_required && str($this->real_value)->isEmpty(),
        );
    }

    protected function isNixpacks(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (str($this->key)->startsWith('NIXPACKS_')) {
                    return true;
                }

                return false;
            }
        );
    }

    protected function isSaturn(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (str($this->key)->startsWith('SERVICE_')) {
                    return true;
                }

                return false;
            }
        );
    }

    protected function isShared(): Attribute
    {
        return Attribute::make(
            get: function () {
                $type = str($this->value)->after('{{')->before('.')->toString();
                if (str($this->value)->startsWith('{{'.$type) && str($this->value)->endsWith('}}')) {
                    return true;
                }

                return false;
            }
        );
    }

    private function get_real_environment_variables(?string $environment_variable = null, $resource = null)
    {
        if ((is_null($environment_variable) || $environment_variable === '') || is_null($resource)) {
            return null;
        }
        $environment_variable = trim($environment_variable);
        $sharedEnvsFound = str($environment_variable)->matchAll('/{{(.*?)}}/');
        if ($sharedEnvsFound->isEmpty()) {
            return $environment_variable;
        }
        foreach ($sharedEnvsFound as $sharedEnv) {
            $type = str($sharedEnv)->trim()->match('/(.*?)\./');
            if (! collect(SHARED_VARIABLE_TYPES)->contains($type)) {
                continue;
            }
            $variable = str($sharedEnv)->trim()->match('/\.(.*)/');
            $id = null;
            if ($type->value() === 'environment') {
                $id = $resource->environment->id;
            } elseif ($type->value() === 'project') {
                $id = $resource->environment->project->id;
            } elseif ($type->value() === 'team') {
                $id = $resource->team()->id;
            }
            if (is_null($id)) {
                continue;
            }
            $environment_variable_found = SharedEnvironmentVariable::where('type', $type)->where('key', $variable)->where('team_id', $resource->team()->id)->where("{$type}_id", $id)->first();
            if ($environment_variable_found) {
                $environment_variable = str($environment_variable)->replace("{{{$sharedEnv}}}", $environment_variable_found->value);
            }
        }

        return str($environment_variable)->value();
    }

    private function get_environment_variables(?string $environment_variable = null): ?string
    {
        if (! $environment_variable) {
            return null;
        }

        return trim(decrypt($environment_variable));
    }

    private function set_environment_variables(?string $environment_variable = null): ?string
    {
        if (is_null($environment_variable) || $environment_variable === '') {
            return null;
        }
        $environment_variable = trim($environment_variable);
        $type = str($environment_variable)->after('{{')->before('.')->toString();
        if (str($environment_variable)->startsWith('{{'.$type) && str($environment_variable)->endsWith('}}')) {
            return encrypt($environment_variable);
        }

        return encrypt($environment_variable);
    }

    /**
     * System environment variables that cannot be overridden.
     * These could be used for privilege escalation or system manipulation.
     */
    public const PROTECTED_KEYS = [
        'PATH',
        'LD_PRELOAD',
        'LD_LIBRARY_PATH',
        'LD_AUDIT',
        'LD_DEBUG',
        'SHELL',
        'HOME',
        'USER',
        'LOGNAME',
        'LANG',
        'LC_ALL',
        'IFS',
        'TERM',
        'DISPLAY',
        'SSH_AUTH_SOCK',
        'DOCKER_HOST',
    ];

    protected function key(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                // Sanitize: trim whitespace and replace spaces with underscores
                $sanitized = str($value)->trim()->replace(' ', '_')->toString();

                // Security: Validate key format (POSIX standard for environment variable names)
                // Must start with letter or underscore, followed by letters, digits, or underscores
                if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $sanitized)) {
                    throw new \InvalidArgumentException(
                        'Environment variable key must start with a letter or underscore and contain only letters, digits, and underscores.'
                    );
                }

                // Security: Block system environment variables that could be exploited
                if (in_array(strtoupper($sanitized), self::PROTECTED_KEYS)) {
                    throw new \InvalidArgumentException(
                        "Cannot set protected system environment variable: {$sanitized}"
                    );
                }

                return $sanitized;
            },
        );
    }

    protected function updateIsShared(): void
    {
        $type = str($this->value)->after('{{')->before('.')->toString();
        $isShared = str($this->value)->startsWith('{{'.$type) && str($this->value)->endsWith('}}');
        $this->is_shared = $isShared;
    }
}
