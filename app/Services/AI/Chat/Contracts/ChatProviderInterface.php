<?php

namespace App\Services\AI\Chat\Contracts;

use App\Services\AI\Chat\DTOs\ChatMessage;
use App\Services\AI\Chat\DTOs\ChatResponse;
use Generator;

/**
 * Interface for AI chat providers.
 */
interface ChatProviderInterface
{
    /**
     * Check if the provider is available and configured.
     */
    public function isAvailable(): bool;

    /**
     * Get the provider name.
     */
    public function getName(): string;

    /**
     * Get the model being used.
     */
    public function getModel(): string;

    /**
     * Send a chat message and get response.
     *
     * @param  ChatMessage[]  $messages  Array of messages in the conversation
     * @param  array|null  $tools  Optional tool definitions
     */
    public function chat(array $messages, ?array $tools = null): ChatResponse;

    /**
     * Stream a chat response.
     *
     * @param  ChatMessage[]  $messages  Array of messages in the conversation
     * @return Generator<string> Yields content chunks
     */
    public function streamChat(array $messages): Generator;
}
