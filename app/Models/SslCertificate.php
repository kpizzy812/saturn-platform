<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string|null $ssl_certificate
 * @property string|null $ssl_private_key
 * @property string|null $configuration_dir
 * @property string|null $mount_path
 * @property string|null $resource_type
 * @property int|null $resource_id
 * @property int $server_id
 * @property string|null $common_name
 * @property array|null $subject_alternative_names
 * @property \Carbon\Carbon|null $valid_until
 * @property bool $is_ca_certificate
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read Server $server
 */
class SslCertificate extends Model
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'ssl_certificate',
        'ssl_private_key',
        'configuration_dir',
        'mount_path',
        'resource_type',
        'resource_id',
        'server_id',
        'common_name',
        'subject_alternative_names',
        'valid_until',
        'is_ca_certificate',
    ];

    protected $casts = [
        'ssl_certificate' => 'encrypted',
        'ssl_private_key' => 'encrypted',
        'subject_alternative_names' => 'array',
        'valid_until' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['common_name', 'valid_until', 'is_ca_certificate'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function application()
    {
        return $this->morphTo('resource');
    }

    public function service()
    {
        return $this->morphTo('resource');
    }

    public function database()
    {
        return $this->morphTo('resource');
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}
