<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property-read Application|null $application
 * @property-read Service|null $service
 */
/**
 * @property-read Application|null $application
 * @property-read Service|null $service
 */
class ScheduledTask extends BaseModel
{
    use Auditable, HasSafeStringAttribute, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, service_id, application_id (relationships)
     */
    protected $fillable = [
        'uuid',
        'name',
        'command',
        'frequency',
        'container',
        'enabled',
        'timeout',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'timeout' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'command', 'frequency', 'enabled'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function latest_log(): HasOne
    {
        return $this->hasOne(ScheduledTaskExecution::class)->latest();
    }

    public function executions(): HasMany
    {
        // Last execution first
        return $this->hasMany(ScheduledTaskExecution::class)->orderBy('created_at', 'desc');
    }

    public function server()
    {
        if ($this->application) {
            if ($this->application->destination && $this->application->destination->server) {
                return $this->application->destination->server;
            }
        } elseif ($this->service) {
            if ($this->service->destination && $this->service->destination->server) {
                return $this->service->destination->server;
            }
        } elseif ($this->database) {
            if ($this->database->destination && $this->database->destination->server) {
                return $this->database->destination->server;
            }
        }

        return null;
    }
}
