# Архитектура решения

## Общая схема

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         GIT REPOSITORY ANALYZER                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐ │
│  │  MonorepoDetector │────▶│   AppDetector    │────▶│ DependencyAnalyzer│
│  │                   │     │                   │     │                   │
│  │ • turbo.json      │     │ • package.json   │     │ • DB deps         │
│  │ • pnpm-workspace  │     │ • requirements   │     │ • Service deps    │
│  │ • nx.json         │     │ • go.mod         │     │ • Env vars        │
│  │ • lerna.json      │     │ • Cargo.toml     │     │                   │
│  └──────────────────┘     └──────────────────┘     └──────────────────┘ │
│           │                        │                        │            │
│           └────────────────────────┼────────────────────────┘            │
│                                    ▼                                     │
│                      ┌──────────────────────────┐                       │
│                      │   InfrastructureProposal  │                       │
│                      │                           │                       │
│                      │ • applications[]          │                       │
│                      │ • databases[]             │                       │
│                      │ • services[]              │                       │
│                      │ • env_variables[]         │                       │
│                      └──────────────────────────┘                       │
│                                    │                                     │
└────────────────────────────────────┼─────────────────────────────────────┘
                                     ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                         INFRASTRUCTURE PROVISIONER                       │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  1. Create Applications (Application::create())                         │
│  2. Create Databases (create_standalone_postgresql(), etc.)             │
│  3. Create ResourceLinks (auto_inject = true)                           │
│  4. Generate Environment Variables                                       │
│  5. Queue Deployments (queue_application_deployment())                  │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Поток данных

```
1. User вводит Git URL
         │
         ▼
2. API: POST /api/v1/git/analyze
         │
         ▼
3. Clone repository to temp directory
         │
         ▼
4. MonorepoDetector.detect()
   ├─ turbo.json? → Turborepo
   ├─ pnpm-workspace.yaml? → pnpm
   ├─ nx.json? → Nx
   └─ package.json workspaces? → npm/yarn
         │
         ▼
5. AppDetector.detect()
   ├─ Для каждого workspace path
   └─ Определить framework по dependencies
         │
         ▼
6. DependencyAnalyzer.analyze()
   ├─ Найти DB зависимости (pg, mongoose, redis...)
   ├─ Найти сервисные зависимости (aws-sdk, elasticsearch...)
   └─ Спарсить .env.example
         │
         ▼
7. Return AnalysisResult
         │
         ▼
8. UI показывает результат
   ├─ Список приложений с чекбоксами
   ├─ Список баз данных с чекбоксами
   └─ Предупреждения о внешних сервисах
         │
         ▼
9. User нажимает "Deploy Selected"
         │
         ▼
10. API: POST /api/v1/git/provision
         │
         ▼
11. InfrastructureProvisioner.provision()
    ├─ Create databases
    ├─ Create applications
    ├─ Create ResourceLinks
    └─ Queue deployments
         │
         ▼
12. Redirect to Project Canvas
```

---

## Главный сервис RepositoryAnalyzer

**Файл:** `app/Services/RepositoryAnalyzer/RepositoryAnalyzer.php`

