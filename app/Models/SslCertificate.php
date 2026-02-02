<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
