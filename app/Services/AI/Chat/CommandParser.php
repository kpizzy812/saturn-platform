<?php

namespace App\Services\AI\Chat;

use App\Services\AI\Chat\Contracts\ChatProviderInterface;
use App\Services\AI\Chat\DTOs\ChatMessage;
use App\Services\AI\Chat\DTOs\IntentResult;
use App\Services\AI\Chat\DTOs\ToolCall;
use App\Services\AI\Chat\Providers\AnthropicChatProvider;
use App\Services\AI\Chat\Providers\OpenAIChatProvider;
use Illuminate\Support\Facades\Log;

/**
 * Parses user messages to detect actionable intents.
 * Supports structured output and function calling for improved reliability.
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
        'none',
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
     * @param  bool  $useToolCalling  Use function calling / tool use for more reliable parsing
     */
    public function parse(string $message, ?array $context = null, bool $useToolCalling = true): IntentResult
    {
        // First try simple keyword-based detection
        $simpleResult = $this->parseSimple($message, $context);
        if ($simpleResult->hasIntent()) {
            return $simpleResult;
        }

        // Fall back to AI-based parsing if provider is available
        if ($this->provider && $this->provider->isAvailable()) {
            // Use tool calling for more structured and reliable parsing
            if ($useToolCalling) {
                return $this->parseWithToolCalling($message, $context);
            }

            return $this->parseWithAI($message, $context);
        }

        return IntentResult::none();
    }

    /**
     * Parse with function calling / tool use for structured output.
     * This is more reliable than parsing free-form text.
     */
    private function parseWithToolCalling(string $message, ?array $context = null): IntentResult
    {
        try {
            $systemPrompt = $this->buildToolCallingSystemPrompt($context);
            $userPrompt = $message;

            $messages = [
                ChatMessage::system($systemPrompt),
                ChatMessage::user($userPrompt),
            ];

            // Get provider-specific tools and call
            if ($this->provider instanceof OpenAIChatProvider) {
                return $this->parseWithOpenAITools($messages, $context);
            } elseif ($this->provider instanceof AnthropicChatProvider) {
                return $this->parseWithAnthropicTools($messages, $context);
            }

            // Fall back to regular AI parsing
            return $this->parseWithAI($message, $context);
        } catch (\Throwable $e) {
            Log::error('CommandParser tool calling error', ['error' => $e->getMessage()]);

            // Fall back to regular parsing
            return $this->parseWithAI($message, $context);
        }
    }

    /**
     * Parse using OpenAI function calling.
     */
    private function parseWithOpenAITools(array $messages, ?array $context): IntentResult
    {
        $tools = ToolDefinitions::parseIntentOnlyOpenAI();

        /** @var OpenAIChatProvider $provider */
        $provider = $this->provider;

        // Force the model to call parse_intent function
        $response = $provider->chat($messages, $tools, 'parse_intent');

        if (! $response->success) {
            Log::warning('OpenAI tool calling failed', ['error' => $response->error]);

            return IntentResult::none();
        }

        // Check for tool call in response
        if ($response->hasToolCalls()) {
            $toolCall = $response->getToolCall('parse_intent');
            if ($toolCall) {
                return $this->buildIntentFromToolCall($toolCall, $context);
            }
        }

        // If no tool call, try to parse content as structured output
        if ($response->content) {
            return $this->parseStructuredContent($response->content, $context);
        }

        return IntentResult::none();
    }

    /**
     * Parse using Anthropic tool use.
     */
    private function parseWithAnthropicTools(array $messages, ?array $context): IntentResult
    {
        $tools = ToolDefinitions::parseIntentOnlyAnthropic();

        /** @var AnthropicChatProvider $provider */
        $provider = $this->provider;

        // Force the model to use parse_intent tool
        $response = $provider->chat($messages, $tools, 'parse_intent');

        if (! $response->success) {
            Log::warning('Anthropic tool use failed', ['error' => $response->error]);

            return IntentResult::none();
        }

        // Check for tool use in response
        if ($response->hasToolCalls()) {
            $toolCall = $response->getToolCall('parse_intent');
            if ($toolCall) {
                return $this->buildIntentFromToolCall($toolCall, $context);
            }
        }

        // If no tool call but has content, use it as response text
        if ($response->content) {
            return IntentResult::none($response->content);
        }

        return IntentResult::none();
    }

    /**
     * Build IntentResult from a tool call.
     */
    private function buildIntentFromToolCall(ToolCall $toolCall, ?array $context): IntentResult
    {
        $args = $toolCall->arguments;

        $intent = $args['intent'] ?? null;
        $confidence = (float) ($args['confidence'] ?? 0.0);
        $responseText = $args['response_text'] ?? null;

        // Handle 'none' intent
        if (! $intent || $intent === 'none' || ! in_array($intent, self::ALLOWED_INTENTS, true)) {
            return IntentResult::none($responseText);
        }

        $params = [];

        // Extract resource info from tool call
        $resourceType = $args['resource_type'] ?? null;
        if ($resourceType && $resourceType !== 'null') {
            $params['resource_type'] = $resourceType;
        }

        $resourceName = $args['resource_name'] ?? null;
        if ($resourceName && $resourceName !== 'null') {
            $params['resource_name'] = $resourceName;
        }

        $resourceId = $args['resource_id'] ?? null;
        if ($resourceId && $resourceId !== 'null') {
            $params['resource_id'] = $resourceId;
        }

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
    }

    /**
     * Parse structured JSON content (fallback when tool call not present).
     */
    private function parseStructuredContent(string $content, ?array $context): IntentResult
    {
        try {
            $json = $this->extractJson($content);
            $data = json_decode($json, true);

            if (! $data) {
                return IntentResult::none($content);
            }

            // Create a mock tool call to reuse the same parsing logic
            $mockToolCall = new ToolCall(
                id: 'structured_output',
                name: 'parse_intent',
                arguments: $data,
            );

            return $this->buildIntentFromToolCall($mockToolCall, $context);
        } catch (\Throwable $e) {
            Log::warning('Failed to parse structured content', ['error' => $e->getMessage()]);

            return IntentResult::none($content);
        }
    }

    /**
     * Build system prompt for tool calling.
     */
    private function buildToolCallingSystemPrompt(?array $context = null): string
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
You are an intent parser for a PaaS (Platform as a Service) system called Saturn.
Your job is to analyze user messages and call the parse_intent tool with the detected intent.

