<?php

namespace App\Services\AI;

use App\Models\AiModelPricing;
use Illuminate\Support\Facades\Log;

/**
 * Service for calculating AI token costs.
 *
 * Uses pricing data from the database (ai_model_pricings table).
 * Falls back to config if database pricing not found.
 */
class AiPricingService
{
    /**
     * Fallback pricing when database pricing not available.
     * Uses per-1M token pricing.
     */
    private const FALLBACK_PRICING = [
        'anthropic' => [
            'input_per_1m' => 3.00,
            'output_per_1m' => 15.00,
        ],
        'openai' => [
            'input_per_1m' => 0.15,
            'output_per_1m' => 0.60,
        ],
        'ollama' => [
            'input_per_1m' => 0.00,
            'output_per_1m' => 0.00,
        ],
    ];

    /**
     * Calculate cost for given tokens using database pricing.
     */
    public function calculateCost(string $provider, string $model, int $inputTokens, int $outputTokens): float
    {
        // Normalize provider name
        $normalizedProvider = $this->normalizeProvider($provider);

        // Try to get pricing from database
        $pricing = AiModelPricing::findByModelPattern($normalizedProvider, $model);

        if ($pricing) {
            return $pricing->calculateCost($inputTokens, $outputTokens);
        }

        // Fallback to config or default pricing
        return $this->calculateWithFallback($normalizedProvider, $inputTokens, $outputTokens);
    }

    /**
     * Get pricing info for a model.
     */
    public function getPricing(string $provider, string $model): ?array
    {
        $normalizedProvider = $this->normalizeProvider($provider);
        $pricing = AiModelPricing::findByModelPattern($normalizedProvider, $model);

        if ($pricing) {
            return [
                'provider' => $pricing->provider,
                'model_id' => $pricing->model_id,
                'model_name' => $pricing->model_name,
                'input_price_per_1m' => (float) $pricing->input_price_per_1m,
                'output_price_per_1m' => (float) $pricing->output_price_per_1m,
                'context_window' => $pricing->context_window,
            ];
        }

        // Return fallback info
        $fallback = $this->getFallbackPricing($normalizedProvider);

        return [
            'provider' => $normalizedProvider,
            'model_id' => $model,
            'model_name' => $model.' (fallback pricing)',
            'input_price_per_1m' => $fallback['input_per_1m'],
            'output_price_per_1m' => $fallback['output_per_1m'],
            'context_window' => null,
        ];
    }

    /**
     * Get all available pricing for a provider.
     */
    public function getProviderPricing(string $provider): array
    {
        $normalizedProvider = $this->normalizeProvider($provider);
        $models = AiModelPricing::getByProvider($normalizedProvider);

        return $models->map(fn ($m) => [
            'model_id' => $m->model_id,
            'model_name' => $m->model_name,
            'input_price_per_1m' => (float) $m->input_price_per_1m,
            'output_price_per_1m' => (float) $m->output_price_per_1m,
            'context_window' => $m->context_window,
        ])->toArray();
    }

    /**
     * Get all available pricing grouped by provider.
     */
    public function getAllPricing(): array
    {
        $pricing = [];
        $models = AiModelPricing::where('is_active', true)->get();

        foreach ($models as $model) {
            $provider = $model->provider;
            if (! isset($pricing[$provider])) {
                $pricing[$provider] = [];
            }

            $pricing[$provider][] = [
                'model_id' => $model->model_id,
                'model_name' => $model->model_name,
                'input_price_per_1m' => (float) $model->input_price_per_1m,
                'output_price_per_1m' => (float) $model->output_price_per_1m,
                'context_window' => $model->context_window,
            ];
        }

        return $pricing;
    }

    /**
     * Estimate cost before making an API call.
     */
    public function estimateCost(string $provider, string $model, int $estimatedInputTokens, int $estimatedOutputTokens): array
    {
        $cost = $this->calculateCost($provider, $model, $estimatedInputTokens, $estimatedOutputTokens);
        $pricing = $this->getPricing($provider, $model);

        return [
            'estimated_input_tokens' => $estimatedInputTokens,
            'estimated_output_tokens' => $estimatedOutputTokens,
            'estimated_cost_usd' => $cost,
            'pricing' => $pricing,
        ];
    }

    /**
     * Normalize provider name to standard format.
     */
    private function normalizeProvider(string $provider): string
    {
        return match (strtolower($provider)) {
            'claude', 'anthropic' => 'anthropic',
            'openai', 'gpt' => 'openai',
            'ollama' => 'ollama',
            default => strtolower($provider),
        };
    }

    /**
     * Calculate cost using fallback pricing.
     */
    private function calculateWithFallback(string $provider, int $inputTokens, int $outputTokens): float
    {
        $pricing = $this->getFallbackPricing($provider);

        $inputCost = ($inputTokens / 1_000_000) * $pricing['input_per_1m'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output_per_1m'];

        Log::debug('Using fallback AI pricing', [
            'provider' => $provider,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => $inputCost + $outputCost,
        ]);

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Get fallback pricing for a provider.
     */
    private function getFallbackPricing(string $provider): array
    {
        // Try config first
        $configPricing = config("ai.chat.pricing.{$provider}");
        if ($configPricing) {
            return [
                'input_per_1m' => ($configPricing['input_per_1k'] ?? 0.003) * 1000,
                'output_per_1m' => ($configPricing['output_per_1k'] ?? 0.015) * 1000,
            ];
        }

        return self::FALLBACK_PRICING[$provider] ?? self::FALLBACK_PRICING['anthropic'];
    }
}
