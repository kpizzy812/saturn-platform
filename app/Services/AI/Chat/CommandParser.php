<?php

namespace App\Services\AI\Chat;

use App\Services\AI\Chat\Contracts\ChatProviderInterface;
use App\Services\AI\Chat\DTOs\ChatMessage;
use App\Services\AI\Chat\DTOs\IntentResult;
use App\Services\AI\Chat\DTOs\ParsedCommand;
use App\Services\AI\Chat\DTOs\ParsedIntent;
use App\Services\AI\Chat\Providers\AnthropicChatProvider;
use App\Services\AI\Chat\Providers\OpenAIChatProvider;
use Illuminate\Support\Facades\Log;

/**
 * AI-first command parser.
 * Uses AI to understand user intent and extract multiple commands.
 */
class CommandParser
{
    private ?ChatProviderInterface $provider = null;

    /**
     * Dangerous actions that require confirmation.
     */
    private const DANGEROUS_ACTIONS = [
        'deploy',
        'stop',
        'delete',
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
     * Parse user message to detect multiple commands using AI.
     *
     * @param  array|null  $context  Current context (resource type, id, name)
     */
    public function parseCommands(string $message, ?array $context = null): ParsedIntent
    {
        if (! $this->provider || ! $this->provider->isAvailable()) {
            Log::warning('CommandParser: No AI provider available');

            return ParsedIntent::none('AI сервис недоступен. Пожалуйста, проверьте настройки API ключей.');
        }

        try {
            $systemPrompt = $this->buildSystemPrompt($context);
            $messages = [
                ChatMessage::system($systemPrompt),
                ChatMessage::user($message),
            ];

            // Use provider-specific tool calling
            if ($this->provider instanceof OpenAIChatProvider) {
                return $this->parseWithOpenAI($messages, $context);
            } elseif ($this->provider instanceof AnthropicChatProvider) {
                return $this->parseWithAnthropic($messages, $context);
            }

            // Fallback to generic parsing
            return $this->parseWithGenericAI($messages, $context);
        } catch (\Throwable $e) {
            Log::error('CommandParser error', ['error' => $e->getMessage()]);

            return ParsedIntent::none('Произошла ошибка при обработке запроса: '.$e->getMessage());
        }
    }

    /**
     * Legacy method for backward compatibility.
     * Converts ParsedIntent to IntentResult (single command).
     */
    public function parse(string $message, ?array $context = null, bool $useToolCalling = true): IntentResult
    {
        $parsedIntent = $this->parseCommands($message, $context);

        // Convert to legacy IntentResult (first command only)
        $firstCommand = $parsedIntent->getFirstCommand();

        if (! $firstCommand || $firstCommand->action === 'none') {
            return IntentResult::none($parsedIntent->responseText);
        }

        $params = [
            'resource_type' => $firstCommand->resourceType,
            'resource_name' => $firstCommand->resourceName,
            'resource_id' => $firstCommand->resourceId,
            'resource_uuid' => $firstCommand->resourceUuid,
            'project_name' => $firstCommand->projectName,
            'environment_name' => $firstCommand->environmentName,
        ];

        // Filter out null values
        $params = array_filter($params, fn ($v) => $v !== null);

        return new IntentResult(
            intent: $firstCommand->action,
            params: $params,
            confidence: $parsedIntent->confidence,
            requiresConfirmation: $parsedIntent->requiresConfirmation,
            confirmationMessage: $parsedIntent->confirmationMessage,
            responseText: $parsedIntent->responseText,
        );
    }

    /**
     * Parse using OpenAI function calling.
     */
    private function parseWithOpenAI(array $messages, ?array $context): ParsedIntent
    {
        $tools = ToolDefinitions::parseCommandsOpenAI();

        /** @var OpenAIChatProvider $provider */
        $provider = $this->provider;

        $response = $provider->chat($messages, $tools, 'parse_commands');

        if (! $response->success) {
            Log::warning('OpenAI parsing failed', ['error' => $response->error]);

            return ParsedIntent::none($response->error, $response->inputTokens, $response->outputTokens);
        }

        if ($response->hasToolCalls()) {
            $toolCall = $response->getToolCall('parse_commands');
            if ($toolCall) {
                return $this->buildParsedIntent($toolCall->arguments, $context, $response->inputTokens, $response->outputTokens);
            }
        }

        // If no tool call, try to parse content
        if ($response->content) {
            return $this->parseStructuredContent($response->content, $context, $response->inputTokens, $response->outputTokens);
        }

        return ParsedIntent::none(null, $response->inputTokens, $response->outputTokens);
    }

    /**
     * Parse using Anthropic tool use.
     */
    private function parseWithAnthropic(array $messages, ?array $context): ParsedIntent
    {
        $tools = ToolDefinitions::parseCommandsAnthropic();

        /** @var AnthropicChatProvider $provider */
        $provider = $this->provider;

        $response = $provider->chat($messages, $tools, 'parse_commands');

        if (! $response->success) {
            Log::warning('Anthropic parsing failed', ['error' => $response->error]);

            return ParsedIntent::none($response->error, $response->inputTokens, $response->outputTokens);
        }

        if ($response->hasToolCalls()) {
            $toolCall = $response->getToolCall('parse_commands');
            if ($toolCall) {
                return $this->buildParsedIntent($toolCall->arguments, $context, $response->inputTokens, $response->outputTokens);
            }
        }

        if ($response->content) {
            return ParsedIntent::none($response->content, $response->inputTokens, $response->outputTokens);
        }

        return ParsedIntent::none(null, $response->inputTokens, $response->outputTokens);
    }

    /**
     * Parse with generic AI (no specific tool calling).
     */
    private function parseWithGenericAI(array $messages, ?array $context): ParsedIntent
    {
        $response = $this->provider->chat($messages);

        if (! $response->success) {
            return ParsedIntent::none($response->error, $response->inputTokens, $response->outputTokens);
        }

        return $this->parseStructuredContent($response->content, $context, $response->inputTokens, $response->outputTokens);
    }

    /**
     * Build ParsedIntent from AI response data.
     */
    private function buildParsedIntent(array $data, ?array $context, int $inputTokens = 0, int $outputTokens = 0): ParsedIntent
    {
        $commands = [];

        if (isset($data['commands']) && is_array($data['commands'])) {
            foreach ($data['commands'] as $cmdData) {
                $action = $cmdData['action'] ?? 'none';

                if ($action === 'none') {
                    continue;
                }

                // Merge context if resource not specified
                $resourceType = $this->normalizeNull($cmdData['resource_type'] ?? null);
                $resourceName = $this->normalizeNull($cmdData['resource_name'] ?? null);

                if ($context && ! $resourceName && ! $resourceType) {
                    $resourceType = $context['type'] ?? null;
                    $resourceName = $context['name'] ?? null;
                }

                $commands[] = new ParsedCommand(
                    action: $action,
                    resourceType: $resourceType,
                    resourceName: $resourceName,
                    resourceId: $context['id'] ?? null,
                    resourceUuid: $context['uuid'] ?? null,
                    projectName: $this->normalizeNull($cmdData['project_name'] ?? null),
                    environmentName: $this->normalizeNull($cmdData['environment_name'] ?? null),
                    deploymentUuid: $this->normalizeNull($cmdData['deployment_uuid'] ?? null),
                    targetScope: $this->normalizeNull($cmdData['target_scope'] ?? null),
                    resourceNames: $cmdData['resource_names'] ?? null,
                    timePeriod: $this->normalizeNull($cmdData['time_period'] ?? null),
                );
            }
        }

        // Check for dangerous commands
        $hasDangerous = false;
        $dangerousDescriptions = [];

        foreach ($commands as $cmd) {
            if (in_array($cmd->action, self::DANGEROUS_ACTIONS, true)) {
                $hasDangerous = true;
                $resourceDesc = $cmd->resourceName ?? $cmd->resourceType ?? 'ресурс';
                $dangerousDescriptions[] = "**{$cmd->action}** {$resourceDesc}";
            }
        }

        $confirmationMessage = null;
        if ($hasDangerous) {
            $confirmationMessage = "⚠️ Вы уверены, что хотите выполнить следующие действия?\n\n";
            foreach ($dangerousDescriptions as $desc) {
                $confirmationMessage .= "- {$desc}\n";
            }
            $confirmationMessage .= "\nЭто действие может быть необратимым. Подтвердите, ответив 'да' или 'confirm'.";
        }

        return new ParsedIntent(
            commands: $commands,
            confidence: (float) ($data['confidence'] ?? 0.8),
            requiresConfirmation: $hasDangerous,
            confirmationMessage: $confirmationMessage,
            responseText: $data['response_text'] ?? null,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
        );
    }

    /**
     * Parse structured JSON content from AI response.
     */
    private function parseStructuredContent(string $content, ?array $context, int $inputTokens = 0, int $outputTokens = 0): ParsedIntent
    {
        try {
            $json = $this->extractJson($content);
            $data = json_decode($json, true);

            if (! $data) {
                return ParsedIntent::none($content, $inputTokens, $outputTokens);
            }

            return $this->buildParsedIntent($data, $context, $inputTokens, $outputTokens);
        } catch (\Throwable $e) {
            Log::warning('Failed to parse structured content', ['error' => $e->getMessage()]);

            return ParsedIntent::none($content, $inputTokens, $outputTokens);
        }
    }

    /**
     * Build system prompt for command parsing.
     */
    private function buildSystemPrompt(?array $context = null): string
    {
        $contextInfo = '';
        if ($context) {
            $contextInfo = sprintf(
                "\n\nТекущий контекст:\n- Тип ресурса: %s\n- Имя ресурса: %s\n- ID ресурса: %s",
                $context['type'] ?? 'не указан',
                $context['name'] ?? 'не указано',
                $context['id'] ?? 'не указан'
            );
        }

        return <<<PROMPT
Ты - интеллектуальный парсер команд для PaaS платформы Saturn.
Твоя задача - анализировать сообщения пользователя и извлекать из них команды для управления ресурсами.

**ВАЖНО: Ты ДОЛЖЕН вызвать инструмент parse_commands для КАЖДОГО сообщения!**

Доступные действия:
- **deploy** - задеплоить/развернуть приложение
- **restart** - перезапустить приложение, сервис или базу данных
- **stop** - остановить ресурс
- **start** - запустить остановленный ресурс
- **logs** - показать логи
- **status** - показать статус ресурсов
- **delete** - удалить проект, приложение, сервис или базу данных
- **analyze_errors** - AI анализ ошибок в логах ресурса (находит проблемы, предлагает решения)
- **analyze_deployment** - анализ неудачного деплоя (root cause, solution, prevention)
- **code_review** - показать результаты code review для приложения/деплоя
- **health_check** - проверить здоровье всех ресурсов проекта
- **metrics** - показать метрики и статистику деплоев за период
- **help** - показать справку
- **none** - нет actionable команды (для вопросов, приветствий и т.д.)

Типы ресурсов:
- **application** - приложение
- **service** - сервис (docker-compose stack)
- **database** - база данных (postgresql, mysql, mongodb, redis и т.д.)
- **server** - сервер
- **project** - проект (контейнер для environments и ресурсов)

**Дополнительные поля для новых команд:**

- **target_scope**: single | multiple | all - для анализа одного, нескольких или всех ресурсов
- **resource_names**: массив имен ресурсов для множественного анализа
- **deployment_uuid**: UUID конкретного деплоя для analyze_deployment
- **time_period**: период для metrics ("24h", "7d", "30d")

**ГЛАВНЫЙ ПРИНЦИП — УТОЧНЯЙ ВСЁ НЕДОСТАЮЩЕЕ:**

Ты ОБЯЗАН собрать ВСЮ необходимую информацию перед выполнением команды.
Если чего-то не хватает — НЕ УГАДЫВАЙ, а верни action: "none" и задай вопрос в response_text.

**Что нужно для выполнения команды:**
- **Какое действие?** — если непонятно, спроси
- **Какой ресурс?** — имя обязательно, если не указано — спроси
- **Какой тип?** — application/service/database/server, если неясно — спроси
- **Какое окружение?** — если ресурс может быть в нескольких env — спроси
- **Какой проект?** — если имя не уникально и есть в разных проектах — спроси

**Когда ОБЯЗАТЕЛЬНО спрашивать:**
- Пользователь не указал имя ресурса: "задеплой" → "Какое приложение задеплоить?"
- Имя может быть в нескольких окружениях: "перезапусти PixelAPI" → "PixelAPI есть в dev и prod. Какой?"
- Неясен тип: "удали pixel" → "Что именно удалить — приложение, сервис или проект?"
- Неясно действие: "сделай что-нибудь с API" → "Что именно сделать?"
- Любая другая неоднозначность

**Когда НЕ спрашивать (выполнять сразу):**
- Всё однозначно: "перезапусти PixelAPI в development" → restart
- Ресурс уникален в команде: "логи redis" → если redis один, сразу показать
- status без ресурса → показать обзор всех ресурсов
- health_check без ресурса → проверить всё
- help → показать справку

**Правила парсинга:**

1. **Множественные ресурсы**: Несколько ресурсов → отдельная команда для каждого.
   "перезапусти app1, app2 и db1" → 3 команды

2. **Множественные действия**: Несколько действий → команда для каждого.
   "удали test и перезапусти prod-app" → 2 команды

3. **Тип ресурса по контексту**:
   - db, database, postgres, mysql, redis → database
   - app, application, api, frontend, backend → application
   - project, проект → project

4. **Язык ответа**: ВСЕГДА на том же языке что и сообщение пользователя.

5. **resource_name** содержит ТОЛЬКО имя ресурса, БЕЗ окружения.
   Окружение (dev, development, staging, uat, prod, production) → в environment_name.
   ЗАПРЕЩЕНО: resource_name: "PixelAPI (development)" — скобки НЕ должны быть в resource_name.

6. **Анализ ошибок (analyze_errors)**:
   - "проанализируй ошибки api-service" → analyze_errors, resource_name: api-service
   - "найди проблемы во всех сервисах" → analyze_errors, target_scope: all

7. **Анализ деплоя (analyze_deployment)**:
   - "почему упал последний деплой" → analyze_deployment (последний failed)
   - "проанализируй деплой abc123" → analyze_deployment, deployment_uuid: abc123

8. **Метрики (metrics)**:
   - "покажи метрики за неделю" → metrics, time_period: 7d

9. **Удаление всех кроме (delete с target_scope=all)**:
   "удали все кроме X" → action: delete, resource_type: project, target_scope: all, resource_names: ["X"]
   resource_names = имена которые СОХРАНИТЬ.
   ЗАПРЕЩЕНО создавать отдельные команды для каждого проекта с придуманными именами!
{$contextInfo}
PROMPT;
    }

    /**
     * Extract JSON from response that might be wrapped in markdown.
     */
    private function extractJson(string $content): string
    {
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $content, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            return $matches[0];
        }

        return $content;
    }

