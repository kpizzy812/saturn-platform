<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\AiPricingService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AiPricingServiceTest extends TestCase
{
    private AiPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiPricingService;

        // Mock empty cache to avoid database calls
        Cache::shouldReceive('remember')
            ->andReturn([]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_calculates_cost_with_fallback_pricing_for_anthropic(): void
    {
        $cost = $this->service->calculateCost('anthropic', 'unknown-model', 1000, 500);

        // Fallback pricing: 3.00/1M input, 15.00/1M output
        $expectedCost = (1000 / 1_000_000) * 3.00 + (500 / 1_000_000) * 15.00;

        $this->assertEqualsWithDelta($expectedCost, $cost, 0.000001);
    }

    public function test_calculates_cost_with_fallback_pricing_for_openai(): void
    {
        $cost = $this->service->calculateCost('openai', 'unknown-model', 1000, 500);

        // Fallback from config: 0.0005 per 1k input, 0.0015 per 1k output
        // Converted to per 1M: 0.50/1M input, 1.50/1M output
        $expectedCost = (1000 / 1_000_000) * 0.50 + (500 / 1_000_000) * 1.50;

        $this->assertEqualsWithDelta($expectedCost, $cost, 0.000001);
    }

    public function test_normalizes_provider_names(): void
    {
        // Test that claude -> anthropic normalization works
        $costClaude = $this->service->calculateCost('claude', 'test-model', 1000, 500);
        $costAnthropic = $this->service->calculateCost('anthropic', 'test-model', 1000, 500);

        $this->assertEquals($costClaude, $costAnthropic);
    }

    public function test_get_pricing_returns_fallback_info_for_unknown_model(): void
    {
        $pricing = $this->service->getPricing('openai', 'unknown-model');

        $this->assertNotNull($pricing);
        $this->assertEquals('openai', $pricing['provider']);
        $this->assertEquals('unknown-model', $pricing['model_id']);
        $this->assertStringContainsString('fallback', $pricing['model_name']);
    }

    public function test_ollama_has_zero_cost(): void
    {
        $cost = $this->service->calculateCost('ollama', 'llama3.1', 10000, 5000);

        $this->assertEquals(0.0, $cost);
    }

    public function test_estimate_cost_returns_expected_structure(): void
    {
        $estimate = $this->service->estimateCost('anthropic', 'claude-sonnet-4', 5000, 2000);

        $this->assertArrayHasKey('estimated_input_tokens', $estimate);
        $this->assertArrayHasKey('estimated_output_tokens', $estimate);
        $this->assertArrayHasKey('estimated_cost_usd', $estimate);
        $this->assertArrayHasKey('pricing', $estimate);

        $this->assertEquals(5000, $estimate['estimated_input_tokens']);
        $this->assertEquals(2000, $estimate['estimated_output_tokens']);
    }

    public function test_calculates_zero_cost_for_zero_tokens(): void
    {
        $cost = $this->service->calculateCost('anthropic', 'claude-sonnet-4', 0, 0);

        $this->assertEquals(0.0, $cost);
    }

    public function test_handles_large_token_counts(): void
    {
        // 1 million tokens
        $cost = $this->service->calculateCost('anthropic', 'unknown', 1_000_000, 500_000);

        // Expected: 1M input * 3.00/1M + 500K output * 15.00/1M = 3.00 + 7.50 = 10.50
        $expectedCost = 3.00 + 7.50;

        $this->assertEqualsWithDelta($expectedCost, $cost, 0.001);
    }

    public function test_gpt_provider_normalizes_to_openai(): void
    {
        $costGpt = $this->service->calculateCost('gpt', 'test-model', 1000, 500);
        $costOpenai = $this->service->calculateCost('openai', 'test-model', 1000, 500);

        $this->assertEquals($costGpt, $costOpenai);
    }
}
