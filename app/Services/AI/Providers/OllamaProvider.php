<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIAnalysisResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class OllamaProvider implements AIProviderInterface
{
    private string $baseUrl;

    private string $model;

    private int $timeout;

    public function __construct()
    {
        $config = config('ai.providers.ollama');
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://localhost:11434', '/');
        $this->model = $config['model'] ?? 'llama3.1';
        $this->timeout = $config['timeout'] ?? 120;
    }

    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl.'/api/tags');

            if (! $response->successful()) {
                return false;
            }

            // Check if the required model is available
            $models = collect($response->json('models', []))->pluck('name')->toArray();

            return in_array($this->model, $models, true) ||
                   in_array($this->model.':latest', $models, true);
        } catch (\Throwable $e) {
            Log::debug('Ollama availability check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'ollama';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function analyze(string $prompt, string $logContent): AIAnalysisResult
    {
        $content = $this->rawAnalyze($prompt, $logContent);

        return AIAnalysisResult::fromJson($content, $this->getName(), $this->model, null);
    }

    public function rawAnalyze(string $systemPrompt, string $userPrompt): string
    {
        $fullPrompt = $systemPrompt."\n\n".$userPrompt;

        try {
            $response = Http::timeout($this->timeout)->post($this->baseUrl.'/api/generate', [
                'model' => $this->model,
                'prompt' => $fullPrompt,
                'stream' => false,
                'format' => 'json',
                'options' => [
                    'temperature' => 0.3,
                    'num_predict' => 2048,
                ],
            ]);

            if (! $response->successful()) {
                $error = $response->json('error', 'Unknown Ollama error');
                Log::error('Ollama API error', ['error' => $error, 'status' => $response->status()]);
                throw new RuntimeException("Ollama API error: {$error}");
            }

            $data = $response->json();
            $content = $data['response'] ?? '';

            return $this->extractJson($content);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Ollama provider error', ['error' => $e->getMessage()]);
            throw new RuntimeException("Ollama analysis failed: {$e->getMessage()}");
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
