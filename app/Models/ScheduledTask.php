<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $command
 * @property string $frequency
 * @property string|null $container
 * @property bool $enabled
 * @property int|null $timeout
 * @property int|null $application_id
 * @property int|null $service_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Application|null $application
 * @property-read Service|null $service
 * @property-read \Illuminate\Database\Eloquent\Model|null $database
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

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
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
            return $this->application->destination->server;
        } elseif ($this->service) {
            return $this->service->destination->server;
        } elseif ($this->database) {
            $destination = $this->database->getAttribute('destination');
            if ($destination && $destination->server) {
                return $destination->server;
            }
        }

        return null;
    }
}
