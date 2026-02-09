<?php

namespace App\Services\AI\Chat\Providers;

use App\Services\AI\Chat\Contracts\ChatProviderInterface;
use App\Services\AI\Chat\DTOs\ChatMessage;
use App\Services\AI\Chat\DTOs\ChatResponse;
use App\Services\AI\Chat\DTOs\ToolCall;
use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class AnthropicChatProvider implements ChatProviderInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private string $apiKey;

    private string $model;

    private int $maxTokens;

    private float $temperature;

    public function __construct()
    {
        $config = config('ai.providers.claude');

        // DB settings (admin panel) take priority over .env
        $settings = \App\Models\InstanceSettings::get();
        $dbKey = $settings->ai_anthropic_api_key ?? '';
        $dbModel = $settings->ai_claude_model ?? '';

        $this->apiKey = ! empty($dbKey) ? $dbKey : ($config['api_key'] ?? '');
        $this->model = ! empty($dbModel) ? $dbModel : ($config['chat_model'] ?? $config['model'] ?? 'claude-sonnet-4-20250514');
        $this->maxTokens = $config['chat_max_tokens'] ?? 1024;
        $this->temperature = $config['chat_temperature'] ?? 0.7;
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
     * Send a chat request with optional tools.
     *
     * @param  ChatMessage[]  $messages
     * @param  array|null  $tools  Tool definitions in Anthropic format
     * @param  string|null  $toolChoice  Force tool use: 'auto', 'any', or specific tool name
     */
    public function chat(array $messages, ?array $tools = null, ?string $toolChoice = null): ChatResponse
    {
        if (! $this->isAvailable()) {
            return ChatResponse::failed('Anthropic API key is not configured', $this->getName(), $this->model);
        }

        try {
            $formattedMessages = $this->formatMessages($messages);
            $systemPrompt = $this->extractSystemPrompt($messages);

            $payload = [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'messages' => $formattedMessages,
            ];

            if ($systemPrompt) {
                $payload['system'] = $systemPrompt;
            }

            if ($tools) {
                $payload['tools'] = $tools;

                // Set tool_choice to force tool use
                if ($toolChoice === 'any') {
                    $payload['tool_choice'] = ['type' => 'any'];
                } elseif ($toolChoice && $toolChoice !== 'auto') {
                    $payload['tool_choice'] = ['type' => 'tool', 'name' => $toolChoice];
                }
            }

            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post(self::API_URL, $payload);

            if (! $response->successful()) {
                $error = $response->json('error.message', 'Unknown API error');
                Log::error('Anthropic Chat API error', ['error' => $error, 'status' => $response->status()]);

                return ChatResponse::failed("API error: {$error}", $this->getName(), $this->model);
            }

            $data = $response->json();

            return $this->parseResponse($data);
        } catch (\Throwable $e) {
            Log::error('Anthropic chat error', ['error' => $e->getMessage()]);

            return ChatResponse::failed($e->getMessage(), $this->getName(), $this->model);
        }
    }

    /**
     * Parse Anthropic API response including tool_use blocks.
     */
    private function parseResponse(array $data): ChatResponse
    {
        $content = '';
        $toolCalls = [];

        // Parse content blocks (can have multiple: text and tool_use)
        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content .= $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = ToolCall::fromAnthropic($block);
            }
        }

        $inputTokens = $data['usage']['input_tokens'] ?? 0;
        $outputTokens = $data['usage']['output_tokens'] ?? 0;
        $stopReason = $data['stop_reason'] ?? null;

        return ChatResponse::success(
            content: $content,
            provider: $this->getName(),
            model: $this->model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            stopReason: $stopReason,
            toolCalls: $toolCalls,
        );
    }

    public function streamChat(array $messages): Generator
    {
        if (! $this->isAvailable()) {
            yield 'Error: Anthropic API key is not configured';

            return;
        }

        try {
            $formattedMessages = $this->formatMessages($messages);
            $systemPrompt = $this->extractSystemPrompt($messages);

            $payload = [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
                'messages' => $formattedMessages,
                'stream' => true,
            ];

            if ($systemPrompt) {
                $payload['system'] = $systemPrompt;
            }

            $ch = curl_init(self::API_URL);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'x-api-key: '.$this->apiKey,
                    'anthropic-version: 2023-06-01',
                    'content-type: application/json',
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
                    if ($data && isset($data['delta']['text'])) {
                        yield $data['delta']['text'];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Anthropic stream error', ['error' => $e->getMessage()]);
            yield 'Error: '.$e->getMessage();
        }
    }

    /**
     * Format messages for Anthropic API (exclude system messages).
     *
     * @param  ChatMessage[]  $messages
     */
    private function formatMessages(array $messages): array
    {
        $formatted = [];
        foreach ($messages as $message) {
            if ($message->role === 'system') {
                continue;
            }
            $formatted[] = $message->toArray();
        }

        return $formatted;
    }

    /**
     * Extract system prompt from messages.
     *
     * @param  ChatMessage[]  $messages
     */
    private function extractSystemPrompt(array $messages): ?string
    {
        foreach ($messages as $message) {
            if ($message->role === 'system') {
                return $message->content;
            }
        }

        return null;
    }
}
