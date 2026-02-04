<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApplicationTemplate extends BaseModel
{
    use HasFactory;

    /**
     * SECURITY: Using $fillable instead of $guarded = [] to prevent mass assignment vulnerabilities.
     * Excludes: id, team_id (security), created_by (relationship), slug (auto-generated),
     * usage_count, rating, rating_count (system-managed)
     */
    protected $fillable = [
        'name',
        'description',
        'category',
        'config',
        'tags',
        'is_official',
        'is_public',
    ];

    protected $casts = [
        'config' => 'array',
        'tags' => 'array',
        'is_official' => 'boolean',
        'is_public' => 'boolean',
        'rating' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ApplicationTemplate $template) {
            if (! $template->slug) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function addRating(float $rating): void
    {
        $currentTotal = ($this->rating ?? 0) * $this->rating_count;
        $this->rating_count++;
        $this->rating = ($currentTotal + $rating) / $this->rating_count;
        $this->save();
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeOfficial($query)
    {
        return $query->where('is_official', true);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('description', 'ilike', "%{$search}%")
                ->orWhereJsonContains('tags', $search);
        });
    }

    /**
     * Get the application configuration with defaults applied.
     */
    public function getApplicationConfig(): array
    {
        $defaults = [
            'build_pack' => 'nixpacks',
            'ports_exposes' => '3000',
            'base_directory' => '/',
            'health_check_path' => '/',
            'health_check_enabled' => true,
        ];

        return array_merge($defaults, $this->config ?? []);
    }

    /**
     * Get environment variables from template.
     */
    public function getEnvironmentVariables(): array
    {
        return $this->config['environment_variables'] ?? [];
    }

    /**
     * Available categories.
     */
    public static function categories(): array
    {
        return [
            'nodejs' => 'Node.js',
            'php' => 'PHP',
            'python' => 'Python',
            'ruby' => 'Ruby',
            'go' => 'Go',
            'rust' => 'Rust',
            'java' => 'Java',
            'dotnet' => '.NET',
            'static' => 'Static',
            'docker' => 'Docker',
            'general' => 'General',
        ];
    }
}
