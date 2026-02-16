<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $organization
 * @property string|null $api_url
 * @property string|null $html_url
 * @property string|null $custom_user
 * @property int|null $custom_port
 * @property int|null $app_id
 * @property int|null $installation_id
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property string|null $webhook_secret
 * @property bool $is_public
 * @property bool $is_system_wide
 * @property int $team_id
 * @property int|null $private_key_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string|null $type
 * @property-read PrivateKey|null $privateKey
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Application> $applications
 */
class GithubApp extends BaseModel
{
    use Auditable, HasFactory, LogsActivity;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, team_id (security), private_key_id (relationship management)
     */
    protected $fillable = [
        'name',
        'organization',
        'api_url',
        'html_url',
        'custom_user',
        'custom_port',
        'app_id',
        'installation_id',
        'client_id',
        'client_secret',
        'webhook_secret',
        'is_public',
        'is_system_wide',
    ];

    protected $appends = ['type'];

    protected $casts = [
        'is_public' => 'boolean',
        'is_system_wide' => 'boolean',
        // Security: Encrypt secrets at rest
        'client_secret' => 'encrypted',
        'webhook_secret' => 'encrypted',
    ];

    protected $hidden = [
        'client_secret',
        'webhook_secret',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'organization', 'is_public'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function booted(): void
    {
        static::deleting(function (GithubApp $github_app) {
            $applications_count = Application::where('source_id', $github_app->id)->count();
            if ($applications_count > 0) {
                throw new \Exception('You cannot delete this GitHub App because it is in use by '.$applications_count.' application(s). Delete them first.');
            }

            $privateKey = $github_app->privateKey;
            if ($privateKey) {
                // Check if key is used by anything EXCEPT this GitHub app
                $isUsedElsewhere = $privateKey->servers()->exists()
                    || $privateKey->applications()->exists()
                    || $privateKey->githubApps()->where('id', '!=', $github_app->id)->exists()
                    || $privateKey->gitlabApps()->exists();

                if (! $isUsedElsewhere) {
                    $privateKey->delete();
                } else {
                }
            }
        });
    }

    public static function ownedByCurrentTeam()
    {
        return GithubApp::where(function ($query) {
            $query->where('team_id', currentTeam()->id)
                ->orWhere('is_system_wide', true);
        });
    }

    public static function public()
    {
        return GithubApp::where(function ($query) {
            $query->where(function ($q) {
                $q->where('team_id', currentTeam()->id)
                    ->orWhere('is_system_wide', true);
            })->where('is_public', true);
        })->whereNotNull('app_id')->get();
    }

    public static function private()
    {
        return GithubApp::where(function ($query) {
            $query->where(function ($q) {
                $q->where('team_id', currentTeam()->id)
                    ->orWhere('is_system_wide', true);
            })->where('is_public', false);
        })->whereNotNull('app_id')->get();
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function applications()
    {
        return $this->morphMany(Application::class, 'source');
    }

    public function privateKey()
    {
        return $this->belongsTo(PrivateKey::class);
    }

    protected function type(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->getMorphClass() === \App\Models\GithubApp::class) {
                    return 'github';
                }
            },
        );
    }
}
