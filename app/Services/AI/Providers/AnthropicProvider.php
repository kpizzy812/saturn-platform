<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIAnalysisResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class AnthropicProvider implements AIProviderInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private string $apiKey;

    private string $model;

    private int $maxTokens;

    private float $temperature;

    public function __construct()
    {
        $config = config('ai.providers.claude');
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'claude-sonnet-4-20250514';
        $this->maxTokens = $config['max_tokens'] ?? 2048;
        $this->temperature = $config['temperature'] ?? 0.3;
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function getName(): string
    {
        return 'claude';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Last API response usage data.
     */
    private ?array $lastUsage = null;

    public function analyze(string $prompt, string $logContent): AIAnalysisResult
    {
        $content = $this->rawAnalyze($prompt, $logContent);
        $usage = $this->getLastUsage();

        return AIAnalysisResult::fromJson(
            $content,
            $this->getName(),
            $this->model,
            tokensUsed: $usage ? $usage['input_tokens'] + $usage['output_tokens'] : null,
            inputTokens: $usage['input_tokens'] ?? null,
            outputTokens: $usage['output_tokens'] ?? null,
        );
    }

    /**
     * Get usage data from last API call.
     */
    public function getLastUsage(): ?array
    {
        return $this->lastUsage;
    }

    public function rawAnalyze(string $systemPrompt, string $userPrompt): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('Anthropic API key is not configured');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(90)->post(self::API_URL, [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'system' => $systemPrompt,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
                    ],
                ],
            ]);

            if (! $response->successful()) {
                $error = $response->json('error.message', 'Unknown API error');
                Log::error('Anthropic API error', ['error' => $error, 'status' => $response->status()]);
                throw new RuntimeException("Anthropic API error: {$error}");
            }

            $data = $response->json();
            $content = $data['content'][0]['text'] ?? '';

            // Store usage data for later retrieval
            $this->lastUsage = [
                'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                'output_tokens' => $data['usage']['output_tokens'] ?? 0,
            ];

            // Extract JSON from response (it might be wrapped in markdown code blocks)
            return $this->extractJson($content);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Anthropic provider error', ['error' => $e->getMessage()]);
            throw new RuntimeException("Anthropic analysis failed: {$e->getMessage()}");
        }
    }

    /**
     * Extract JSON from response that might be wrapped in markdown code blocks.
     */
    private function extractJson(string $content): string
    {
        // Try to find JSON in code blocks
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $content, $matches)) {
            return $matches[1];
        }

        // Try to find raw JSON object
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            return $matches[0];
        }

        return $content;
    }
}
