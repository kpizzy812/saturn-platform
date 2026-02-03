<?php

namespace App\Services\AI\Chat\Providers;

use App\Services\AI\Chat\Contracts\ChatProviderInterface;
use App\Services\AI\Chat\DTOs\ChatMessage;
use App\Services\AI\Chat\DTOs\ChatResponse;
use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OpenAIChatProvider implements ChatProviderInterface
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
        $this->model = $config['chat_model'] ?? $config['model'] ?? 'gpt-4o-mini';
        $this->maxTokens = $config['chat_max_tokens'] ?? 1024;
        $this->temperature = $config['chat_temperature'] ?? 0.7;
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

    public function chat(array $messages, ?array $tools = null): ChatResponse
    {
        if (! $this->isAvailable()) {
            return ChatResponse::failed('OpenAI API key is not configured', $this->getName(), $this->model);
        }

        try {
            $formattedMessages = $this->formatMessages($messages);

            $payload = [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'messages' => $formattedMessages,
            ];

            if ($tools) {
                $payload['tools'] = $tools;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post(self::API_URL, $payload);

            if (! $response->successful()) {
                $error = $response->json('error.message', 'Unknown API error');
                Log::error('OpenAI Chat API error', ['error' => $error, 'status' => $response->status()]);

                return ChatResponse::failed("API error: {$error}", $this->getName(), $this->model);
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $inputTokens = $data['usage']['prompt_tokens'] ?? 0;
            $outputTokens = $data['usage']['completion_tokens'] ?? 0;
            $stopReason = $data['choices'][0]['finish_reason'] ?? null;

            return ChatResponse::success(
                content: $content,
                provider: $this->getName(),
                model: $this->model,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                stopReason: $stopReason,
            );
        } catch (\Throwable $e) {
            Log::error('OpenAI chat error', ['error' => $e->getMessage()]);

            return ChatResponse::failed($e->getMessage(), $this->getName(), $this->model);
        }
    }

    public function streamChat(array $messages): Generator
    {
        if (! $this->isAvailable()) {
            yield 'Error: OpenAI API key is not configured';

            return;
        }

        try {
            $formattedMessages = $this->formatMessages($messages);

            $payload = [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'messages' => $formattedMessages,
                'stream' => true,
            ];

            $ch = curl_init(self::API_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer '.$this->apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$buffer) {
                    $buffer .= $data;

                    return strlen($data);
                },
            ]);

            $buffer = '';
            curl_exec($ch);
            curl_close($ch);

            // Parse SSE events
            $lines = explode("\n", $buffer);
            foreach ($lines as $line) {
                if (str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);
                    if ($json === '[DONE]') {
                        break;
                    }

                    $data = json_decode($json, true);
                    if ($data && isset($data['choices'][0]['delta']['content'])) {
                        yield $data['choices'][0]['delta']['content'];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('OpenAI stream error', ['error' => $e->getMessage()]);
            yield 'Error: '.$e->getMessage();
        }
    }

    /**
     * Format messages for OpenAI API.
     *
     * @param  ChatMessage[]  $messages
     */
    private function formatMessages(array $messages): array
    {
        $formatted = [];
        foreach ($messages as $message) {
            $formatted[] = $message->toArray();
        }

        return $formatted;
    }
}
