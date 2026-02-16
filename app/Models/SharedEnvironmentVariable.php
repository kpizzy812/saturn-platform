<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SharedEnvironmentVariable extends Model
{
    use Auditable, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, team_id (security), project_id, environment_id (relationships)
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    protected $casts = [
        'key' => 'string',
        'value' => 'encrypted',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'type'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Validate and sanitize the key attribute.
     * Uses the same validation as EnvironmentVariable for consistency.
     */
    protected function key(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                // Sanitize: trim whitespace and replace spaces with underscores
                $sanitized = str($value)->trim()->replace(' ', '_')->toString();

                // Security: Validate key format (POSIX standard for environment variable names)
                if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $sanitized)) {
                    throw new \InvalidArgumentException(
                        'Environment variable key must start with a letter or underscore and contain only letters, digits, and underscores.'
                    );
                }

                // Security: Block system environment variables
                if (in_array(strtoupper($sanitized), EnvironmentVariable::PROTECTED_KEYS)) {
                    throw new \InvalidArgumentException(
                        "Cannot set protected system environment variable: {$sanitized}"
                    );
                }

                return $sanitized;
            },
        );
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function environment()
    {
        return $this->belongsTo(Environment::class);
    }
}
