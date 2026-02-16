<?php

namespace App\Services\AI\Chat;

use App\Models\Application;
use App\Models\Service;
use App\Services\AI\Chat\Contracts\ChatProviderInterface;
use App\Services\AI\Chat\DTOs\ChatMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * AI-powered error analysis service for resource logs.
 *
 * Analyzes logs from applications, services, and databases to identify
 * errors, their root causes, and potential solutions.
 */
class ResourceErrorAnalyzer
{
    private ?ChatProviderInterface $provider = null;

    public function __construct(?ChatProviderInterface $provider = null)
    {
        $this->provider = $provider;
    }

    /**
     * Set the AI provider for analysis.
     */
    public function setProvider(ChatProviderInterface $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Analyze logs from a single resource for errors.
     *
     * @param  int  $logLines  Number of log lines to fetch
     * @return array{
     *     success: bool,
     *     resource_name: string,
     *     resource_type: string,
     *     errors_found: int,
     *     issues: array<array{severity: string, message: string, suggestion: string|null}>,
     *     summary: string|null,
     *     solutions: array<string>,
     *     raw_logs?: string
     * }
     */
    public function analyze(Model $resource, int $logLines = 200): array
    {
        $resourceName = $resource->getAttribute('name') ?? 'Unknown';
        $resourceType = class_basename($resource);

        try {
            $logs = $this->fetchLogs($resource, $logLines);

            if (empty(trim($logs)) || $logs === 'No logs available') {
                return [
                    'success' => true,
                    'resource_name' => $resourceName,
                    'resource_type' => $resourceType,
                    'errors_found' => 0,
                    'issues' => [],
                    'summary' => 'Логи пусты или недоступны.',
                    'solutions' => [],
                ];
            }

            // If no provider, return basic analysis
            if (! $this->provider || ! $this->provider->isAvailable()) {
                return $this->basicAnalysis($logs, $resourceName, $resourceType);
            }

            // Use AI for detailed analysis
            return $this->aiAnalysis($logs, $resourceName, $resourceType);
        } catch (\Throwable $e) {
            Log::error('ResourceErrorAnalyzer failed', [
                'resource' => $resourceName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'resource_name' => $resourceName,
                'resource_type' => $resourceType,
                'errors_found' => 0,
                'issues' => [],
                'summary' => "Ошибка анализа: {$e->getMessage()}",
                'solutions' => [],
            ];
        }
    }

    /**
     * Analyze multiple resources in batch.
     *
     * @param  Model[]  $resources
     * @return array<string, array>
     */
    public function analyzeMultiple(array $resources): array
    {
        $results = [];

        foreach ($resources as $resource) {
            $name = $resource->getAttribute('name') ?? $resource->getAttribute('uuid') ?? 'unknown';
            $results[$name] = $this->analyze($resource);
        }

        return $results;
    }

    /**
     * Perform basic pattern-based error analysis without AI.
     */
    private function basicAnalysis(string $logs, string $resourceName, string $resourceType): array
    {
        $issues = [];
        $lines = explode("\n", $logs);

        $errorPatterns = [
            'critical' => [
                '/fatal\s*error/i' => 'Fatal error detected',
                '/out\s*of\s*memory/i' => 'Out of memory error',
                '/panic/i' => 'Panic/crash detected',
                '/segmentation\s*fault/i' => 'Segmentation fault',
                '/killed/i' => 'Process killed (possibly OOM)',
            ],
            'high' => [
                '/error/i' => 'Error logged',
                '/exception/i' => 'Exception thrown',
                '/failed/i' => 'Operation failed',
                '/cannot\s*connect/i' => 'Connection failure',
                '/connection\s*refused/i' => 'Connection refused',
                '/timeout/i' => 'Timeout occurred',
            ],
            'medium' => [
                '/warning/i' => 'Warning logged',
                '/deprecated/i' => 'Deprecated feature used',
                '/retry/i' => 'Retry attempt',
            ],
            'low' => [
                '/notice/i' => 'Notice logged',
                '/info.*error/i' => 'Info-level error mention',
            ],
        ];

        $foundErrors = [];

        foreach ($lines as $lineNum => $line) {
            foreach ($errorPatterns as $severity => $patterns) {
                foreach ($patterns as $pattern => $description) {
                    if (preg_match($pattern, $line) && ! isset($foundErrors[md5($line)])) {
                        $foundErrors[md5($line)] = true;
                        $issues[] = [
                            'severity' => $severity,
                            'message' => substr(trim($line), 0, 200),
                            'suggestion' => $description,
                            'line_number' => $lineNum + 1,
                        ];
                    }
                }
            }
        }

        // Sort by severity
        $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($issues, fn ($a, $b) => $severityOrder[$a['severity']] <=> $severityOrder[$b['severity']]);

        // Limit to top 20 issues
        $issues = array_slice($issues, 0, 20);

        $criticalCount = count(array_filter($issues, fn ($i) => $i['severity'] === 'critical'));
        $highCount = count(array_filter($issues, fn ($i) => $i['severity'] === 'high'));

        $summary = count($issues) > 0
            ? "Обнаружено {$criticalCount} критических и {$highCount} серьёзных ошибок в логах."
            : 'Критических ошибок не обнаружено.';

        return [
            'success' => true,
            'resource_name' => $resourceName,
            'resource_type' => $resourceType,
            'errors_found' => count($issues),
            'issues' => $issues,
            'summary' => $summary,
            'solutions' => $this->generateBasicSolutions($issues),
        ];
    }

    /**
     * Perform AI-powered error analysis.
     */
    private function aiAnalysis(string $logs, string $resourceName, string $resourceType): array
    {
        $prompt = $this->buildAnalysisPrompt($logs, $resourceName, $resourceType);

        $messages = [
            ChatMessage::system($this->getSystemPrompt()),
            ChatMessage::user($prompt),
        ];

        try {
            $response = $this->provider->chat($messages);

            if (! $response->success) {
                Log::warning('AI analysis failed, falling back to basic', ['error' => $response->error]);

                return $this->basicAnalysis($logs, $resourceName, $resourceType);
            }

            return $this->parseAiResponse($response->content, $resourceName, $resourceType, $logs);
        } catch (\Throwable $e) {
            Log::warning('AI analysis exception, falling back to basic', ['error' => $e->getMessage()]);

            return $this->basicAnalysis($logs, $resourceName, $resourceType);
        }
    }

    /**
     * Build the analysis prompt for AI.
     */
    private function buildAnalysisPrompt(string $logs, string $resourceName, string $resourceType): string
    {
        // Truncate logs if too long
        $maxLogSize = 10000;
        if (strlen($logs) > $maxLogSize) {
            $logs = "[TRUNCATED - showing last portion]\n".substr($logs, -$maxLogSize);
        }

        return <<<PROMPT
Проанализируй логи ресурса "{$resourceName}" (тип: {$resourceType}) и найди все ошибки и проблемы.

Логи:
```
{$logs}
```

Верни JSON-объект со следующей структурой:
{
  "errors_found": <число найденных ошибок>,
  "issues": [
    {
      "severity": "critical|high|medium|low",
      "message": "Описание проблемы",
      "suggestion": "Рекомендация по исправлению"
    }
  ],
  "summary": "Краткое резюме анализа",
  "solutions": ["Шаг 1 для решения", "Шаг 2 для решения"]
}

Правила:
1. Severity: critical (падения, OOM), high (ошибки), medium (warnings), low (notices)
2. Максимум 10 issues, отсортированных по severity
3. summary должен быть на русском языке
4. solutions - конкретные шаги для решения проблем
PROMPT;
    }

    /**
     * Get system prompt for AI analysis.
     */
    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Ты - эксперт по DevOps и анализу логов. Твоя задача - анализировать логи приложений,
находить ошибки, определять их причины и предлагать решения.

Отвечай ТОЛЬКО в формате JSON. Не добавляй никакого текста до или после JSON.
Если в логах нет явных ошибок, верни пустой массив issues.
PROMPT;
    }

    /**
     * Parse AI response and extract structured data.
     */
    private function parseAiResponse(string $content, string $resourceName, string $resourceType, string $logs): array
    {
        try {
            // Extract JSON from response
            $json = $this->extractJson($content);
            $data = json_decode($json, true);

            if (! $data || ! is_array($data)) {
                throw new \RuntimeException('Invalid JSON response');
            }

            return [
                'success' => true,
                'resource_name' => $resourceName,
                'resource_type' => $resourceType,
                'errors_found' => $data['errors_found'] ?? count($data['issues'] ?? []),
                'issues' => $data['issues'] ?? [],
                'summary' => $data['summary'] ?? null,
                'solutions' => $data['solutions'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to parse AI response', ['error' => $e->getMessage()]);

            return $this->basicAnalysis($logs, $resourceName, $resourceType);
        }
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

        // Try to find raw JSON
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            return $matches[0];
        }

        return $content;
    }

    /**
     * Generate basic solutions based on error patterns.
     */
    private function generateBasicSolutions(array $issues): array
    {
        $solutions = [];

        foreach ($issues as $issue) {
            $message = strtolower($issue['message'] ?? '');

            if (str_contains($message, 'out of memory') || str_contains($message, 'oom') || str_contains($message, 'killed')) {
                $solutions[] = 'Увеличьте лимит памяти для контейнера';
            }
            if (str_contains($message, 'connection refused') || str_contains($message, 'cannot connect')) {
                $solutions[] = 'Проверьте доступность зависимых сервисов (БД, Redis и т.д.)';
            }
            if (str_contains($message, 'timeout')) {
                $solutions[] = 'Увеличьте таймауты или проверьте производительность зависимых сервисов';
            }
            if (str_contains($message, 'permission denied') || str_contains($message, 'access denied')) {
                $solutions[] = 'Проверьте права доступа к файлам/директориям';
            }
            if (str_contains($message, 'no space left')) {
                $solutions[] = 'Освободите место на диске сервера';
            }
        }

        return array_unique($solutions);
    }

    /**
     * Fetch logs from a resource.
     */
    private function fetchLogs(Model $resource, int $lines = 200): string
    {
        try {
            if ($resource instanceof Application) {
                $server = $resource->destination->server;
                if (! $server->isFunctional()) {
                    return 'Server is not functional';
                }

                $containerName = $resource->uuid;
                $command = "docker logs --tail {$lines} {$containerName} 2>&1";

                return instant_remote_process([$command], $server, throwError: false) ?: 'No logs available';
            }

            if ($resource instanceof Service) {
                $server = $resource->destination->server;
                if (! $server->isFunctional()) {
                    return 'Server is not functional';
                }

                $firstApp = $resource->applications()->first();
                if ($firstApp) {
                    $containerName = $firstApp->uuid;
                    $command = "docker logs --tail {$lines} {$containerName} 2>&1";

                    return instant_remote_process([$command], $server, throwError: false) ?: 'No logs available';
                }

                return 'No container found for service';
            }

            // Database types
            if (method_exists($resource, 'destination') && $resource->getAttribute('destination')) {
                $destination = $resource->getAttribute('destination');
                $server = $destination->server;
                if (! $server->isFunctional()) {
                    return 'Server is not functional';
                }

                $containerName = $resource->getAttribute('uuid');
                $command = "docker logs --tail {$lines} {$containerName} 2>&1";

                return instant_remote_process([$command], $server, throwError: false) ?: 'No logs available';
            }

            return 'Resource type does not support logs';
        } catch (\Throwable $e) {
            return "Error fetching logs: {$e->getMessage()}";
        }
    }
}
