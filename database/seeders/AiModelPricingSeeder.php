<?php

namespace Database\Seeders;

use App\Models\AiModelPricing;
use Illuminate\Database\Seeder;

/**
 * Seeder for AI model pricing data.
 *
 * Pricing as of February 2026:
 * - OpenAI: https://openai.com/api/pricing/
 * - Anthropic: https://www.anthropic.com/pricing
 */
class AiModelPricingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $models = [
            // =====================
            // ANTHROPIC MODELS
            // =====================

            // Claude 4.5 Series (Latest - February 2026)
            [
                'provider' => 'anthropic',
                'model_id' => 'claude-opus-4-5-20250514',
                'model_name' => 'Claude Opus 4.5',
                'input_price_per_1m' => 5.00,
                'output_price_per_1m' => 25.00,
                'context_window' => 200000,
                'is_active' => true,
            ],
            [
                'provider' => 'anthropic',
                'model_id' => 'claude-sonnet-4-5-20250514',
                'model_name' => 'Claude Sonnet 4.5',
                'input_price_per_1m' => 3.00,
                'output_price_per_1m' => 15.00,
                'context_window' => 200000,
                'is_active' => true,
            ],
            [
                'provider' => 'anthropic',
                'model_id' => 'claude-haiku-4-5-20250514',
                'model_name' => 'Claude Haiku 4.5',
                'input_price_per_1m' => 1.00,
                'output_price_per_1m' => 5.00,
                'context_window' => 200000,
                'is_active' => true,
            ],

            // Claude 4 Series
            [
                'provider' => 'anthropic',
                'model_id' => 'claude-sonnet-4-20250514',
                'model_name' => 'Claude Sonnet 4',
                'input_price_per_1m' => 3.00,
                'output_price_per_1m' => 15.00,
                'context_window' => 200000,
                'is_active' => true,
            ],
            [
                'provider' => 'anthropic',
                'model_id' => 'claude-opus-4-1-20250514',
                'model_name' => 'Claude Opus 4.1',
                'input_price_per_1m' => 15.00,
                'output_price_per_1m' => 75.00,
                'context_window' => 200000,
                'is_active' => true,
            ],

            // Claude 3.5 Series (Legacy but still used)
            [
                'provider' => 'anthropic',
                'model_id' => 'claude-3-5-sonnet-20241022',
                'model_name' => 'Claude 3.5 Sonnet',
                'input_price_per_1m' => 3.00,
                'output_price_per_1m' => 15.00,
                'context_window' => 200000,
                'is_active' => true,
            ],
            [
                'provider' => 'anthropic',
                'model_id' => 'claude-3-5-haiku-20241022',
                'model_name' => 'Claude 3.5 Haiku',
                'input_price_per_1m' => 1.00,
                'output_price_per_1m' => 5.00,
                'context_window' => 200000,
                'is_active' => true,
            ],

            // =====================
            // OPENAI MODELS
            // =====================

            // GPT-4o Series
            [
                'provider' => 'openai',
                'model_id' => 'gpt-4o',
                'model_name' => 'GPT-4o',
                'input_price_per_1m' => 2.50,
                'output_price_per_1m' => 10.00,
                'context_window' => 128000,
                'is_active' => true,
            ],
            [
                'provider' => 'openai',
                'model_id' => 'gpt-4o-mini',
                'model_name' => 'GPT-4o Mini',
                'input_price_per_1m' => 0.15,
                'output_price_per_1m' => 0.60,
                'context_window' => 128000,
                'is_active' => true,
            ],

            // GPT-4.1 (Released April 2025)
            [
                'provider' => 'openai',
                'model_id' => 'gpt-4.1',
                'model_name' => 'GPT-4.1',
                'input_price_per_1m' => 2.00,
                'output_price_per_1m' => 8.00,
                'context_window' => 1000000,
                'is_active' => true,
            ],
            [
                'provider' => 'openai',
                'model_id' => 'gpt-4.1-mini',
                'model_name' => 'GPT-4.1 Mini',
                'input_price_per_1m' => 0.40,
                'output_price_per_1m' => 1.60,
                'context_window' => 1000000,
                'is_active' => true,
            ],
            [
                'provider' => 'openai',
                'model_id' => 'gpt-4.1-nano',
                'model_name' => 'GPT-4.1 Nano',
                'input_price_per_1m' => 0.10,
                'output_price_per_1m' => 0.40,
                'context_window' => 1000000,
                'is_active' => true,
            ],

            // GPT-4 Turbo (Legacy)
            [
                'provider' => 'openai',
                'model_id' => 'gpt-4-turbo',
                'model_name' => 'GPT-4 Turbo',
                'input_price_per_1m' => 10.00,
                'output_price_per_1m' => 30.00,
                'context_window' => 128000,
                'is_active' => true,
            ],

            // GPT-3.5 Turbo (Legacy, cheap option)
            [
                'provider' => 'openai',
                'model_id' => 'gpt-3.5-turbo',
                'model_name' => 'GPT-3.5 Turbo',
                'input_price_per_1m' => 0.50,
                'output_price_per_1m' => 1.50,
                'context_window' => 16385,
                'is_active' => true,
            ],

            // o1 Reasoning Models
            [
                'provider' => 'openai',
                'model_id' => 'o1',
                'model_name' => 'o1 Reasoning',
                'input_price_per_1m' => 15.00,
                'output_price_per_1m' => 60.00,
                'context_window' => 200000,
                'is_active' => true,
            ],
            [
                'provider' => 'openai',
                'model_id' => 'o1-mini',
                'model_name' => 'o1 Mini',
                'input_price_per_1m' => 3.00,
                'output_price_per_1m' => 12.00,
                'context_window' => 128000,
                'is_active' => true,
            ],
            [
                'provider' => 'openai',
                'model_id' => 'o3-mini',
                'model_name' => 'o3 Mini',
                'input_price_per_1m' => 1.10,
                'output_price_per_1m' => 4.40,
                'context_window' => 200000,
                'is_active' => true,
            ],

            // =====================
            // OLLAMA (Local, free)
            // =====================
            [
                'provider' => 'ollama',
                'model_id' => 'llama3.1',
                'model_name' => 'Llama 3.1',
                'input_price_per_1m' => 0.00,
                'output_price_per_1m' => 0.00,
                'context_window' => 128000,
                'is_active' => true,
            ],
            [
                'provider' => 'ollama',
                'model_id' => 'llama3.2',
                'model_name' => 'Llama 3.2',
                'input_price_per_1m' => 0.00,
                'output_price_per_1m' => 0.00,
                'context_window' => 128000,
                'is_active' => true,
            ],
            [
                'provider' => 'ollama',
                'model_id' => 'mistral',
                'model_name' => 'Mistral',
                'input_price_per_1m' => 0.00,
                'output_price_per_1m' => 0.00,
                'context_window' => 32000,
                'is_active' => true,
            ],
            [
                'provider' => 'ollama',
                'model_id' => 'codellama',
                'model_name' => 'Code Llama',
                'input_price_per_1m' => 0.00,
                'output_price_per_1m' => 0.00,
                'context_window' => 16000,
                'is_active' => true,
            ],
        ];

        foreach ($models as $model) {
            AiModelPricing::updateOrCreate(
                ['provider' => $model['provider'], 'model_id' => $model['model_id']],
                $model
            );
        }

        // Clear cache after seeding
        AiModelPricing::clearCache();

        $this->command->info('AI model pricing seeded successfully: '.count($models).' models');
    }
}
