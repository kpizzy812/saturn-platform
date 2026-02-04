<?php

namespace App\Services\AI\Chat\DTOs;

/**
 * Result of AI intent parsing - supports multiple commands.
 */
readonly class ParsedIntent
{
    /**
     * @param  ParsedCommand[]  $commands
     */
    public function __construct(
        public array $commands = [],
        public float $confidence = 0.0,
        public bool $requiresConfirmation = false,
        public ?string $confirmationMessage = null,
        public ?string $responseText = null,
    ) {}

    public function hasCommands(): bool
    {
        return count($this->commands) > 0;
    }

    public function hasMultipleCommands(): bool
    {
        return count($this->commands) > 1;
    }

    public function getFirstCommand(): ?ParsedCommand
    {
        return $this->commands[0] ?? null;
    }

    public function hasActionableCommands(): bool
    {
        foreach ($this->commands as $command) {
            if ($command->isActionable()) {
                return true;
            }
        }

        return false;
    }

    public function hasDangerousCommands(): bool
    {
        foreach ($this->commands as $command) {
            if ($command->isDangerous()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all actionable commands.
     *
     * @return ParsedCommand[]
     */
    public function getActionableCommands(): array
    {
        return array_filter($this->commands, fn (ParsedCommand $cmd) => $cmd->isActionable());
    }

    /**
     * Get all dangerous commands.
     *
     * @return ParsedCommand[]
     */
    public function getDangerousCommands(): array
    {
        return array_filter($this->commands, fn (ParsedCommand $cmd) => $cmd->isDangerous());
    }

    public static function none(?string $responseText = null): self
    {
        return new self(
            commands: [],
            responseText: $responseText,
        );
    }

    public static function withConfirmation(
        array $commands,
        string $confirmationMessage,
        float $confidence = 1.0,
    ): self {
        return new self(
            commands: $commands,
            confidence: $confidence,
            requiresConfirmation: true,
            confirmationMessage: $confirmationMessage,
        );
    }

    public function toArray(): array
    {
        return [
            'commands' => array_map(fn (ParsedCommand $cmd) => $cmd->toArray(), $this->commands),
            'confidence' => $this->confidence,
            'requires_confirmation' => $this->requiresConfirmation,
            'confirmation_message' => $this->confirmationMessage,
            'response_text' => $this->responseText,
        ];
    }

    /**
     * Create ParsedIntent from AI response array.
     */
    public static function fromAIResponse(array $data): self
    {
        $commands = [];

        if (isset($data['commands']) && is_array($data['commands'])) {
            foreach ($data['commands'] as $cmdData) {
                if (isset($cmdData['action']) && $cmdData['action'] !== 'none') {
                    $commands[] = ParsedCommand::fromArray($cmdData);
                }
            }
        }

        // Check for dangerous commands and generate confirmation
        $hasDangerous = false;
        $dangerousActions = [];

        foreach ($commands as $cmd) {
            if ($cmd->isDangerous()) {
                $hasDangerous = true;
                $dangerousActions[] = $cmd->action.' '.$cmd->resourceName;
            }
        }

        $confirmationMessage = null;
        if ($hasDangerous) {
            $confirmationMessage = '⚠️ Вы уверены, что хотите выполнить следующие действия?'."\n";
            foreach ($dangerousActions as $action) {
                $confirmationMessage .= "- **{$action}**\n";
            }
            $confirmationMessage .= "\nЭто действие нельзя будет отменить!";
        }

        return new self(
            commands: $commands,
            confidence: (float) ($data['confidence'] ?? 0.8),
            requiresConfirmation: $hasDangerous,
            confirmationMessage: $confirmationMessage,
            responseText: $data['response_text'] ?? null,
        );
    }
}
