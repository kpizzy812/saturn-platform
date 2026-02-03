<?php

namespace App\Services\AI\Chat;

use App\Events\AiChatMessageReceived;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\AI\Chat\Contracts\ChatProviderInterface;
use App\Services\AI\Chat\DTOs\ChatMessage;
use App\Services\AI\Chat\DTOs\ChatResponse;
use App\Services\AI\Chat\DTOs\CommandResult;
use App\Services\AI\Chat\DTOs\IntentResult;
use App\Services\AI\Chat\Providers\AnthropicChatProvider;
use App\Services\AI\Chat\Providers\OpenAIChatProvider;
use Generator;
use Illuminate\Support\Facades\Log;

/**
 * Main AI Chat service for handling conversations.
 */
class AiChatService
{
    private ?ChatProviderInterface $provider = null;

    private CommandParser $commandParser;

    /**
     * Provider fallback order.
     */
    private array $providerOrder = ['claude', 'openai'];

    public function __construct()
    {
        $this->providerOrder = config('ai.chat.fallback_order', config('ai.fallback_order', ['claude', 'openai']));
        $this->commandParser = new CommandParser;
    }

    /**
     * Check if AI chat is enabled.
     */
    public function isEnabled(): bool
    {
        return config('ai.chat.enabled', true) && config('ai.enabled', true);
    }

    /**
     * Check if any provider is available.
     */
    public function isAvailable(): bool
    {
        return $this->getProvider() !== null;
    }

    /**
     * Check if both enabled and available.
     */
    public function isEnabledAndAvailable(): bool
    {
        return $this->isEnabled() && $this->isAvailable();
    }

    /**
     * Get the active chat provider.
     */
    public function getProvider(): ?ChatProviderInterface
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        $defaultProvider = config('ai.chat.default_provider', config('ai.default_provider', 'claude'));

        // Try default provider first
        $provider = $this->createProvider($defaultProvider);
        if ($provider?->isAvailable()) {
            $this->provider = $provider;

            return $this->provider;
        }

        // Try fallback providers
        foreach ($this->providerOrder as $providerName) {
            if ($providerName === $defaultProvider) {
                continue;
            }

            $provider = $this->createProvider($providerName);
            if ($provider?->isAvailable()) {
                $this->provider = $provider;

                return $this->provider;
            }
        }

