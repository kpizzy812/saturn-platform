<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectSetting extends Model
{
    protected $guarded = [];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function defaultServer()
    {
        return $this->belongsTo(Server::class, 'default_server_id');
    }
}