IMPORTANT: You MUST call the parse_intent tool for EVERY message. Never respond with plain text.

Available intents:
- deploy: Deploy/redeploy an application or service
- restart: Restart an application, service, or database
- stop: Stop an application, service, or database
- start: Start a stopped application, service, or database
- logs: Show logs for a resource
- status: Check the status of a resource
- help: Show help information
- none: No actionable intent detected (for questions, greetings, etc.)

Guidelines:
- Set confidence based on how clear the user's intent is (0.0 = unclear, 1.0 = very clear)
- Extract resource_type, resource_name, resource_id if mentioned
- Provide a helpful response_text in the same language as the user's message
- Support both English and Russian languages
{$contextInfo}
PROMPT;
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

        // Try to extract resource info including project/environment
        $resourceInfo = $this->extractResourceInfo($originalMessage);

        if ($resourceInfo['name']) {
            $params['resource_name'] = $resourceInfo['name'];
        }
        if ($resourceInfo['project']) {
            $params['project_name'] = $resourceInfo['project'];
        }
        if ($resourceInfo['environment']) {
            $params['environment_name'] = $resourceInfo['environment'];
        }

        $requiresConfirmation = in_array($intent, self::DANGEROUS_INTENTS, true);
        $confirmationMessage = null;

        if ($requiresConfirmation) {
            $displayName = $params['resource_name'] ?? $context['name'] ?? 'this resource';
            if (! empty($params['project_name'])) {
                $displayName .= " ({$params['project_name']}";
                if (! empty($params['environment_name'])) {
                    $displayName .= "/{$params['environment_name']}";
                }
                $displayName .= ')';
            }
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
        // Pattern: "deploy app-name" or "restart my-service" (English and Russian commands)
        $commands = 'deploy|restart|stop|start|logs|status|деплой|задеплой|разверни|перезапусти|рестарт|останови|стоп|выключи|запусти|старт|включи|логи|статус';
        if (preg_match('/(?:'.$commands.')\s+([a-zA-Z0-9_-]+)/ui', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract resource info including project and environment from message.
     * Supports formats like:
     * - "deploy app-name in project/environment"
     * - "deploy app-name from project/environment"
     * - "деплой app-name в project/environment"
     */
    public function extractResourceInfo(string $message): array
    {
        $info = [
            'name' => null,
            'project' => null,
            'environment' => null,
        ];

        // Extract resource name
        $info['name'] = $this->extractResourceName($message);

        // Pattern for "in project/environment" or "from project/environment"
        // Also supports Russian "в project/environment"
        if (preg_match('/(?:in|from|в)\s+([a-zA-Z0-9_-]+)\/([a-zA-Z0-9_-]+)/ui', $message, $matches)) {
            $info['project'] = $matches[1];
            $info['environment'] = $matches[2];
        }
        // Pattern for just project name "in project" or "from project"
        elseif (preg_match('/(?:in|from|в)\s+([a-zA-Z0-9_-]+)(?:\s|$)/ui', $message, $matches)) {
            $info['project'] = $matches[1];
        }
        // Pattern for "project project-name" or "проект project-name"
        elseif (preg_match('/(?:project|проект)\s+([a-zA-Z0-9_-]+)/ui', $message, $matches)) {
            $info['project'] = $matches[1];
        }
        // Pattern for "environment env-name" or "окружение env-name"
        if (preg_match('/(?:environment|env|окружение)\s+([a-zA-Z0-9_-]+)/ui', $message, $matches)) {
            $info['environment'] = $matches[1];
        }

        return $info;
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
