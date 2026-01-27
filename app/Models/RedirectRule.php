<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedirectRule extends BaseModel
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'hits' => 'integer',
        ];
    }

    public static function ownedByCurrentTeam()
    {
        return RedirectRule::where('team_id', currentTeam()->id)->orderBy('created_at', 'desc');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
