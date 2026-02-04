<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * AI Model Pricing - stores pricing information for all AI models.
 *
 * @property int $id
 * @property string $provider
 * @property string $model_id
 * @property string $model_name
 * @property float $input_price_per_1m
 * @property float $output_price_per_1m
 * @property int|null $context_window
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class AiModelPricing extends Model
{
    protected $fillable = [
        'provider',
        'model_id',
        'model_name',
        'input_price_per_1m',
        'output_price_per_1m',
        'context_window',
        'is_active',
    ];

    protected $casts = [
        'input_price_per_1m' => 'decimal:4',
        'output_price_per_1m' => 'decimal:4',
        'context_window' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Cache key for pricing data.
     */
    private const CACHE_KEY = 'ai_model_pricing';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get pricing for a specific model.
     */
    public static function getPricing(string $provider, string $modelId): ?self
    {
        $cached = self::getCachedPricing();

        $key = self::buildKey($provider, $modelId);

        return $cached[$key] ?? null;
    }

    /**
     * Get all cached pricing data.
     *
     * @return array<string, self>
     */
    public static function getCachedPricing(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $pricing = [];
            foreach (self::where('is_active', true)->get() as $model) {
                $key = self::buildKey($model->provider, $model->model_id);
                $pricing[$key] = $model;
            }

            return $pricing;
        });
    }

    /**
     * Clear pricing cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Calculate cost for given tokens.
     */
    public function calculateCost(int $inputTokens, int $outputTokens): float
    {
        $inputCost = ($inputTokens / 1_000_000) * (float) $this->input_price_per_1m;
        $outputCost = ($outputTokens / 1_000_000) * (float) $this->output_price_per_1m;

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Get all active models for a provider.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public static function getByProvider(string $provider)
    {
        return self::where('provider', $provider)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Find pricing by partial model ID match.
     * Useful when API returns slightly different model names.
     */
    public static function findByModelPattern(string $provider, string $modelPattern): ?self
    {
        // First try exact match
        $exact = self::getPricing($provider, $modelPattern);
        if ($exact) {
            return $exact;
        }

        // Try to find by pattern (e.g., "claude-3-5-sonnet" matches "claude-3-5-sonnet-20241022")
        $cached = self::getCachedPricing();

        foreach ($cached as $key => $pricing) {
            if ($pricing->provider !== $provider) {
                continue;
            }

            // Check if model_id contains the pattern or vice versa
            if (str_contains($pricing->model_id, $modelPattern) || str_contains($modelPattern, $pricing->model_id)) {
                return $pricing;
            }
        }

        return null;
    }

    /**
     * Scope to active models.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to specific provider.
     */
    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Build cache key for provider+model.
     */
    private static function buildKey(string $provider, string $modelId): string
    {
        return strtolower($provider).':'.strtolower($modelId);
    }
}
