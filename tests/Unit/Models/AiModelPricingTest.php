<?php

namespace Tests\Unit\Models;

use App\Models\AiModelPricing;
use Tests\TestCase;

class AiModelPricingTest extends TestCase
{
    public function test_calculate_cost_with_integer_tokens(): void
    {
        $pricing = new AiModelPricing([
            'provider' => 'anthropic',
            'model_id' => 'test-model',
            'model_name' => 'Test Model',
            'input_price_per_1m' => 3.00,
            'output_price_per_1m' => 15.00,
        ]);

        // 1000 input tokens, 500 output tokens
        $cost = $pricing->calculateCost(1000, 500);

        // Expected: (1000/1M) * 3.00 + (500/1M) * 15.00 = 0.003 + 0.0075 = 0.0105
        $this->assertEqualsWithDelta(0.0105, $cost, 0.000001);
    }

    public function test_calculate_cost_with_zero_tokens(): void
    {
        $pricing = new AiModelPricing([
            'provider' => 'anthropic',
            'model_id' => 'test-model',
            'model_name' => 'Test Model',
            'input_price_per_1m' => 3.00,
            'output_price_per_1m' => 15.00,
        ]);

        $cost = $pricing->calculateCost(0, 0);

        $this->assertEquals(0.0, $cost);
    }

    public function test_calculate_cost_with_large_tokens(): void
    {
        $pricing = new AiModelPricing([
            'provider' => 'openai',
            'model_id' => 'gpt-4o',
            'model_name' => 'GPT-4o',
            'input_price_per_1m' => 2.50,
            'output_price_per_1m' => 10.00,
        ]);

        // 1 million input, 500K output
        $cost = $pricing->calculateCost(1_000_000, 500_000);

        // Expected: 2.50 + 5.00 = 7.50
        $this->assertEqualsWithDelta(7.50, $cost, 0.001);
    }

    public function test_free_model_has_zero_cost(): void
    {
        $pricing = new AiModelPricing([
            'provider' => 'ollama',
            'model_id' => 'llama3.1',
            'model_name' => 'Llama 3.1',
            'input_price_per_1m' => 0.00,
            'output_price_per_1m' => 0.00,
        ]);

        $cost = $pricing->calculateCost(10_000_000, 5_000_000);

        $this->assertEquals(0.0, $cost);
    }

    public function test_casts_are_correct(): void
    {
        $pricing = new AiModelPricing([
            'provider' => 'anthropic',
            'model_id' => 'test',
            'model_name' => 'Test',
            'input_price_per_1m' => '3.00',
            'output_price_per_1m' => '15.00',
            'context_window' => '200000',
            'is_active' => 1,
        ]);

        $this->assertIsString($pricing->input_price_per_1m);
        $this->assertIsString($pricing->output_price_per_1m);
        $this->assertIsInt($pricing->context_window);
        $this->assertIsBool($pricing->is_active);
    }

    public function test_mini_model_pricing(): void
    {
        $pricing = new AiModelPricing([
            'provider' => 'openai',
            'model_id' => 'gpt-4o-mini',
            'model_name' => 'GPT-4o Mini',
            'input_price_per_1m' => 0.15,
            'output_price_per_1m' => 0.60,
        ]);

        // 10K input, 5K output
        $cost = $pricing->calculateCost(10_000, 5_000);

        // Expected: (10000/1M) * 0.15 + (5000/1M) * 0.60 = 0.0015 + 0.003 = 0.0045
        $this->assertEqualsWithDelta(0.0045, $cost, 0.000001);
    }
}
