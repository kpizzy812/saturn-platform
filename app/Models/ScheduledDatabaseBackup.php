<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property-read Team|null $team
 */
class ScheduledDatabaseBackup extends BaseModel
{
    use Auditable, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, team_id (security), database_type, database_id (relationships),
     * last_restore_test_at (system-managed)
     */
    protected $fillable = [
        'uuid',
        'enabled',
        'name',
        'frequency',
        'save_s3',
        's3_storage_id',
        'backup_retention_period',
        'databases_to_backup',
        'dump_all',
        'disable_local_backup',
        'verify_after_backup',
        'restore_test_enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'save_s3' => 'boolean',
            'dump_all' => 'boolean',
            'disable_local_backup' => 'boolean',
            'verify_after_backup' => 'boolean',
            'restore_test_enabled' => 'boolean',
            'last_restore_test_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['enabled', 'frequency', 'save_s3', 'databases_to_backup'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public static function ownedByCurrentTeam()
    {
        return ScheduledDatabaseBackup::whereRelation('team', 'id', currentTeam()->id)->orderBy('created_at', 'desc');
    }

    public static function ownedByCurrentTeamAPI(int $teamId)
    {
        return ScheduledDatabaseBackup::whereRelation('team', 'id', $teamId)->orderBy('created_at', 'desc');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function database(): MorphTo
    {
        return $this->morphTo();
    }

    public function latest_log(): HasOne
    {
        return $this->hasOne(ScheduledDatabaseBackupExecution::class)->latest();
    }

    public function executions(): HasMany
    {
        // Last execution first
        return $this->hasMany(ScheduledDatabaseBackupExecution::class)->orderBy('created_at', 'desc');
    }

    public function s3()
    {
        return $this->belongsTo(S3Storage::class, 's3_storage_id');
    }

    public function get_last_days_backup_status($days = 7)
    {
        return $this->hasMany(ScheduledDatabaseBackupExecution::class)->where('created_at', '>=', now()->subDays($days))->get();
    }

    public function executionsPaginated(int $skip = 0, int $take = 10)
    {
        $executions = $this->hasMany(ScheduledDatabaseBackupExecution::class)->orderBy('created_at', 'desc');
        $count = $executions->count();
        $executions = $executions->skip($skip)->take($take)->get();

        return [
            'count' => $count,
            'executions' => $executions,
        ];
    }

    public function server()
    {
        if ($this->database) {
            if ($this->database instanceof ServiceDatabase) {
                $destination = data_get($this->database->service, 'destination');
                $server = data_get($destination, 'server');
            } else {
                $destination = data_get($this->database, 'destination');
                $server = data_get($destination, 'server');
            }
            if ($server) {
                return $server;
            }
        }

        return null;
    }
}