        return null;
    }

    /**
     * Create a provider instance.
     */
    private function createProvider(string $name): ?ChatProviderInterface
    {
        return match ($name) {
            'claude', 'anthropic' => new AnthropicChatProvider,
            'openai' => new OpenAIChatProvider,
            default => null,
        };
    }

    /**
     * Get or create a chat session.
     */
    public function getOrCreateSession(
        User $user,
        int $teamId,
        ?string $contextType = null,
        ?int $contextId = null,
        ?string $contextName = null,
    ): AiChatSession {
        // Try to find existing active session
        $query = AiChatSession::query()
            ->where('user_id', $user->id)
            ->where('team_id', $teamId)
            ->active();

        if ($contextType && $contextId) {
            $query->forContext($contextType, $contextId);
        } else {
            $query->whereNull('context_type');
        }

        $session = $query->latest()->first();

        if ($session) {
            return $session;
        }

        // Create new session
        return AiChatSession::create([
            'user_id' => $user->id,
            'team_id' => $teamId,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'context_name' => $contextName,
        ]);
    }

    /**
     * Send a message and get response.
     */
    public function sendMessage(
        AiChatSession $session,
        string $content,
        bool $executeCommands = true,
    ): AiChatMessage {
        $startTime = microtime(true);

        // Save user message (no broadcast needed - frontend adds optimistically)
        $userMessage = $session->messages()->create([
            'role' => 'user',
            'content' => $content,
        ]);

        // Get context for command parsing
        $context = $this->buildContext($session);

        // Parse intent
        $intent = $this->parseIntent($content, $context);

        // Generate AI response
        $response = $this->generateResponse($session, $content, $intent, $context);

        // Execute command if detected and allowed
        $commandResult = null;
        if ($executeCommands && $intent->isActionable() && ! $intent->requiresConfirmation) {
            $commandResult = $this->executeCommand($session, $intent);
        }

        // Save assistant message
        $assistantMessage = $session->messages()->create([
            'role' => 'assistant',
            'content' => $this->formatResponse($response, $intent, $commandResult),
            'intent' => $intent->intent,
            'intent_params' => $intent->params ?: null,
            'command_status' => $commandResult?->success ? 'completed' : ($commandResult ? 'failed' : null),
            'command_result' => $commandResult?->message,
        ]);

        // Log usage
        $this->logUsage($session, $assistantMessage, $response, $startTime);

        // Broadcast assistant message
        broadcast(new AiChatMessageReceived($session, $assistantMessage));

        // Generate title for new sessions
        $session->generateTitle();

        return $assistantMessage;
    }

    /**
     * Stream a message response.
     *
     * @return Generator<string>
     */
    public function streamMessage(AiChatSession $session, string $content): Generator
    {
        $provider = $this->getProvider();
        if (! $provider) {
            yield 'AI service is not available. Please check your API keys.';

            return;
        }

        // Save user message
        $session->messages()->create([
            'role' => 'user',
            'content' => $content,
        ]);

        // Build conversation history
        $messages = $this->buildMessageHistory($session, $content);

        // Stream response
        $fullContent = '';
        foreach ($provider->streamChat($messages) as $chunk) {
            $fullContent .= $chunk;
            yield $chunk;
        }

        // Save assistant message after streaming completes
        $session->messages()->create([
            'role' => 'assistant',
            'content' => $fullContent,
        ]);

        $session->generateTitle();
    }

    /**
     * Parse intent from user message.
     */
    public function parseIntent(string $message, ?array $context = null): IntentResult
    {
        $provider = $this->getProvider();
        if ($provider) {
            $this->commandParser->setProvider($provider);
        }

        return $this->commandParser->parse($message, $context);
    }

    /**
     * Execute a command.
     */
    public function executeCommand(AiChatSession $session, IntentResult $intent): CommandResult
    {
        $executor = new CommandExecutor($session->user, $session->team_id);

        return $executor->execute($intent);
    }

    /**
     * Rate a message.
     */
    public function rateMessage(AiChatMessage $message, int $rating): bool
    {
        return $message->rate($rating);
    }

    /**
     * Get provider info.
     */
    public function getProviderInfo(): array
    {
        $provider = $this->getProvider();
        if (! $provider) {
            return ['provider' => null, 'model' => null];
        }

        return [
            'provider' => $provider->getName(),
            'model' => $provider->getModel(),
        ];
    }

    /**
     * Build context from session.
     */
    private function buildContext(AiChatSession $session): ?array
    {
        if (! $session->context_type || ! $session->context_id) {
            return null;
        }

        return [
            'type' => $session->context_type,
            'id' => $session->context_id,
            'name' => $session->context_name,
            'uuid' => $session->loadContext()?->uuid,
        ];
    }

    /**
     * Build message history for AI.
     */
    private function buildMessageHistory(AiChatSession $session, ?string $currentMessage = null): array
    {
        $messages = [];

        // Add system message
        $messages[] = ChatMessage::system($this->buildSystemPrompt($session));

        // Add recent conversation history (last 10 messages)
        $history = $session->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->latest()
            ->take(10)
            ->get()
            ->reverse();

        foreach ($history as $msg) {
            $messages[] = new ChatMessage($msg->role, $msg->content);
        }

        // Add current message if not already included
        if ($currentMessage && (! $history->count() || $history->last()->content !== $currentMessage)) {
            $messages[] = ChatMessage::user($currentMessage);
        }

        return $messages;
    }

    /**
     * Build system prompt.
     */
    private function buildSystemPrompt(AiChatSession $session): string
    {
        $contextInfo = '';
        if ($session->context_type) {
            $resource = $session->loadContext();
            $contextInfo = sprintf(
                "\n\nCurrent context:\n- Resource type: %s\n- Resource name: %s\n- Resource ID: %d",
                $session->context_type,
                $session->context_name ?? ($resource?->name ?? 'Unknown'),
                $session->context_id
            );
        }

        $customPrompt = config('ai.prompts.chat_system', '');
        if ($customPrompt) {
            return $customPrompt.$contextInfo;
        }

        return <<<PROMPT
You are Saturn AI, an intelligent assistant for the Saturn Platform - a self-hosted PaaS (Platform as a Service).

Your capabilities:
- Help users manage their applications, services, databases, and servers
- Execute commands like deploy, restart, stop, start when requested
- Provide logs and status information
- Answer questions about the platform and resources

Communication style:
- Be concise and helpful
- Use markdown formatting when appropriate
- Support both English and Russian languages
- Be direct but friendly

When users ask for actions (deploy, restart, etc.), you will parse their intent and execute the appropriate command if you have sufficient context. If you need more information, ask for it.

When showing logs or status, format the output clearly.
{$contextInfo}
PROMPT;
    }

    /**
     * Generate AI response.
     */
    private function generateResponse(
        AiChatSession $session,
        string $userMessage,
        IntentResult $intent,
        ?array $context,
    ): ChatResponse {
        $provider = $this->getProvider();
        if (! $provider) {
            return ChatResponse::failed(
                'AI service is not available',
                'none',
                'none'
            );
        }

        try {
            // If intent requires confirmation, return confirmation message
            if ($intent->requiresConfirmation) {
                return ChatResponse::success(
                    $intent->confirmationMessage ?? "Are you sure you want to {$intent->intent}?",
                    $provider->getName(),
                    $provider->getModel()
                );
            }

            // If intent has a predefined response, use it
            if ($intent->responseText) {
                return ChatResponse::success(
                    $intent->responseText,
                    $provider->getName(),
                    $provider->getModel()
                );
            }

            // Generate response via AI
            $messages = $this->buildMessageHistory($session, $userMessage);

            return $provider->chat($messages);
        } catch (\Throwable $e) {
            Log::error('AI Chat response generation failed', ['error' => $e->getMessage()]);

            return ChatResponse::failed($e->getMessage(), $provider?->getName() ?? 'none', $provider?->getModel() ?? 'none');
        }
    }

    /**
     * Format response with command result.
     */
    private function formatResponse(ChatResponse $response, IntentResult $intent, ?CommandResult $commandResult): string
    {
        $content = $response->content;

        // If command was executed, append result
        if ($commandResult) {
            if ($commandResult->success) {
                $content = $commandResult->message;
            } else {
                $content = "**Error:** {$commandResult->message}";
            }
        }

        // If intent requires confirmation, show confirmation message
        if ($intent->requiresConfirmation && $intent->confirmationMessage) {
            $content = $intent->confirmationMessage."\n\nPlease confirm by saying 'yes' or 'confirm'.";
        }

        return $content ?: 'I apologize, but I was unable to generate a response.';
    }

    /**
     * Log AI usage.
     */
    private function logUsage(
        AiChatSession $session,
        AiChatMessage $message,
        ChatResponse $response,
        float $startTime,
    ): void {
        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        try {
            AiUsageLog::create([
                'user_id' => $session->user_id,
                'team_id' => $session->team_id,
                'message_id' => $message->id,
                'provider' => $response->provider,
                'model' => $response->model,
                'operation' => 'chat',
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'cost_usd' => $this->calculateCost($response),
                'response_time_ms' => $responseTimeMs,
                'success' => $response->success,
                'error_message' => $response->error,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log AI usage', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Calculate cost based on provider pricing.
     */
    private function calculateCost(ChatResponse $response): float
    {
        $pricing = config("ai.chat.pricing.{$response->provider}", [
            'input_per_1k' => 0.003,
            'output_per_1k' => 0.015,
        ]);

        $inputCost = ($response->inputTokens / 1000) * $pricing['input_per_1k'];
        $outputCost = ($response->outputTokens / 1000) * $pricing['output_per_1k'];

        return $inputCost + $outputCost;
    }
}
