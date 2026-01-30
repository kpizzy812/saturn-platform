# Data Transfer Objects (DTOs)

**Директория:** `app/Services/RepositoryAnalyzer/DTOs/`

---

## MonorepoInfo

```php
<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

class MonorepoInfo
{
    public function __construct(
        public bool $isMonorepo,
        public ?string $type = null,
        public array $workspacePaths = [],
    ) {}

    public static function notMonorepo(): self
    {
        return new self(isMonorepo: false);
    }
}
```

---

## DetectedApp

```php
<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents a detected application in a repository
 */
readonly class DetectedApp
{
    public function __construct(
        public string $name,
        public string $path,
        public string $framework,
        public string $buildPack,
        public int $defaultPort,
        public ?string $buildCommand = null,
        public ?string $publishDirectory = null,
        public string $type = 'backend',  // backend, frontend, fullstack, unknown
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'framework' => $this->framework,
            'build_pack' => $this->buildPack,
            'default_port' => $this->defaultPort,
            'build_command' => $this->buildCommand,
            'publish_directory' => $this->publishDirectory,
            'type' => $this->type,
        ];
    }

    /**
     * Check if this is a static site (frontend-only)
     */
    public function isStatic(): bool
    {
        return $this->buildPack === 'static';
    }

    /**
     * Check if this app can handle backend requests
     */
    public function hasBackend(): bool
    {
        return in_array($this->type, ['backend', 'fullstack'], true);
    }
}
```

---

## DetectedDatabase

```php
<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

/**
 * Represents a detected database dependency
 *
 * This DTO is immutable - use withMergedConsumers() to create
 * a new instance with additional consumers.
 */
readonly class DetectedDatabase
{
    public function __construct(
        public string $type,          // postgresql, mysql, mongodb, redis, clickhouse
        public string $name,
        public string $envVarName,    // DATABASE_URL, REDIS_URL, etc.
        public array $consumers = [], // App names that use this DB
        public ?string $detectedVia = null,
    ) {}

    /**
     * Create new instance with merged consumers (immutable pattern)
     *
     * @param string[] $additionalConsumers
     */
    public function withMergedConsumers(array $additionalConsumers): self
    {
        return new self(
            type: $this->type,
            name: $this->name,
            envVarName: $this->envVarName,
            consumers: array_unique(array_merge($this->consumers, $additionalConsumers)),
            detectedVia: $this->detectedVia,
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'env_var_name' => $this->envVarName,
            'consumers' => $this->consumers,
            'detected_via' => $this->detectedVia,
        ];
    }
}
```

---

## DetectedService

```php
<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

class DetectedService
{
    public function __construct(
        public string $type,
        public string $description,
        public array $requiredEnvVars,
        public array $consumers = [],
    ) {}
}
```

---

## DetectedEnvVariable

```php
<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

class DetectedEnvVariable
{
    public function __construct(
        public string $key,
        public ?string $defaultValue,
        public bool $isRequired,
        public string $category,
        public string $forApp,
    ) {}
}
```

---

## DependencyAnalysisResult

```php
<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

class DependencyAnalysisResult
{
    public function __construct(
        public array $databases,
        public array $services,
        public array $envVariables,
    ) {}
}
```

---

## AnalysisResult

```php
<?php

namespace App\Services\RepositoryAnalyzer\DTOs;

class AnalysisResult
{
    public function __construct(
        public MonorepoInfo $monorepo,
        public array $applications,
        public array $databases,
        public array $services,
        public array $envVariables,
    ) {}

    public function toArray(): array
    {
        return [
            'is_monorepo' => $this->monorepo->isMonorepo,
            'monorepo_type' => $this->monorepo->type,
            'applications' => array_map(fn($a) => $a->toArray(), $this->applications),
            'databases' => array_map(fn($d) => $d->toArray(), $this->databases),
            'services' => array_map(fn($s) => [
                'type' => $s->type,
                'description' => $s->description,
                'required_env_vars' => $s->requiredEnvVars,
            ], $this->services),
            'env_variables' => array_map(fn($e) => [
                'key' => $e->key,
                'default_value' => $e->defaultValue,
                'is_required' => $e->isRequired,
                'category' => $e->category,
                'for_app' => $e->forApp,
            ], $this->envVariables),
        ];
    }
}
```

---

## ProvisioningResult

**Файл:** `app/Services/RepositoryAnalyzer/ProvisioningResult.php`

```php
<?php

namespace App\Services\RepositoryAnalyzer;

class ProvisioningResult
{
    public function __construct(
        public array $applications,
        public array $databases,
        public ?string $monorepoGroupId,
    ) {}
}
```

---

## Исключения (Exceptions)

**Файл:** `app/Services/RepositoryAnalyzer/Exceptions/RepositoryAnalysisException.php`

```php
<?php

namespace App\Services\RepositoryAnalyzer\Exceptions;

use Exception;

/**
 * Exception thrown when repository analysis fails
 */
class RepositoryAnalysisException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
```

---

**Файл:** `app/Services/RepositoryAnalyzer/Exceptions/ProvisioningException.php`

```php
<?php

namespace App\Services\RepositoryAnalyzer\Exceptions;

use Exception;

/**
 * Exception thrown when infrastructure provisioning fails
 */
class ProvisioningException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
```
