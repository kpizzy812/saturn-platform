<?php

namespace App\Services\AI\Chat\DTOs;

/**
 * Result of intent detection from user message.
 */
readonly class IntentResult
{
    public function __construct(
        public ?string $intent,           // deploy, restart, stop, start, logs, status, help, null
        public array $params = [],        // {resource_type: 'application', resource_id: 123}
        public float $confidence = 0.0,   // 0.0-1.0
        public bool $requiresConfirmation = false,
        public ?string $confirmationMessage = null,
        public ?string $responseText = null, // AI response to show user
    ) {}

    public function hasIntent(): bool
    {
        return $this->intent !== null;
    }

    public function isActionable(): bool
    {
        return $this->hasIntent() && in_array($this->intent, [
            'deploy',
            'restart',
            'stop',
            'start',
            'logs',
            'status',
        ], true);
    }

    public function isDangerous(): bool
    {
        return in_array($this->intent, ['deploy', 'stop', 'delete'], true);
    }

    public function getResourceType(): ?string
    {
        return $this->params['resource_type'] ?? null;
    }

    public function getResourceId(): ?int
    {
        $id = $this->params['resource_id'] ?? null;

        return $id ? (int) $id : null;
    }

    public function getResourceUuid(): ?string
    {
        return $this->params['resource_uuid'] ?? null;
    }

    public static function none(?string $responseText = null): self
    {
        return new self(
            intent: null,
            responseText: $responseText,
        );
    }

    public static function withConfirmation(
        string $intent,
        array $params,
        string $confirmationMessage,
        float $confidence = 1.0,
    ): self {
        return new self(
            intent: $intent,
            params: $params,
            confidence: $confidence,
            requiresConfirmation: true,
            confirmationMessage: $confirmationMessage,
        );
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'params' => $this->params,
            'confidence' => $this->confidence,
            'requires_confirmation' => $this->requiresConfirmation,
            'confirmation_message' => $this->confirmationMessage,
            'response_text' => $this->responseText,
        ];
    }
}
