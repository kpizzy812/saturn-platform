<?php

namespace App\Services\AI\Chat\DTOs;

/**
 * Represents an AI chat response with metadata.
 */
readonly class ChatResponse
{
    /**
     * @param  ToolCall[]  $toolCalls
     */
    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?string $stopReason = null,
        public bool $success = true,
        public ?string $error = null,
        public array $toolCalls = [],
    ) {}

    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Check if response contains tool calls.
     */
    public function hasToolCalls(): bool
    {
        return ! empty($this->toolCalls);
    }

    /**
     * Get the first tool call if any.
     */
    public function getFirstToolCall(): ?ToolCall
    {
        return $this->toolCalls[0] ?? null;
    }

    /**
     * Get tool call by name.
     */
    public function getToolCall(string $name): ?ToolCall
    {
        foreach ($this->toolCalls as $toolCall) {
            if ($toolCall->name === $name) {
                return $toolCall;
            }
        }

        return null;
    }

    /**
     * Check if response was stopped due to tool use.
     */
    public function stoppedForToolUse(): bool
    {
        return $this->stopReason === 'tool_use' || $this->stopReason === 'tool_calls';
    }

    /**
     * @param  ToolCall[]  $toolCalls
     */
    public static function success(
        string $content,
        string $provider,
        string $model,
        int $inputTokens = 0,
        int $outputTokens = 0,
        ?string $stopReason = null,
        array $toolCalls = [],
    ): self {
        return new self(
            content: $content,
            provider: $provider,
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            stopReason: $stopReason,
            success: true,
            toolCalls: $toolCalls,
        );
    }

    public static function failed(string $error, string $provider, string $model): self
    {
        return new self(
            content: '',
            provider: $provider,
            model: $model,
            success: false,
            error: $error,
        );
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'provider' => $this->provider,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'stop_reason' => $this->stopReason,
            'success' => $this->success,
            'error' => $this->error,
            'tool_calls' => array_map(fn (ToolCall $tc) => $tc->toArray(), $this->toolCalls),
        ];
    }
}
