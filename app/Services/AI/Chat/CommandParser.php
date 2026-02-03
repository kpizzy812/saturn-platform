<?php

namespace App\Services\AI\Chat;

use App\Services\AI\Chat\Contracts\ChatProviderInterface;
use App\Services\AI\Chat\DTOs\ChatMessage;
use App\Services\AI\Chat\DTOs\IntentResult;
use Illuminate\Support\Facades\Log;

/**
 * Parses user messages to detect actionable intents.
 */
class CommandParser
{
    private ?ChatProviderInterface $provider = null;

    /**
     * Allowed intents for command execution.
     */
    private const ALLOWED_INTENTS = [
        'deploy',
        'restart',
        'stop',
        'start',
        'logs',
        'status',
        'help',
    ];

    /**
     * Intents that require confirmation before execution.
     */
    private const DANGEROUS_INTENTS = [
        'deploy',
        'stop',
    ];

    public function __construct(?ChatProviderInterface $provider = null)
    {
        $this->provider = $provider;
    }

    /**
     * Set the provider for AI-based parsing.
     */
    public function setProvider(ChatProviderInterface $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Parse user message to detect intent.
     *
     * @param  array|null  $context  Current context (resource type, id, name)
     */
    public function parse(string $message, ?array $context = null): IntentResult
    {
        // First try simple keyword-based detection
        $simpleResult = $this->parseSimple($message, $context);
        if ($simpleResult->hasIntent()) {
            return $simpleResult;
        }

        // Fall back to AI-based parsing if provider is available
        if ($this->provider && $this->provider->isAvailable()) {
            return $this->parseWithAI($message, $context);
        }

        return IntentResult::none();
    }

    /**
     * Simple keyword-based intent detection.
     */
    private function parseSimple(string $message, ?array $context = null): IntentResult
    {
        $message = strtolower(trim($message));

        // Deploy patterns
        if (preg_match('/^(deploy|деплой|задеплой|разверни|redeploy)/ui', $message)) {
            return $this->createIntentResult('deploy', $context, $message);
        }

        // Restart patterns
        if (preg_match('/^(restart|перезапусти|рестарт|reboot)/ui', $message)) {
            return $this->createIntentResult('restart', $context, $message);
        }

        // Stop patterns
        if (preg_match('/^(stop|останови|стоп|выключи)/ui', $message)) {
            return $this->createIntentResult('stop', $context, $message);
        }

        // Start patterns
        if (preg_match('/^(start|запусти|старт|включи)/ui', $message)) {
            return $this->createIntentResult('start', $context, $message);
        }

        // Logs patterns
        if (preg_match('/^(logs?|логи?|покажи логи|show logs)/ui', $message)) {
            return $this->createIntentResult('logs', $context, $message);
        }

        // Status patterns
        if (preg_match('/^(status|статус|состояние|state)/ui', $message)) {
            return $this->createIntentResult('status', $context, $message);
        }

        // Help patterns
        if (preg_match('/^(help|помощь|помоги|что ты (умеешь|можешь))/ui', $message)) {
            return $this->createIntentResult('help', $context, $message);
        }

        return IntentResult::none();
    }

    /**
     * AI-based intent detection for complex messages.
     */
    private function parseWithAI(string $message, ?array $context = null): IntentResult
    {
        try {
            $systemPrompt = $this->buildSystemPrompt($context);
            $userPrompt = "User message: {$message}";

            $messages = [
                ChatMessage::system($systemPrompt),
                ChatMessage::user($userPrompt),
            ];

            $response = $this->provider->chat($messages);

            if (! $response->success) {
                Log::warning('AI intent parsing failed', ['error' => $response->error]);

                return IntentResult::none();
            }

            return $this->parseAIResponse($response->content, $context);
        } catch (\Throwable $e) {
            Log::error('CommandParser AI error', ['error' => $e->getMessage()]);

            return IntentResult::none();
        }
    }

    /**
     * Build system prompt for intent parsing.
     */
    private function buildSystemPrompt(?array $context = null): string
    {
        $contextInfo = '';
        if ($context) {
            $contextInfo = sprintf(
                "\n\nCurrent context:\n- Resource type: %s\n- Resource name: %s\n- Resource ID: %s",
                $context['type'] ?? 'none',
                $context['name'] ?? 'unknown',
                $context['id'] ?? 'unknown'
            );
        }

        return <<<PROMPT
You are an intent parser for a PaaS (Platform as a Service) system.
Analyze user messages and extract actionable intents.

Available intents:
- deploy: Deploy/redeploy an application or service
- restart: Restart an application, service, or database
- stop: Stop an application, service, or database
- start: Start a stopped application, service, or database
- logs: Show logs for a resource
- status: Check the status of a resource
- help: Show help information

Respond in JSON format:
{
    "intent": "intent_name or null",
    "confidence": 0.0-1.0,
    "params": {
        "resource_type": "application|service|database|server|null",
        "resource_name": "name if mentioned or null",
        "resource_id": "id if mentioned or null"
    },
    "response_text": "Your response to the user"
}

If no actionable intent is detected, set intent to null and provide a helpful response.
Support both English and Russian languages.
{$contextInfo}
PROMPT;
    }

    /**
     * Parse AI response to IntentResult.
     */
    private function parseAIResponse(string $content, ?array $context = null): IntentResult
    {
        try {
            // Extract JSON from response
            $json = $this->extractJson($content);
            $data = json_decode($json, true);

            if (! $data) {
                return IntentResult::none($content);
            }

            $intent = $data['intent'] ?? null;
            $confidence = (float) ($data['confidence'] ?? 0.0);
            $responseText = $data['response_text'] ?? null;

            if (! $intent || ! in_array($intent, self::ALLOWED_INTENTS, true)) {
                return IntentResult::none($responseText);
            }

            $params = $data['params'] ?? [];

            // Merge with context if not specified in params
            if ($context) {
                $params['resource_type'] ??= $context['type'] ?? null;
                $params['resource_id'] ??= $context['id'] ?? null;
                $params['resource_uuid'] ??= $context['uuid'] ?? null;
            }

            $requiresConfirmation = in_array($intent, self::DANGEROUS_INTENTS, true);
            $confirmationMessage = null;

            if ($requiresConfirmation) {
                $resourceName = $params['resource_name'] ?? $context['name'] ?? 'this resource';
                $confirmationMessage = $this->getConfirmationMessage($intent, $resourceName);
            }

            return new IntentResult(
                intent: $intent,
                params: $params,
                confidence: $confidence,
                requiresConfirmation: $requiresConfirmation,
                confirmationMessage: $confirmationMessage,
                responseText: $responseText,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to parse AI intent response', ['error' => $e->getMessage(), 'content' => $content]);

            return IntentResult::none();
        }
    }

    /**
     * Create intent result with context.
     */
    private function createIntentResult(string $intent, ?array $context, string $originalMessage): IntentResult
    {
        $params = [];

        if ($context) {
            $params['resource_type'] = $context['type'] ?? null;
            $params['resource_id'] = $context['id'] ?? null;
            $params['resource_uuid'] = $context['uuid'] ?? null;
        }

        // Try to extract resource name from message
        $resourceName = $this->extractResourceName($originalMessage);
        if ($resourceName) {
            $params['resource_name'] = $resourceName;
        }

        $requiresConfirmation = in_array($intent, self::DANGEROUS_INTENTS, true);
        $confirmationMessage = null;

        if ($requiresConfirmation) {
            $displayName = $params['resource_name'] ?? $context['name'] ?? 'this resource';
            $confirmationMessage = $this->getConfirmationMessage($intent, $displayName);
        }

        return new IntentResult(
            intent: $intent,
            params: $params,
            confidence: 1.0,
            requiresConfirmation: $requiresConfirmation,
            confirmationMessage: $confirmationMessage,
        );
    }

    /**
     * Extract resource name from message.
     */
    private function extractResourceName(string $message): ?string
    {
        // Pattern: "deploy app-name" or "restart my-service"
        if (preg_match('/(?:deploy|restart|stop|start|logs|status)\s+([a-zA-Z0-9_-]+)/ui', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get confirmation message for dangerous intent.
     */
    private function getConfirmationMessage(string $intent, string $resourceName): string
    {
        return match ($intent) {
            'deploy' => "Are you sure you want to deploy **{$resourceName}**? This will trigger a new deployment.",
            'stop' => "Are you sure you want to stop **{$resourceName}**? The service will become unavailable.",
            default => "Are you sure you want to {$intent} **{$resourceName}**?",
        };
    }

    /**
     * Extract JSON from response that might be wrapped in markdown.
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
