<?php

namespace App\Models;

use App\Traits\HasSafeStringAttribute;

class Tag extends BaseModel
{
    use HasSafeStringAttribute;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, team_id (security)
     */
    protected $fillable = [
        'name',
        'description',
    ];

    protected function customizeName($value)
    {
        return strtolower($value);
    }

    public static function ownedByCurrentTeam()
    {
        return Tag::whereTeamId(currentTeam()->id)->orderBy('name');
    }

    public function applications()
    {
        return $this->morphedByMany(Application::class, 'taggable');
    }

    public function services()
    {
        return $this->morphedByMany(Service::class, 'taggable');
    }

    public function projects()
    {
        return $this->morphedByMany(Project::class, 'taggable');
    }
}
