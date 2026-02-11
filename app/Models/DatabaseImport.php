<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class DatabaseImport extends Model
{
    protected $fillable = [
        'uuid',
        'database_type',
        'database_id',
        'team_id',
        'mode',
        'status',
        'progress',
        'connection_string',
        'source_type',
        'file_path',
        'file_name',
        'file_size',
        'output',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'connection_string' => 'encrypted',
        'progress' => 'integer',
        'file_size' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DatabaseImport $import): void {
            if (empty($import->uuid)) {
                $import->uuid = (string) Str::uuid();
            }
        });
    }

    public function database(): MorphTo
    {
        return $this->morphTo();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsInProgress(): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(string $output = ''): void
    {
        $this->update([
            'status' => 'completed',
            'progress' => 100,
            'output' => $output,
            'finished_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error' => $error,
            'finished_at' => now(),
        ]);
    }

    public function updateProgress(int $progress, ?string $output = null): void
    {
        $data = ['progress' => min(100, max(0, $progress))];
        if ($output !== null) {
            $data['output'] = $output;
        }
        $this->update($data);
    }
}
