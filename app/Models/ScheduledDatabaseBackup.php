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
 * @property int $id
 * @property string $uuid
 * @property bool $enabled
 * @property string|null $name
 * @property string $frequency
 * @property bool $save_s3
 * @property int|null $s3_storage_id
 * @property int|null $backup_retention_period
 * @property string|null $databases_to_backup
 * @property bool $dump_all
 * @property bool $disable_local_backup
 * @property string $database_type
 * @property int $database_id
 * @property int $team_id
 * @property bool $restore_test_enabled
 * @property \Carbon\Carbon|null $last_restore_test_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Team|null $team
 * @property-read \Illuminate\Database\Eloquent\Model|null $database
 */
class ScheduledDatabaseBackup extends BaseModel
{
    use Auditable, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id (auto-increment), last_restore_test_at (system-managed)
     * Note: database_id, database_type, team_id are required for create() calls across the codebase.
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
        'database_id',
        'database_type',
        'team_id',
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

    /** @return BelongsTo<S3Storage, $this> */
    public function s3(): BelongsTo
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
