<?php

namespace App\Services\AI\Chat\DTOs;

/**
 * Represents a single message in a chat conversation.
 */
readonly class ChatMessage
{
    public function __construct(
        public string $role,      // system, user, assistant
        public string $content,
    ) {}

    public static function system(string $content): self
    {
        return new self('system', $content);
    }

    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
        ];
    }
}