```php
<?php

namespace App\Services\RepositoryAnalyzer;

use App\Services\RepositoryAnalyzer\Detectors\MonorepoDetector;
use App\Services\RepositoryAnalyzer\Detectors\AppDetector;
use App\Services\RepositoryAnalyzer\Detectors\DependencyAnalyzer;
use App\Services\RepositoryAnalyzer\DTOs\AnalysisResult;
use App\Services\RepositoryAnalyzer\DTOs\DetectedDatabase;
use App\Services\RepositoryAnalyzer\Exceptions\RepositoryAnalysisException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException as YamlException;
use JsonException;

class RepositoryAnalyzer
{
    /**
     * Maximum repository size in MB (prevents cloning huge repos)
     */
    private const MAX_REPO_SIZE_MB = 500;

    public function __construct(
        private MonorepoDetector $monorepoDetector,
        private AppDetector $appDetector,
        private DependencyAnalyzer $dependencyAnalyzer,
        private LoggerInterface $logger,
    ) {}

    /**
     * Analyze a git repository and return infrastructure proposal
     *
     * @throws RepositoryAnalysisException
     */
    public function analyze(string $repoPath): AnalysisResult
    {
        // Validate path is within allowed directory (prevent path traversal)
        $this->validateRepoPath($repoPath);

        try {
            // Step 1: Detect if monorepo
            $monorepoInfo = $this->monorepoDetector->detect($repoPath);

            // Step 2: Find all applications
            $apps = $monorepoInfo->isMonorepo
                ? $this->appDetector->detectFromMonorepo($repoPath, $monorepoInfo)
                : $this->appDetector->detectSingleApp($repoPath);

            // Step 3: Analyze dependencies for each app
            $databases = [];
            $services = [];
            $envVariables = [];

            foreach ($apps as $app) {
                $deps = $this->dependencyAnalyzer->analyze($repoPath, $app);
                $databases = array_merge($databases, $deps->databases);
                $services = array_merge($services, $deps->services);
                $envVariables = array_merge($envVariables, $deps->envVariables);
            }

            // Deduplicate databases (e.g., if both apps need PostgreSQL)
            $databases = $this->deduplicateDatabases($databases);

            return new AnalysisResult(
                monorepo: $monorepoInfo,
                applications: $apps,
                databases: $databases,
                services: $services,
                envVariables: $envVariables,
            );
        } catch (JsonException|YamlException $e) {
            $this->logger->warning('Failed to parse config file', [
                'path' => $repoPath,
                'error' => $e->getMessage(),
            ]);
            throw new RepositoryAnalysisException(
                "Failed to parse repository configuration: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Validate repository path is safe and within allowed directory
     *
     * @throws RepositoryAnalysisException
     */
    private function validateRepoPath(string $repoPath): void
    {
        $realPath = realpath($repoPath);
        $tempDir = realpath(sys_get_temp_dir());

        if ($realPath === false) {
            throw new RepositoryAnalysisException("Repository path does not exist: {$repoPath}");
        }

        if (!str_starts_with($realPath, $tempDir)) {
            throw new RepositoryAnalysisException("Repository path must be within temp directory");
        }

        // Check directory size
        $sizeMb = $this->getDirectorySizeMb($realPath);
        if ($sizeMb > self::MAX_REPO_SIZE_MB) {
            throw new RepositoryAnalysisException(
                "Repository too large: {$sizeMb}MB (max: " . self::MAX_REPO_SIZE_MB . "MB)"
            );
        }
    }

    private function getDirectorySizeMb(string $path): float
    {
        $output = shell_exec("du -sm " . escapeshellarg($path) . " 2>/dev/null | cut -f1");
        return (float) trim($output ?: '0');
    }

    /**
     * Deduplicate databases, merging consumers from duplicates
     *
     * @param DetectedDatabase[] $databases
     * @return DetectedDatabase[]
     */
    private function deduplicateDatabases(array $databases): array
    {
        $unique = [];
        foreach ($databases as $db) {
            $key = $db->type . '_' . ($db->name ?? 'default');
            if (!isset($unique[$key])) {
                $unique[$key] = $db;
            } else {
                // Create new DTO with merged consumers (DTOs are immutable)
                $unique[$key] = $unique[$key]->withMergedConsumers($db->consumers);
            }
        }
        return array_values($unique);
    }
}
```

---

## Интеграция с существующей архитектурой Saturn

### Используемые модели и хелперы

| Компонент | Файл | Использование |
|-----------|------|---------------|
| Application | `app/Models/Application.php` | Создание приложений |
| ResourceLink | `app/Models/ResourceLink.php` | Связь app ↔ database |
| StandalonePostgresql | `app/Models/StandalonePostgresql.php` | Модель PostgreSQL |
| create_standalone_* | `bootstrap/helpers/databases.php` | Создание БД |
| queue_application_deployment | `bootstrap/helpers/applications.php` | Запуск деплоя |

### ResourceLink и auto_inject

При создании связи `Application ↔ Database`:

```php
ResourceLink::create([
    'source_type' => Application::class,
    'source_id' => $application->id,
    'target_type' => StandalonePostgresql::class,
    'target_id' => $database->id,
    'auto_inject' => true,  // Автоматически добавит DATABASE_URL
    'inject_as' => null,    // Использовать default key
]);
```

Default env keys по типу БД:
- PostgreSQL/MySQL/MariaDB → `DATABASE_URL`
- Redis/KeyDB/Dragonfly → `REDIS_URL`
- MongoDB → `MONGODB_URL`
- ClickHouse → `CLICKHOUSE_URL`
