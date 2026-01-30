<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class SharedEnvironmentVariable extends Model
{
    protected $guarded = [];

    protected $casts = [
        'key' => 'string',
        'value' => 'encrypted',
    ];

    /**
     * Validate and sanitize the key attribute.
     * Uses the same validation as EnvironmentVariable for consistency.
     */
    protected function key(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                // Sanitize: trim whitespace and replace spaces with underscores
                $sanitized = str($value)->trim()->replace(' ', '_')->value;

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
