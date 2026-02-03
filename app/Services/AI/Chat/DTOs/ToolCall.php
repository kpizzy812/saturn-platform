<?php

namespace App\Services\AI\Chat\DTOs;

/**
 * Represents a tool/function call from the AI model.
 */
readonly class ToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
        public string $type = 'function',
    ) {}

    /**
     * Create from Anthropic tool_use block.
     */
    public static function fromAnthropic(array $data): self
    {
        return new self(
            id: $data['id'] ?? uniqid('tool_'),
            name: $data['name'] ?? '',
            arguments: $data['input'] ?? [],
            type: 'tool_use',
        );
    }

    /**
     * Create from OpenAI function call.
     */
    public static function fromOpenAI(array $data): self
    {
        $arguments = [];
        if (isset($data['function']['arguments'])) {
            $arguments = json_decode($data['function']['arguments'], true) ?? [];
        }

        return new self(
            id: $data['id'] ?? uniqid('call_'),
            name: $data['function']['name'] ?? '',
            arguments: $arguments,
            type: 'function',
        );
    }

    /**
     * Get argument value by key.
     */
    public function getArgument(string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    /**
     * Check if this is a specific tool call.
     */
    public function is(string $name): bool
    {
        return $this->name === $name;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'arguments' => $this->arguments,
            'type' => $this->type,
        ];
    }
}