    /**
     * Normalize 'null' string to actual null.
     */
    private function normalizeNull(mixed $value): mixed
    {
        if ($value === 'null' || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Legacy method for extracting resource info.
     * Kept for backward compatibility with tests.
     */
    public function extractResourceInfo(string $message): array
    {
        // This is now handled by AI, but kept for tests
        $info = [
            'name' => null,
            'project' => null,
            'environment' => null,
        ];

        // Simple regex fallback for basic extraction
        $commands = 'deploy|restart|stop|start|logs|status|delete|remove|деплой|задеплой|разверни|перезапусти|рестарт|останови|стоп|выключи|запусти|старт|включи|логи|статус|удали|удалить|убери|убрать';

        if (preg_match('/(?:'.$commands.')\s+([a-zA-Z0-9_-]+)/ui', $message, $matches)) {
            $info['name'] = $matches[1];
        }

        if (preg_match('/(?:in|from|в)\s+([a-zA-Z0-9_-]+)\/([a-zA-Z0-9_-]+)/ui', $message, $matches)) {
            $info['project'] = $matches[1];
            $info['environment'] = $matches[2];
        } elseif (preg_match('/(?:in|from|в)\s+([a-zA-Z0-9_-]+)(?:\s|$)/ui', $message, $matches)) {
            $info['project'] = $matches[1];
        }

        return $info;
    }
}
