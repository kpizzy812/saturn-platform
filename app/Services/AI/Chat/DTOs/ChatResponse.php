<?php

namespace App\Services\AI\Chat\DTOs;

/**
 * Represents an AI chat response with metadata.
 */
readonly class ChatResponse
{
    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public ?string $stopReason = null,
        public bool $success = true,
        public ?string $error = null,
    ) {}

    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    public static function success(
        string $content,
        string $provider,
        string $model,
        int $inputTokens = 0,
        int $outputTokens = 0,
        ?string $stopReason = null,
    ): self {
        return new self(
            content: $content,
            provider: $provider,
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            stopReason: $stopReason,
            success: true,
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
        ];
    }
}
