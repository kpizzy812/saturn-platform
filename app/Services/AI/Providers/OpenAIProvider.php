<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIAnalysisResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class OpenAIProvider implements AIProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    private string $apiKey;

    private string $model;

    private int $maxTokens;

    private float $temperature;

    public function __construct()
    {
        $config = config('ai.providers.openai');
        $this->apiKey = $config['api_key'] ?? '';
        $this->model = $config['model'] ?? 'gpt-4o-mini';
        $this->maxTokens = $config['max_tokens'] ?? 2048;
        $this->temperature = $config['temperature'] ?? 0.3;
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function getName(): string
    {
        return 'openai';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function analyze(string $prompt, string $logContent): AIAnalysisResult
    {
        $content = $this->rawAnalyze($prompt, $logContent);
        $tokensUsed = null; // Token count not available in rawAnalyze

        return AIAnalysisResult::fromJson($content, $this->getName(), $this->model, $tokensUsed);
    }

    public function rawAnalyze(string $systemPrompt, string $userPrompt): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('OpenAI API key is not configured');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(90)->post(self::API_URL, [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
                    ],
                ],
            ]);

            if (! $response->successful()) {
                $error = $response->json('error.message', 'Unknown API error');
                Log::error('OpenAI API error', ['error' => $error, 'status' => $response->status()]);
                throw new RuntimeException("OpenAI API error: {$error}");
            }

            $data = $response->json();

            return $data['choices'][0]['message']['content'] ?? '';
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('OpenAI provider error', ['error' => $e->getMessage()]);
            throw new RuntimeException("OpenAI analysis failed: {$e->getMessage()}");
        }
    }
}
